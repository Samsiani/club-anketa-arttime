<?php
/**
 * WooCommerce Integration Class
 * 
 * Handles all WooCommerce-specific hooks for checkout and account pages.
 * KEY FIX: Uses proper WordPress hooks instead of preg_replace for injecting
 * verification UI, solving WoodMart theme compatibility issues.
 *
 * @package ClubAnketa\Integrations
 */

namespace ClubAnketa\Integrations;

use ClubAnketa\Core\Utils;

if (!defined('ABSPATH')) {
    exit;
}

class WooCommerce {

    /**
     * Add phone verification button after checkout billing form
     * 
     * KEY FIX FOR WOODMART: Instead of modifying HTML with preg_replace (which breaks
     * in WoodMart's DOM structure), we use a proper WooCommerce hook to inject
     * the verification button container. JavaScript will then group it with the phone input.
     */
    public function add_phone_verification_button() {
        // Get verified phone for comparison
        $verified_phone = '';
        if (is_user_logged_in()) {
            $verified_phone = Utils::get_user_verified_phone(get_current_user_id());
        }

        // Get current billing phone value
        $current_phone = '';
        if (is_user_logged_in()) {
            $current_phone = Utils::normalize_phone(get_user_meta(get_current_user_id(), 'billing_phone', true));
        }

        $is_verified = !empty($verified_phone) && $current_phone === $verified_phone;

        // Output the verification container
        // JavaScript will move this next to #billing_phone and wrap them in phone-verify-group
        ?>
        <div id="billing_phone_verification" class="phone-verify-container" data-target="#billing_phone">
            <?php if ($is_verified): ?>
                <button type="button" class="phone-verify-btn" style="display:none;" aria-label="<?php esc_attr_e('Verify phone number', 'club-anketa'); ?>">
                    <?php esc_html_e('Verify', 'club-anketa'); ?>
                </button>
                <span class="phone-verified-icon" aria-label="<?php esc_attr_e('Phone verified', 'club-anketa'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </span>
            <?php else: ?>
                <button type="button" class="phone-verify-btn" aria-label="<?php esc_attr_e('Verify phone number', 'club-anketa'); ?>">
                    <?php esc_html_e('Verify', 'club-anketa'); ?>
                </button>
                <span class="phone-verified-icon" style="display:none;" aria-label="<?php esc_attr_e('Phone verified', 'club-anketa'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </span>
            <?php endif; ?>
        </div>
        <input type="hidden" name="otp_verification_token" value="" class="otp-verification-token" />
        <?php
    }

    /**
     * Add phone verification UI to account edit address page
     */
    public function add_account_phone_verification() {
        // Output verification container for account phone
        $this->add_phone_verification_button();
        
        // Ensure verification token field exists in the form
        ?>
        <script>
        // Ensure verification token field exists in the form
        (function() {
            var form = document.querySelector('form.woocommerce-EditAccountForm, form.edit-address');
            if (form && !form.querySelector('.otp-verification-token')) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'otp_verification_token';
                input.value = '';
                input.className = 'otp-verification-token';
                form.appendChild(input);
            }
        })();
        </script>
        <?php
    }

    /**
     * Render SMS consent fields at checkout
     */
    public function checkout_sms_consent() {
        // Skip if user already has SMS consent
        if (Utils::user_has_sms_consent()) {
            return;
        }

        $this->render_sms_consent_fields('checkout');
    }

    /**
     * Render SMS consent fields on account page
     */
    public function account_sms_consent() {
        $user_id = get_current_user_id();
        $current_consent = get_user_meta($user_id, '_sms_consent', true);
        $current_call_consent = get_user_meta($user_id, '_call_consent', true);

        $this->render_sms_consent_fields('account', $current_consent, $current_call_consent);
    }

    /**
     * Save account SMS consent
     *
     * @param int $user_id User ID
     */
    public function save_account_sms_consent($user_id) {
        if (!isset($_POST['anketa_sms_consent'])) {
            return;
        }

        $new_consent = sanitize_text_field(wp_unslash($_POST['anketa_sms_consent']));
        $old_consent = get_user_meta($user_id, '_sms_consent', true);

        // Get current phone number
        $phone = get_user_meta($user_id, 'billing_phone', true);
        $phone_digits = Utils::normalize_phone($phone);

        // If changing from "no" to "yes", require OTP verification
        if ($new_consent === 'yes' && $old_consent !== 'yes') {
            if (!Utils::is_phone_verified($phone_digits)) {
                // Don't update, verification required
                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('Phone verification required to enable SMS notifications.', 'club-anketa'), 'error');
                }
                return;
            }
            // Update the verified phone number
            update_user_meta($user_id, '_verified_phone_number', $phone_digits);
        }

        // If consent is "no", clear the verified phone number
        if ($new_consent === 'no') {
            delete_user_meta($user_id, '_verified_phone_number');
        }

        update_user_meta($user_id, '_sms_consent', $new_consent);

        // Handle Call Consent
        if (isset($_POST['anketa_call_consent'])) {
            $call_consent = sanitize_text_field(wp_unslash($_POST['anketa_call_consent']));
            if ($call_consent !== 'yes' && $call_consent !== 'no') {
                $call_consent = 'yes';
            }
            update_user_meta($user_id, '_call_consent', $call_consent);
        }
    }

    /**
     * Validate phone verification on checkout
     */
    public function validate_checkout_phone_verification() {
        if (!isset($_POST['billing_phone'])) {
            return;
        }

        $phone = sanitize_text_field(wp_unslash($_POST['billing_phone']));
        $phone_digits = Utils::normalize_phone($phone);

        if (strlen($phone_digits) !== 9) {
            return; // Invalid phone, WooCommerce will handle validation
        }

        // Check if user is logged in and phone matches verified phone
        if (is_user_logged_in()) {
            $verified_phone = Utils::get_user_verified_phone(get_current_user_id());
            if ($phone_digits === $verified_phone) {
                return; // Phone already verified for this user
            }
        }

        // Check for OTP verification token
        if (!Utils::is_phone_verified($phone_digits)) {
            wc_add_notice(__('Phone verification required. Please verify your phone number before placing the order.', 'club-anketa'), 'error');
        }
    }

    /**
     * Validate phone verification on account details save
     *
     * @param \WP_Error $errors WP_Error object
     */
    public function validate_account_phone_verification($errors) {
        if (!isset($_POST['billing_phone'])) {
            return;
        }

        $phone = sanitize_text_field(wp_unslash($_POST['billing_phone']));
        $phone_digits = Utils::normalize_phone($phone);

        if (strlen($phone_digits) !== 9) {
            return; // Invalid phone format will be handled by WooCommerce
        }

        $user_id = get_current_user_id();
        $verified_phone = Utils::get_user_verified_phone($user_id);

        // If phone number changed, require verification
        if ($phone_digits !== $verified_phone) {
            if (!Utils::is_phone_verified($phone_digits)) {
                $errors->add('phone_verification', __('Phone verification required. Please verify your new phone number.', 'club-anketa'));
            } else {
                // Update verified phone number after successful verification
                update_user_meta($user_id, '_verified_phone_number', $phone_digits);
            }
        }
    }

    /**
     * Render SMS and Call consent fields
     *
     * @param string $context            Context (registration, checkout, account)
     * @param string $current_value      Current SMS consent value
     * @param string $current_call_value Current call consent value
     */
    private function render_sms_consent_fields($context = 'registration', $current_value = 'yes', $current_call_value = 'yes') {
        ?>
        <div class="club-anketa-sms-consent" data-context="<?php echo esc_attr($context); ?>">
            <div class="row">
                <span class="label"><?php esc_html_e('SMS შეტყობინებების მიღების თანხმობა', 'club-anketa'); ?></span>
                <div class="field sms-consent-options">
                    <label style="margin-right:12px;">
                        <input type="radio" name="anketa_sms_consent" value="yes" <?php checked($current_value, 'yes'); ?> class="sms-consent-radio" />
                        <?php esc_html_e('დიახ', 'club-anketa'); ?>
                    </label>
                    <label>
                        <input type="radio" name="anketa_sms_consent" value="no" <?php checked($current_value, 'no'); ?> class="sms-consent-radio" />
                        <?php esc_html_e('არა', 'club-anketa'); ?>
                    </label>
                </div>
            </div>
            <div class="row">
                <span class="label"><?php esc_html_e('თანხმობა სატელეფონო ზარზე', 'club-anketa'); ?></span>
                <div class="field sms-consent-options">
                    <label style="margin-right:12px;">
                        <input type="radio" name="anketa_call_consent" value="yes" <?php checked($current_call_value, 'yes'); ?> class="call-consent-radio" />
                        <?php esc_html_e('დიახ', 'club-anketa'); ?>
                    </label>
                    <label>
                        <input type="radio" name="anketa_call_consent" value="no" <?php checked($current_call_value, 'no'); ?> class="call-consent-radio" />
                        <?php esc_html_e('არა', 'club-anketa'); ?>
                    </label>
                </div>
            </div>
            <input type="hidden" name="otp_verification_token" value="" class="otp-verification-token" />
        </div>
        <?php
    }
}
