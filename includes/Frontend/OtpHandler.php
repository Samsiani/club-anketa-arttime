<?php
/**
 * OtpHandler Class - Handles OTP AJAX requests and script enqueuing
 *
 * @package ClubAnketa\Frontend
 */

namespace ClubAnketa\Frontend;

use ClubAnketa\Core\Utils;
use ClubAnketa\Api\SmsProvider;

if (!defined('ABSPATH')) {
    exit;
}

class OtpHandler {

    /**
     * Enqueue SMS verification scripts and styles
     */
    public function enqueue_scripts() {
        // Check if we're on a page that needs SMS verification
        global $post;
        $is_checkout = function_exists('is_checkout') && is_checkout();
        $is_account = function_exists('is_account_page') && is_account_page();
        $has_shortcode = $post && has_shortcode($post->post_content, 'club_anketa_form');

        $is_wc_page = $is_checkout || $is_account;

        if (!$is_wc_page && !$has_shortcode) {
            return;
        }

        // Register and enqueue frontend CSS
        wp_register_style(
            'club-anketa-frontend',
            CLUB_ANKETA_URL . 'assets/css/frontend.css',
            [],
            CLUB_ANKETA_VERSION
        );
        wp_enqueue_style('club-anketa-frontend');

        // Enqueue verification JS
        wp_enqueue_script(
            'club-anketa-sms-verification',
            CLUB_ANKETA_URL . 'assets/js/sms-verification.js',
            ['jquery'],
            CLUB_ANKETA_VERSION,
            true
        );

        // Get verified phone number for logged-in users
        $verified_phone = '';
        if (is_user_logged_in()) {
            $verified_phone = Utils::get_user_verified_phone(get_current_user_id());
        }

        // Localize script
        wp_localize_script('club-anketa-sms-verification', 'clubAnketaSms', [
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('club_anketa_sms_nonce'),
            'verifiedPhone' => $verified_phone,
            'i18n'          => [
                'sendingOtp'           => __('იგზავნება...', 'club-anketa'),
                'enterCode'            => __('შეიყვანეთ 6-ნიშნა კოდი', 'club-anketa'),
                'verifying'            => __('მოწმდება...', 'club-anketa'),
                'verified'             => __('დადასტურებულია!', 'club-anketa'),
                'error'                => __('შეცდომა', 'club-anketa'),
                'invalidCode'          => __('არასწორი კოდი', 'club-anketa'),
                'codeExpired'          => __('კოდის ვადა ამოიწურა', 'club-anketa'),
                'resendIn'             => __('ხელახლა გაგზავნა:', 'club-anketa'),
                'resend'               => __('ხელახლა გაგზავნა', 'club-anketa'),
                'close'                => __('დახურვა', 'club-anketa'),
                'verify'               => __('დადასტურება', 'club-anketa'),
                'phoneRequired'        => __('ტელეფონის ნომერი სავალდებულოა', 'club-anketa'),
                'rateLimitError'       => __('ზედმეტად ბევრი მცდელობა. გთხოვთ სცადეთ მოგვიანებით.', 'club-anketa'),
                'modalTitle'           => __('ტელეფონის ვერიფიკაცია', 'club-anketa'),
                'modalSubtitle'        => __('SMS კოდი გამოგზავნილია ნომერზე:', 'club-anketa'),
                'verifyBtn'            => __('Verify', 'club-anketa'),
                'verificationRequired' => __('ტელეფონის ვერიფიკაცია სავალდებულოა', 'club-anketa'),
            ],
        ]);
    }

    /**
     * Inject OTP Modal HTML via wp_footer (Global Modal Strategy)
     * This ensures the modal is always a direct child of <body>, solving z-index issues
     */
    public function inject_modal_html() {
        // Only inject on pages that need SMS verification
        global $post;
        $is_checkout = function_exists('is_checkout') && is_checkout();
        $is_account = function_exists('is_account_page') && is_account_page();
        $has_shortcode = $post && has_shortcode($post->post_content, 'club_anketa_form');

        if (!$is_checkout && !$is_account && !$has_shortcode) {
            return;
        }

        // Include the modal template
        include CLUB_ANKETA_PATH . 'templates/public/modal-otp.php';
    }

    /**
     * AJAX handler: Send OTP
     */
    public function ajax_send_otp() {
        check_ajax_referer('club_anketa_sms_nonce', 'nonce');

        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $phone_digits = Utils::normalize_phone($phone);

        if (strlen($phone_digits) !== 9) {
            wp_send_json_error(['message' => __('Invalid phone number. Must be 9 digits.', 'club-anketa')]);
        }

        // Check rate limit
        if (!Utils::check_rate_limit($phone_digits)) {
            wp_send_json_error(['message' => __('Too many attempts. Please try again later.', 'club-anketa')]);
        }

        // Generate OTP
        $otp = Utils::generate_otp();

        // Store OTP in transient
        $otp_key = 'otp_' . $phone_digits;
        set_transient($otp_key, $otp, Utils::OTP_EXPIRY_SECONDS);

        // Send SMS via SmsProvider
        $result = SmsProvider::send_otp($phone_digits, $otp);

        if ($result['success']) {
            Utils::increment_rate_limit($phone_digits);
            wp_send_json_success([
                'message' => __('OTP sent successfully.', 'club-anketa'),
                'expires' => Utils::OTP_EXPIRY_SECONDS,
            ]);
        } else {
            wp_send_json_error(['message' => $result['error']]);
        }
    }

    /**
     * Rate limiting constants for OTP verification attempts
     */
    const VERIFY_MAX_ATTEMPTS = 5;
    const VERIFY_LOCKOUT_MINUTES = 15;

    /**
     * Check verification rate limit
     *
     * @param string $phone Phone number
     * @return bool True if within limits, false if locked out
     */
    private function check_verify_rate_limit($phone) {
        $key = 'otp_verify_attempts_' . $phone;
        $attempts = get_transient($key);
        
        if ($attempts === false) {
            return true;
        }
        
        return (int) $attempts < self::VERIFY_MAX_ATTEMPTS;
    }

    /**
     * Increment verification attempt counter
     *
     * @param string $phone Phone number
     */
    private function increment_verify_attempts($phone) {
        $key = 'otp_verify_attempts_' . $phone;
        $attempts = get_transient($key);
        
        if ($attempts === false) {
            set_transient($key, 1, self::VERIFY_LOCKOUT_MINUTES * MINUTE_IN_SECONDS);
        } else {
            set_transient($key, (int) $attempts + 1, self::VERIFY_LOCKOUT_MINUTES * MINUTE_IN_SECONDS);
        }
    }

    /**
     * Clear verification attempt counter on success
     *
     * @param string $phone Phone number
     */
    private function clear_verify_attempts($phone) {
        delete_transient('otp_verify_attempts_' . $phone);
    }

    /**
     * AJAX handler: Verify OTP
     */
    public function ajax_verify_otp() {
        check_ajax_referer('club_anketa_sms_nonce', 'nonce');

        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        $phone_digits = Utils::normalize_phone($phone);

        if (strlen($phone_digits) !== 9 || strlen($code) !== 6) {
            wp_send_json_error(['message' => __('Invalid phone or code format.', 'club-anketa')]);
        }

        // Check verification rate limit (brute-force protection)
        if (!$this->check_verify_rate_limit($phone_digits)) {
            wp_send_json_error([
                'message' => __('Too many failed verification attempts. Please wait 15 minutes and try again.', 'club-anketa')
            ]);
        }

        $otp_key = 'otp_' . $phone_digits;
        $stored_otp = get_transient($otp_key);

        if ($stored_otp === false) {
            $this->increment_verify_attempts($phone_digits);
            wp_send_json_error(['message' => __('OTP expired. Please request a new code.', 'club-anketa')]);
        }

        if ($stored_otp !== $code) {
            $this->increment_verify_attempts($phone_digits);
            wp_send_json_error(['message' => __('Invalid OTP code.', 'club-anketa')]);
        }

        // OTP verified - delete transient and create verification token
        delete_transient($otp_key);
        $this->clear_verify_attempts($phone_digits);

        // Create a temporary verification token for form submission
        $verify_token = wp_generate_password(32, false);
        $verify_key = 'otp_verified_' . $phone_digits;
        set_transient($verify_key, $verify_token, Utils::OTP_EXPIRY_SECONDS);

        // If user is logged in, update their verified phone number
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            update_user_meta($user_id, '_verified_phone_number', $phone_digits);
        }

        wp_send_json_success([
            'message'       => __('Phone verified successfully.', 'club-anketa'),
            'token'         => $verify_token,
            'verifiedPhone' => $phone_digits,
        ]);
    }
}
