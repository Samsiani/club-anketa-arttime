<?php
/**
 * Shortcode Class - Handles [club_anketa_form] shortcode
 *
 * @package ClubAnketa\Frontend
 */

namespace ClubAnketa\Frontend;

use ClubAnketa\Core\Utils;

if (!defined('ABSPATH')) {
    exit;
}

class Shortcode {

    /**
     * Form errors
     *
     * @var array
     */
    private static $errors = [];

    /**
     * Old form values
     *
     * @var array
     */
    private static $old = [];

    /**
     * Render the shortcode
     *
     * @return string Shortcode HTML output
     */
    public function render() {
        // Enqueue form CSS
        wp_enqueue_style('club-anketa-frontend');

        $errors = self::$errors;
        $old    = self::$old;

        // Helper function for getting old values
        $v = function ($key) use ($old) {
            return isset($old[$key]) ? esc_attr($old[$key]) : '';
        };

        // Get consent values
        $sms_old = isset($old['anketa_sms_consent']) ? strtolower($old['anketa_sms_consent']) : 'yes';
        if ($sms_old !== 'yes' && $sms_old !== 'no') {
            $sms_old = 'yes';
        }

        $call_old = isset($old['anketa_call_consent']) ? strtolower($old['anketa_call_consent']) : 'yes';
        if ($call_old !== 'yes' && $call_old !== 'no') {
            $call_old = 'yes';
        }

        // Start output buffering
        ob_start();

        // Include the template
        include CLUB_ANKETA_PATH . 'templates/public/form-registration.php';

        return ob_get_clean();
    }

    /**
     * Process form submission
     */
    public function process_submission() {
        if ('POST' !== $_SERVER['REQUEST_METHOD'] || empty($_POST['club_anketa_form_submitted'])) {
            return;
        }

        // Verify nonce
        if (
            empty($_POST['club_anketa_form_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['club_anketa_form_nonce'])), 'club_anketa_register')
        ) {
            return;
        }

        // Honeypot check
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
            'anketa_phone_local'        => 'tel',
            'anketa_address'            => 'text',
            'anketa_email'              => 'email',
            'anketa_card_no'            => 'text',
            'anketa_responsible_person' => 'text',
            'anketa_form_date'          => 'date',
            'anketa_shop'               => 'text',
            'anketa_sms_consent'        => 'text',
            'anketa_call_consent'       => 'text',
        ];

        $data = [];
        foreach ($fields as $key => $type) {
            $raw = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
            $data[$key] = Utils::sanitize_by_type($raw, $type);
        }
        self::$old = $data;

        // Validate required fields
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

        // Validate email
        if ($data['anketa_email'] && !is_email($data['anketa_email'])) {
            self::$errors[] = __('Please enter a valid email address.', 'club-anketa');
        }

        // Validate phone (9 digits)
        $local_digits = preg_replace('/\D+/', '', (string) $data['anketa_phone_local']);
        if (!preg_match('/^\d{9}$/', $local_digits)) {
            self::$errors[] = __('Local phone number must be exactly 9 digits.', 'club-anketa');
        }

        // Check uniqueness
        if ($local_digits && username_exists($local_digits)) {
            self::$errors[] = __('This phone number is already registered.', 'club-anketa');
        }
        if ($data['anketa_email'] && email_exists($data['anketa_email'])) {
            self::$errors[] = __('This email is already registered.', 'club-anketa');
        }

        if (!empty(self::$errors)) {
            return;
        }

        // Process consent values
        $sms_consent = strtolower($data['anketa_sms_consent']);
        if ($sms_consent !== 'yes' && $sms_consent !== 'no') {
            $sms_consent = 'yes';
        }

        $call_consent = strtolower($data['anketa_call_consent']);
        if ($call_consent !== 'yes' && $call_consent !== 'no') {
            $call_consent = 'yes';
        }

        // Check if phone was verified via OTP
        $phone_was_verified = Utils::is_phone_verified($local_digits);

        // Create user
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

        // Save user meta
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
            '_sms_consent'                => $sms_consent,
            '_call_consent'               => $call_consent,
            '_verified_phone_number'      => $phone_was_verified ? $local_digits : '',
        ];

        foreach ($meta_map as $meta_key => $meta_value) {
            if ($meta_value !== '') {
                update_user_meta($user_id, $meta_key, $meta_value);
            }
        }

        // Clean up verification token
        if ($phone_was_verified) {
            delete_transient('otp_verified_' . $local_digits);
        }

        // Redirect to print page
        $url = home_url('/print-anketa/?user_id=' . absint($user_id));
        wp_safe_redirect($url);
        exit;
    }

    /**
     * Get form errors
     *
     * @return array Form errors
     */
    public static function get_errors() {
        return self::$errors;
    }

    /**
     * Get old form values
     *
     * @return array Old form values
     */
    public static function get_old() {
        return self::$old;
    }
}
