<?php
if (!defined('ABSPATH')) {
    exit;
}

class Club_Anketa_Registration {

    private static $instance = null;
    private static $errors = [];
    private static $old = [];

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('club_anketa_form', [$this, 'shortcode_form']);
        // Process early so we can redirect ASAP
        add_action('template_redirect', [$this, 'maybe_process_submission'], 1);

        // Routing for print pages
        add_action('init', [$this, 'register_rewrite_rules']);
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_filter('template_include', [$this, 'maybe_use_print_template']);

        // Admin: Users list column for SMS consent
        add_filter('manage_users_columns', [$this, 'add_user_columns']);
        add_filter('manage_users_custom_column', [$this, 'render_user_columns'], 10, 3);

        // Optional async email hooks (not used by default)
        add_action('club_anketa_send_user_notification', [$this, 'send_user_notification'], 10, 1);
        add_action('club_anketa_send_user_notification_cron', [$this, 'send_user_notification'], 10, 1);
    }

    // ===== Routing for print pages =====

    public function register_rewrite_rules() {
        add_rewrite_rule('^print-anketa/?$', 'index.php?is_anketa_print_page=1', 'top');
        add_rewrite_rule('^signature-terms/?$', 'index.php?is_signature_terms_page=1', 'top');
    }

    public function register_query_vars($vars) {
        $vars[] = 'is_anketa_print_page';
        $vars[] = 'is_signature_terms_page';
        return $vars;
    }

    public function maybe_use_print_template($template) {
        if (get_query_var('is_anketa_print_page')) {
            return CLUB_ANKETA_PATH . 'templates/print-anketa.php';
        }
        if (get_query_var('is_signature_terms_page')) {
            return CLUB_ANKETA_PATH . 'templates/signature-terms.php';
        }
        return $template;
    }

    // ===== Form processing =====

    public function maybe_process_submission() {
        if ('POST' !== $_SERVER['REQUEST_METHOD'] || empty($_POST['club_anketa_form_submitted'])) {
            return;
        }

        // Nonce
        if (
            empty($_POST['club_anketa_form_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['club_anketa_form_nonce'])), 'club_anketa_register')
        ) {
            return;
        }

        // Honeypot
        $honeypot = isset($_POST['anketa_security_field']) ? trim((string) $_POST['anketa_security_field']) : '';
        if ($honeypot !== '') {
            return;
        }

        // Collect and sanitize inputs
        $fields = [
            'anketa_personal_id'        => 'text',
            'anketa_first_name'         => 'text',
            'anketa_last_name'          => 'text',
            'anketa_dob'                => 'date',
            'anketa_phone_local'        => 'tel',   // 9-digit local part
            'anketa_address'            => 'text',
            'anketa_email'              => 'email',
            'anketa_card_no'            => 'text',
            // Signature not collected
            'anketa_responsible_person' => 'text',
            'anketa_form_date'          => 'date',
            'anketa_shop'               => 'text',
            // SMS consent radio (default yes)
            'anketa_sms_consent'        => 'text',
        ];

        $data = [];
        foreach ($fields as $key => $type) {
            $raw = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
            $data[$key] = $this->sanitize_by_type($raw, $type);
        }
        self::$old = $data;

        // Required
        $required = [
            'anketa_personal_id' => __('Personal ID is required.', 'club-anketa'),
            'anketa_first_name'  => __('First name is required.', 'club-anketa'),
            'anketa_last_name'   => __('Last name is required.', 'club-anketa'),
            'anketa_dob'         => __('Date of birth is required.', 'club-anketa'),
            'anketa_phone_local' => __('Phone number is required.', 'club-anketa'),
            'anketa_email'       => __('Email is required.', 'club-anketa'),
        ];
        foreach ($required as $key => $message) {
            if ($data[$key] === '') {
                self::$errors[] = $message;
            }
        }

        // Email validity
        if ($data['anketa_email'] && !is_email($data['anketa_email'])) {
            self::$errors[] = __('Please enter a valid email address.', 'club-anketa');
        }

        // Phone local digits (username)
        $local_digits = preg_replace('/\D+/', '', (string)$data['anketa_phone_local']);
        if (!preg_match('/^\d{9}$/', $local_digits)) {
            self::$errors[] = __('Local phone number must be exactly 9 digits.', 'club-anketa');
        }

        // Uniqueness
        if ($local_digits && username_exists($local_digits)) {
            self::$errors[] = __('This phone number is already registered.', 'club-anketa');
        }
        if ($data['anketa_email'] && email_exists($data['anketa_email'])) {
            self::$errors[] = __('This email is already registered.', 'club-anketa');
        }

        if (!empty(self::$errors)) {
            return;
        }

        // Default SMS consent to 'yes' if not provided
        $sms_consent = strtolower($data['anketa_sms_consent']);
        if ($sms_consent !== 'yes' && $sms_consent !== 'no') {
            $sms_consent = 'yes';
        }

        // Create user quickly
        $password = wp_generate_password(18, true, true);
        $user_id = wp_insert_user([
            'user_login'   => $local_digits,
            'user_email'   => $data['anketa_email'],
            'user_pass'    => $password,
            'first_name'   => $data['anketa_first_name'],
            'last_name'    => $data['anketa_last_name'],
            'display_name' => trim($data['anketa_first_name'] . ' ' . $data['anketa_last_name']),
        ]);

        if (is_wp_error($user_id)) {
            self::$errors[] = $user_id->get_error_message();
            return;
        }

        // Save meta
        $full_phone = '+995 ' . $local_digits;
        $meta_map = [
            'billing_phone'               => $full_phone,
            'billing_address_1'           => $data['anketa_address'],
            '_anketa_personal_id'         => $data['anketa_personal_id'],
            '_anketa_dob'                 => $data['anketa_dob'],
            '_anketa_card_no'             => $data['anketa_card_no'],
            '_anketa_responsible_person'  => $data['anketa_responsible_person'],
            '_anketa_form_date'           => $data['anketa_form_date'],
            '_anketa_shop'                => $data['anketa_shop'],
            // SMS consent
            '_sms_consent'                => $sms_consent,
        ];
        foreach ($meta_map as $meta_key => $meta_value) {
            if ($meta_value !== '') {
                update_user_meta($user_id, $meta_key, $meta_value);
            }
        }

        // Redirect to print page
        $url = home_url('/signature-terms/?user_id=' . absint($user_id)); // Optional: go to terms first
        $url = home_url('/print-anketa/?user_id=' . absint($user_id));   // Default: go to anketa
        wp_safe_redirect($url);
        exit;
    }

    private function sanitize_by_type($value, $type) {
        $value = is_string($value) ? trim($value) : $value;
        switch ($type) {
            case 'email':
                return sanitize_email($value);
            case 'date':
                return preg_replace('/[^0-9\-\.\s\/]/', '', $value);
            case 'tel':
                return preg_replace('/[^0-9\+\-\s\(\)]/', '', $value);
            case 'text':
            default:
                return sanitize_text_field($value);
        }
    }

    // ===== Admin Users list column =====

    public function add_user_columns($columns) {
        $columns['club_anketa_sms'] = __('SMS accept', 'club-anketa');
        return $columns;
    }

    public function render_user_columns($output, $column_name, $user_id) {
        if ($column_name === 'club_anketa_sms') {
            $val = get_user_meta((int)$user_id, '_sms_consent', true);
            $val = is_string($val) ? strtolower($val) : '';
            if ($val === 'yes') {
                return '<span style="color:#2e7d32;font-weight:600;">' . esc_html__('Yes', 'club-anketa') . '</span>';
            }
            if ($val === 'no') {
                return '<span style="color:#c62828;font-weight:600;">' . esc_html__('No', 'club-anketa') . '</span>';
            }
            return '<span style="color:#616161;">' . esc_html__('(blank)', 'club-anketa') . '</span>';
        }
        return $output;
    }

    // ===== Optional async user email (unused by default) =====
    public function send_user_notification($user_id) {
        if ($user_id > 0 && function_exists('wp_new_user_notification')) {
            wp_new_user_notification($user_id, null, 'user');
        }
    }

    // ===== Shortcode =====

    public function shortcode_form() {
        // Enqueue form CSS
        wp_register_style(
            'club-anketa-form',
            CLUB_ANKETA_URL . 'assets/anketa-form.css',
            [],
            CLUB_ANKETA_VERSION
        );
        wp_enqueue_style('club-anketa-form');

        $errors = self::$errors;
        $old    = self::$old;

        $v = function ($key) use ($old) {
            return isset($old[$key]) ? esc_attr($old[$key]) : '';
        };

        $sms_old = isset($old['anketa_sms_consent']) ? strtolower($old['anketa_sms_consent']) : 'yes';
        if ($sms_old !== 'yes' && $sms_old !== 'no') {
            $sms_old = 'yes';
        }

        ob_start();
        ?>
        <div class="club-anketa-form-wrap">
            <?php if (!empty($errors)): ?>
                <div class="club-anketa-errors" role="alert" aria-live="polite">
                    <?php foreach ($errors as $e): ?>
                        <div><?php echo esc_html($e); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="club-anketa-form" method="post" action="">
                <?php wp_nonce_field('club_anketa_register', 'club_anketa_form_nonce'); ?>
                <input type="hidden" name="club_anketa_form_submitted" value="1" />
                <div class="club-anketa-hp">
                    <label for="anketa_security_field">Leave this empty</label>
                    <input type="text" id="anketa_security_field" name="anketa_security_field" value="" autocomplete="off" />
                </div>

                <div class="row">
                    <label class="label" for="anketa_personal_id">პირადი ნომერი *</label>
                    <div class="field">
                        <input type="text" id="anketa_personal_id" name="anketa_personal_id" required value="<?php echo $v('anketa_personal_id'); ?>" />
                    </div>
                </div>

                <div class="row">
                    <label class="label" for="anketa_first_name">სახელი *</label>
                    <div class="field">
                        <input type="text" id="anketa_first_name" name="anketa_first_name" required value="<?php echo $v('anketa_first_name'); ?>" />
                    </div>
                </div>

                <div class="row">
                    <label class="label" for="anketa_last_name">გვარი *</label>
                    <div class="field">
                        <input type="text" id="anketa_last_name" name="anketa_last_name" required value="<?php echo $v('anketa_last_name'); ?>" />
                    </div>
                </div>

                <div class="row">
                    <label class="label" for="anketa_dob">დაბადების თარიღი *</label>
                    <div class="field">
                        <input type="date" id="anketa_dob" name="anketa_dob" required value="<?php echo $v('anketa_dob'); ?>" />
                    </div>
                </div>

                <div class="row">
                    <label class="label">ტელეფონის ნომერი *</label>
                    <div class="field">
                        <div class="phone-group">
                            <input class="phone-prefix" type="text" value="+995" readonly aria-label="Country code +995" />
                            <input
                                class="phone-local"
                                type="tel"
                                id="anketa_phone_local"
                                name="anketa_phone_local"
                                inputmode="numeric"
                                pattern="[0-9]{9}"
                                maxlength="9"
                                placeholder="599620303"
                                required
                                value="<?php echo $v('anketa_phone_local'); ?>"
                                aria-describedby="phoneHelp"
                            />
                        </div>
                        <small id="phoneHelp" class="help-text">9-ციფრიანი ნომერი, მაგალითად 599620303</small>
                    </div>
                </div>

                <div class="row">
                    <label class="label" for="anketa_address">მისამართი</label>
                    <div class="field">
                        <input type="text" id="anketa_address" name="anketa_address" value="<?php echo $v('anketa_address'); ?>" />
                    </div>
                </div>

                <div class="row">
                    <label class="label" for="anketa_email">E-mail *</label>
                    <div class="field">
                        <input type="email" id="anketa_email" name="anketa_email" required value="<?php echo $v('anketa_email'); ?>" />
                    </div>
                </div>

                <div class="rules-wrap">
                    <div class="rules-title">წესები და პირობები</div>
                    <div class="rules-text">
                        <?php
                        $rules = <<<HTML
<p><strong>Arttime-ის კლუბის წევრები სარგებლობენ შემდეგი უპირატესობით:</strong></p>
<ul>
<li>ბარათზე 500-5000 ლარამდე დაგროვების შემთხვევაში ფასდაკლება 5%</li>
<li>ბარათზე 5001-10000 ლარამდე დაგროვების შემთხვევაში ფასდაკლება 10%;</li>
<li>ბარათზე 10 000 ლარზე მეტის დაგროვების შემთხვევაში ფასდაკლება 15%.</li>
</ul>
<p>&nbsp;</p>
<p><strong>გთხოვთ გაითვალისწინოთ:</strong></p>
<ol>
<li>ართთაიმის კლუბის ბარათით გათვალისწინებული ფასდაკლება არ მოქმედებს ფასდაკლებელ პროდუქციაზე;</li>
<li>ფასდაკლებული პროდუქციის შეძენის შემთხვევაში ბარათზე მხოლოდ ქულები დაგერიცხებათ;</li>
<li>ფასდაკლება მოქმედებს, მაგრამ ქულები არ გერიცხებათ პროდუქციის სასაჩუქრე ვაუჩერით შემენისას</li>
<li>სასაჩუქრე ვაუჩერის შეძენისას ფასდაკლება არ მოქმედებს, მაგრამ ქულები გროვდება:</li>
<li>დაგროვილი ქულები ბარათზე აისახება 2 სამუშაო დღის ვადაში;</li>
<li>გაითვალისწინეთ, წინამდებარე წესებით დადგენილი პირობები შეიძლება შეიცვალოს შპს „ართთაიმის“ მიერ, რომელიც სავალდებულო იქნება ბარათების პროექტში ჩართული მომხმარებლებისთვის.</li>
<li>ხელმოწერით ვადასტურებ ჩემი პირადი მონაცემების სიზუსტეს და ბარათის მიღებას</li>
</ol>
HTML;
                        echo wp_kses_post(apply_filters('club_anketa_rules_text', $rules));
                        ?>
                    </div>
                </div>

                <div class="row">
                    <label class="label" for="anketa_card_no">მივიღე ბარათი №</label>
                    <div class="field">
                        <input type="text" id="anketa_card_no" name="anketa_card_no" value="<?php echo $v('anketa_card_no'); ?>" />
                    </div>
                </div>

                <!-- SMS consent -->
                <div class="row">
                    <span class="label">SMS შეტყობინებების მიღების თანხმობა</span>
                    <div class="field">
                        <label style="margin-right:12px;">
                            <input type="radio" name="anketa_sms_consent" value="yes" <?php checked($sms_old, 'yes'); ?> />
                            დიახ
                        </label>
                        <label>
                            <input type="radio" name="anketa_sms_consent" value="no" <?php checked($sms_old, 'no'); ?> />
                            არა
                        </label>
                    </div>
                </div>

                <div class="row">
                    <label class="label" for="anketa_responsible_person">პასუხისმგებელი პირი</label>
                    <div class="field">
                        <input type="text" id="anketa_responsible_person" name="anketa_responsible_person" value="<?php echo $v('anketa_responsible_person'); ?>" />
                    </div>
                </div>

                <div class="row">
                    <label class="label" for="anketa_form_date">თარიღი</label>
                    <div class="field">
                        <input type="date" id="anketa_form_date" name="anketa_form_date" value="<?php echo $v('anketa_form_date'); ?>" />
                    </div>
                </div>

                <div class="row">
                    <label class="label" for="anketa_shop">მაღაზია</label>
                    <div class="field">
                        <input type="text" id="anketa_shop" name="anketa_shop" value="<?php echo $v('anketa_shop'); ?>" />
                    </div>
                </div>

                <div class="submit-row">
                    <button type="submit" class="submit-btn">რეგისტრაცია</button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}