<?php
/**
 * Registration Form Template
 *
 * This template renders the club anketa registration form.
 * Available variables: $errors, $old, $v(), $sms_old, $call_old
 *
 * @package ClubAnketa
 */

if (!defined('ABSPATH')) {
    exit;
}
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
        
        <!-- Honeypot field -->
        <div class="club-anketa-hp">
            <label for="anketa_security_field">Leave this empty</label>
            <input type="text" id="anketa_security_field" name="anketa_security_field" value="" autocomplete="off" />
        </div>

        <div class="row">
            <label class="label" for="anketa_personal_id"><?php esc_html_e('პირადი ნომერი *', 'club-anketa'); ?></label>
            <div class="field">
                <input type="text" id="anketa_personal_id" name="anketa_personal_id" required value="<?php echo $v('anketa_personal_id'); ?>" />
            </div>
        </div>

        <div class="row">
            <label class="label" for="anketa_first_name"><?php esc_html_e('სახელი *', 'club-anketa'); ?></label>
            <div class="field">
                <input type="text" id="anketa_first_name" name="anketa_first_name" required value="<?php echo $v('anketa_first_name'); ?>" />
            </div>
        </div>

        <div class="row">
            <label class="label" for="anketa_last_name"><?php esc_html_e('გვარი *', 'club-anketa'); ?></label>
            <div class="field">
                <input type="text" id="anketa_last_name" name="anketa_last_name" required value="<?php echo $v('anketa_last_name'); ?>" />
            </div>
        </div>

        <div class="row">
            <label class="label" for="anketa_dob"><?php esc_html_e('დაბადების თარიღი *', 'club-anketa'); ?></label>
            <div class="field">
                <input type="date" id="anketa_dob" name="anketa_dob" required value="<?php echo $v('anketa_dob'); ?>" />
            </div>
        </div>

        <div class="row">
            <label class="label"><?php esc_html_e('ტელეფონის ნომერი *', 'club-anketa'); ?></label>
            <div class="field">
                <div class="phone-group phone-verify-group">
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
                        data-phone-field="anketa"
                    />
                    <div class="phone-verify-container" data-target="#anketa_phone_local">
                        <button type="button" class="phone-verify-btn" aria-label="<?php esc_attr_e('Verify phone number', 'club-anketa'); ?>">
                            <?php esc_html_e('Verify', 'club-anketa'); ?>
                        </button>
                        <span class="phone-verified-icon" style="display:none;" aria-label="<?php esc_attr_e('Phone verified', 'club-anketa'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </span>
                    </div>
                </div>
                <small id="phoneHelp" class="help-text"><?php esc_html_e('9-ციფრიანი ნომერი, მაგალითად 599620303', 'club-anketa'); ?></small>
            </div>
        </div>

        <div class="row">
            <label class="label" for="anketa_address"><?php esc_html_e('მისამართი', 'club-anketa'); ?></label>
            <div class="field">
                <input type="text" id="anketa_address" name="anketa_address" value="<?php echo $v('anketa_address'); ?>" />
            </div>
        </div>

        <div class="row">
            <label class="label" for="anketa_email"><?php esc_html_e('E-mail *', 'club-anketa'); ?></label>
            <div class="field">
                <input type="email" id="anketa_email" name="anketa_email" required value="<?php echo $v('anketa_email'); ?>" />
            </div>
        </div>

        <div class="rules-wrap">
            <div class="rules-title"><?php esc_html_e('წესები და პირობები', 'club-anketa'); ?></div>
            <div class="rules-text">
                <?php echo wp_kses_post(\ClubAnketa\Core\Utils::get_default_rules_text()); ?>
            </div>
        </div>

        <div class="row">
            <label class="label" for="anketa_card_no"><?php esc_html_e('მივიღე ბარათი №', 'club-anketa'); ?></label>
            <div class="field">
                <input type="text" id="anketa_card_no" name="anketa_card_no" value="<?php echo $v('anketa_card_no'); ?>" />
            </div>
        </div>

        <!-- SMS consent -->
        <div class="row club-anketa-sms-consent" data-context="registration">
            <span class="label"><?php esc_html_e('SMS შეტყობინებების მიღების თანხმობა', 'club-anketa'); ?></span>
            <div class="field sms-consent-options">
                <label style="margin-right:12px;">
                    <input type="radio" name="anketa_sms_consent" value="yes" <?php checked($sms_old, 'yes'); ?> class="sms-consent-radio" />
                    <?php esc_html_e('დიახ', 'club-anketa'); ?>
                </label>
                <label>
                    <input type="radio" name="anketa_sms_consent" value="no" <?php checked($sms_old, 'no'); ?> class="sms-consent-radio" />
                    <?php esc_html_e('არა', 'club-anketa'); ?>
                </label>
            </div>
            <input type="hidden" name="otp_verification_token" value="" class="otp-verification-token" />
        </div>

        <!-- Call consent -->
        <div class="row club-anketa-sms-consent" data-context="registration">
            <span class="label"><?php esc_html_e('თანხმობა სატელეფონო ზარზე', 'club-anketa'); ?></span>
            <div class="field sms-consent-options">
                <label style="margin-right:12px;">
                    <input type="radio" name="anketa_call_consent" value="yes" <?php checked($call_old, 'yes'); ?> class="call-consent-radio" />
                    <?php esc_html_e('დიახ', 'club-anketa'); ?>
                </label>
                <label>
                    <input type="radio" name="anketa_call_consent" value="no" <?php checked($call_old, 'no'); ?> class="call-consent-radio" />
                    <?php esc_html_e('არა', 'club-anketa'); ?>
                </label>
            </div>
        </div>

        <div class="row">
            <label class="label" for="anketa_responsible_person"><?php esc_html_e('პასუხისმგებელი პირი', 'club-anketa'); ?></label>
            <div class="field">
                <input type="text" id="anketa_responsible_person" name="anketa_responsible_person" value="<?php echo $v('anketa_responsible_person'); ?>" />
            </div>
        </div>

        <div class="row">
            <label class="label" for="anketa_form_date"><?php esc_html_e('თარიღი', 'club-anketa'); ?></label>
            <div class="field">
                <input type="date" id="anketa_form_date" name="anketa_form_date" value="<?php echo $v('anketa_form_date'); ?>" />
            </div>
        </div>

        <div class="row">
            <label class="label" for="anketa_shop"><?php esc_html_e('მაღაზია', 'club-anketa'); ?></label>
            <div class="field">
                <input type="text" id="anketa_shop" name="anketa_shop" value="<?php echo $v('anketa_shop'); ?>" />
            </div>
        </div>

        <div class="submit-row">
            <button type="submit" class="submit-btn"><?php esc_html_e('რეგისტრაცია', 'club-anketa'); ?></button>
        </div>
    </form>
</div>
