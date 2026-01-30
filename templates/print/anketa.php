<?php
/**
 * Print Anketa Template
 * URL: /print-anketa/?user_id=123
 *
 * @package ClubAnketa
 */

use ClubAnketa\Core\Utils;

if (!defined('ABSPATH')) {
    exit;
}

$user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
$user    = $user_id ? get_user_by('ID', $user_id) : false;

if (!$user) {
    status_header(404);
    wp_die(esc_html__('User not found.', 'club-anketa'));
}

$meta = function ($key, $default = '') use ($user_id) {
    $v = get_user_meta($user_id, $key, true);
    return $v !== '' ? $v : $default;
};

$first_name   = $user->first_name;
$last_name    = $user->last_name;
$personal_id  = $meta('_anketa_personal_id');
$dob          = $meta('_anketa_dob');
$billing_raw  = get_user_meta($user_id, 'billing_phone', true);
$address_1    = get_user_meta($user_id, 'billing_address_1', true);
$email        = $user->user_email;
$card_no      = $meta('_anketa_card_no');
$responsible  = $meta('_anketa_responsible_person');
$form_date    = $meta('_anketa_form_date');
$shop         = $meta('_anketa_shop');

// Format phone
$phone = Utils::format_phone($billing_raw);

// Utility for boxed digits
$boxed = function ($text, $boxes = 11) {
    $text  = (string) $text;
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $cells = [];
    for ($i = 0; $i < $boxes; $i++) {
        $cells[] = isset($chars[$i]) ? esc_html($chars[$i]) : '&nbsp;';
    }
    return '<div class="boxes boxes-' . intval($boxes) . '"><span>' . implode('</span><span>', $cells) . '</span></div>';
};

// Terms links
$sms_terms_link = esc_url(add_query_arg(['user_id' => $user_id, 'terms_type' => 'sms'], home_url('/signature-terms/')));
$call_terms_link = esc_url(add_query_arg(['user_id' => $user_id, 'terms_type' => 'call'], home_url('/signature-terms/')));
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html__('Print Anketa', 'club-anketa'); ?></title>
<link rel="stylesheet" href="<?php echo esc_url(CLUB_ANKETA_URL . 'assets/css/print.css?v=' . urlencode(CLUB_ANKETA_VERSION)); ?>" media="all" />
</head>
<body>
    <div class="print-actions">
        <button onclick="window.print()"><?php echo esc_html__('Print Anketa', 'club-anketa'); ?></button>
        <a class="button button-secondary print-terms-btn" href="<?php echo $sms_terms_link; ?>"><?php echo esc_html__('Print SMS Terms', 'club-anketa'); ?></a>
        <a class="button button-secondary print-terms-btn" href="<?php echo $call_terms_link; ?>"><?php echo esc_html__('Print Phone Call Terms', 'club-anketa'); ?></a>
    </div>

    <div class="page">
        <h1 class="title"><?php echo esc_html('გახდი შპს "ართთაიმის" ს/კ 202356672 კლუბის წევრი!'); ?></h1>

        <div class="row">
            <div class="label"><?php esc_html_e('პირადი ნომერი', 'club-anketa'); ?></div>
            <div class="value value-boxes">
                <?php echo $boxed($personal_id, 11); ?>
            </div>
        </div>

        <div class="row">
            <div class="label"><?php esc_html_e('სახელი', 'club-anketa'); ?></div>
            <div class="value value-line"><?php echo esc_html($first_name); ?></div>
        </div>

        <div class="row">
            <div class="label"><?php esc_html_e('გვარი', 'club-anketa'); ?></div>
            <div class="value value-line"><?php echo esc_html($last_name); ?></div>
        </div>

        <div class="row">
            <div class="label"><?php esc_html_e('დაბადების თარიღი', 'club-anketa'); ?></div>
            <div class="value value-line"><?php echo esc_html($dob); ?></div>
        </div>

        <div class="row">
            <div class="label"><?php esc_html_e('ტელეფონის ნომერი', 'club-anketa'); ?></div>
            <div class="value value-line"><?php echo esc_html($phone); ?></div>
        </div>

        <div class="row">
            <div class="label"><?php esc_html_e('მისამართი', 'club-anketa'); ?></div>
            <div class="value value-line"><?php echo esc_html($address_1); ?></div>
        </div>

        <div class="row">
            <div class="label"><?php esc_html_e('E-mail', 'club-anketa'); ?></div>
            <div class="value value-line"><?php echo esc_html($email); ?></div>
        </div>

        <div class="rules">
            <div class="rules-title"><?php esc_html_e('წესები და პირობები', 'club-anketa'); ?></div>
            <div class="rules-inner">
                <?php
                $rules = '<p><strong>Arttime-ის კლუბის წევრები სარგებლობენ შემდეგი უპირატესობით:</strong></p>
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
<li>გაითვალისწინეთ, წინამდებარე წესებით დადგენილი პირობები შეიძლება შეიცვალოს შპს „ართთაიმის" მიერ, რომელიც სავალდებულო იქნება ბარათების პროექტში ჩართული მომხმარებლებისთვის.</li>
<li>ხელმოწერით ვადასტურებ ჩემი პირადი მონაცემების სიზუსტეს და ბარათის მიღებას</li>
</ol>';
                echo wp_kses_post(apply_filters('club_anketa_rules_text', $rules));
                ?>
            </div>
        </div>

        <div class="row">
            <div class="label"><?php esc_html_e('მივიღე ბარათი №', 'club-anketa'); ?></div>
            <div class="value value-boxes">
                <?php echo $boxed($card_no, 10); ?>
            </div>
        </div>

        <div class="row signature-row no-break">
            <div class="label"><?php esc_html_e('მომხმარებლის ხელმოწერა', 'club-anketa'); ?></div>
            <div class="value value-line"></div>
        </div>

        <div class="row">
            <div class="label"><?php esc_html_e('პასუხისმგებელი პირი', 'club-anketa'); ?></div>
            <div class="value value-line"><?php echo esc_html($responsible); ?></div>
        </div>

        <div class="row">
            <div class="label"><?php esc_html_e('თარიღი', 'club-anketa'); ?></div>
            <div class="value value-line"><?php echo esc_html($form_date); ?></div>
        </div>

        <div class="row">
            <div class="label"><?php esc_html_e('მაღაზია', 'club-anketa'); ?></div>
            <div class="value value-line"><?php echo esc_html($shop); ?></div>
        </div>
    </div>
</body>
</html>
