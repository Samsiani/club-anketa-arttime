<?php
/**
 * OTP Modal Template
 *
 * This modal is injected via wp_footer to ensure it's a direct child of <body>,
 * solving z-index and overflow issues with themes like WoodMart.
 *
 * @package ClubAnketa
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- Club Anketa OTP Verification Modal -->
<!-- Injected via wp_footer to ensure it's outside any restrictive theme containers -->
<div id="club-anketa-otp-modal" class="club-anketa-modal">
    <div class="club-anketa-modal-overlay"></div>
    <div class="club-anketa-modal-content">
        <button type="button" class="club-anketa-modal-close">&times;</button>
        <div class="club-anketa-modal-header">
            <h3><?php esc_html_e('ტელეფონის ვერიფიკაცია', 'club-anketa'); ?></h3>
            <p class="modal-subtitle">
                <?php esc_html_e('SMS კოდი გამოგზავნილია ნომერზე:', 'club-anketa'); ?>
                <span class="otp-phone-display"></span>
            </p>
        </div>
        <div class="club-anketa-modal-body">
            <div class="otp-input-container">
                <input type="text" class="otp-digit" maxlength="1" data-index="0" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" />
                <input type="text" class="otp-digit" maxlength="1" data-index="1" inputmode="numeric" pattern="[0-9]" />
                <input type="text" class="otp-digit" maxlength="1" data-index="2" inputmode="numeric" pattern="[0-9]" />
                <input type="text" class="otp-digit" maxlength="1" data-index="3" inputmode="numeric" pattern="[0-9]" />
                <input type="text" class="otp-digit" maxlength="1" data-index="4" inputmode="numeric" pattern="[0-9]" />
                <input type="text" class="otp-digit" maxlength="1" data-index="5" inputmode="numeric" pattern="[0-9]" />
            </div>
            <div class="otp-message"></div>
            <div class="otp-resend-container">
                <span class="otp-countdown" style="display:none;">
                    <?php esc_html_e('ხელახლა გაგზავნა:', 'club-anketa'); ?>
                    <span class="countdown-timer">60</span>
                    <?php esc_html_e('წამი', 'club-anketa'); ?>
                </span>
                <button type="button" class="otp-resend-btn" style="display:none;">
                    <?php esc_html_e('ხელახლა გაგზავნა', 'club-anketa'); ?>
                </button>
            </div>
        </div>
        <div class="club-anketa-modal-footer">
            <button type="button" class="otp-verify-btn"><?php esc_html_e('დადასტურება', 'club-anketa'); ?></button>
        </div>
    </div>
</div>
