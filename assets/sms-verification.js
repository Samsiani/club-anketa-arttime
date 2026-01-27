/**
 * SMS OTP Verification Script
 * Club Anketa Registration for WooCommerce
 * Version: 1.5.0
 */
(function($) {
    'use strict';

    // Exit if clubAnketaSms is not defined
    if (typeof clubAnketaSms === 'undefined') {
        return;
    }

    var i18n = clubAnketaSms.i18n;
    var ajaxUrl = clubAnketaSms.ajaxUrl;
    var nonce = clubAnketaSms.nonce;

    // State variables
    var isVerified = false;
    var verificationToken = '';
    var countdownInterval = null;
    var resendCountdown = 60;

    /**
     * Initialize the SMS verification system
     */
    function init() {
        // Inject OTP Modal HTML
        injectModalHtml();

        // Bind events
        bindEvents();
    }

    /**
     * Inject the OTP Modal HTML into the page
     */
    function injectModalHtml() {
        var modalHtml = '<div id="club-anketa-otp-modal" class="club-anketa-modal" style="display:none;">' +
            '<div class="club-anketa-modal-overlay"></div>' +
            '<div class="club-anketa-modal-content">' +
            '<button type="button" class="club-anketa-modal-close">&times;</button>' +
            '<div class="club-anketa-modal-header">' +
            '<h3>' + i18n.modalTitle + '</h3>' +
            '<p class="modal-subtitle">' + i18n.modalSubtitle + ' <span class="otp-phone-display"></span></p>' +
            '</div>' +
            '<div class="club-anketa-modal-body">' +
            '<div class="otp-input-container">' +
            '<input type="text" class="otp-digit" maxlength="1" data-index="0" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" />' +
            '<input type="text" class="otp-digit" maxlength="1" data-index="1" inputmode="numeric" pattern="[0-9]" />' +
            '<input type="text" class="otp-digit" maxlength="1" data-index="2" inputmode="numeric" pattern="[0-9]" />' +
            '<input type="text" class="otp-digit" maxlength="1" data-index="3" inputmode="numeric" pattern="[0-9]" />' +
            '<input type="text" class="otp-digit" maxlength="1" data-index="4" inputmode="numeric" pattern="[0-9]" />' +
            '<input type="text" class="otp-digit" maxlength="1" data-index="5" inputmode="numeric" pattern="[0-9]" />' +
            '</div>' +
            '<div class="otp-message"></div>' +
            '<div class="otp-resend-container">' +
            '<span class="otp-countdown" style="display:none;">' + i18n.resendIn + ' <span class="countdown-timer">60</span> წამი</span>' +
            '<button type="button" class="otp-resend-btn" style="display:none;">' + i18n.resend + '</button>' +
            '</div>' +
            '</div>' +
            '<div class="club-anketa-modal-footer">' +
            '<button type="button" class="otp-verify-btn">' + i18n.verify + '</button>' +
            '</div>' +
            '</div>' +
            '</div>';

        $('body').append(modalHtml);
    }

    /**
     * Bind all event handlers
     */
    function bindEvents() {
        // Form submission interception
        $(document).on('submit', '.club-anketa-form, form.checkout, form.woocommerce-EditAccountForm', function(e) {
            return handleFormSubmit(e, $(this));
        });

        // Close modal
        $(document).on('click', '.club-anketa-modal-close, .club-anketa-modal-overlay', closeModal);

        // OTP digit input handling
        $(document).on('input', '.otp-digit', handleOtpInput);
        $(document).on('keydown', '.otp-digit', handleOtpKeydown);
        $(document).on('paste', '.otp-digit', handleOtpPaste);

        // Verify OTP button
        $(document).on('click', '.otp-verify-btn', verifyOtp);

        // Resend OTP button
        $(document).on('click', '.otp-resend-btn', function() {
            var phone = $('.otp-phone-display').data('phone');
            if (phone) {
                sendOtp(phone);
            }
        });

        // Listen for SMS consent changes
        $(document).on('change', '.sms-consent-radio', function() {
            // Reset verification when consent changes
            if ($(this).val() === 'no') {
                isVerified = false;
                verificationToken = '';
                updateVerificationToken('');
            }
        });

        // Close modal on Escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    }

    /**
     * Handle form submission
     */
    function handleFormSubmit(e, $form) {
        var $smsConsentYes = $form.find('.sms-consent-radio[value="yes"]:checked');
        
        // If SMS consent is "Yes" and not yet verified
        if ($smsConsentYes.length > 0 && !isVerified) {
            e.preventDefault();
            e.stopPropagation();

            // Get phone number
            var phone = getPhoneNumber($form);
            
            if (!phone || phone.length !== 9) {
                showMessage(i18n.phoneRequired, 'error');
                return false;
            }

            // Send OTP and show modal
            sendOtp(phone, function() {
                openModal(phone);
            });

            return false;
        }

        // If verified, allow submission
        return true;
    }

    /**
     * Get phone number from form
     */
    function getPhoneNumber($form) {
        var phone = '';
        
        // Try different selectors for phone input
        var $phoneInput = $form.find('#anketa_phone_local, input[name="anketa_phone_local"], #billing_phone, input[name="billing_phone"]');
        
        if ($phoneInput.length > 0) {
            phone = $phoneInput.val();
            // Extract digits only
            phone = phone.replace(/\D/g, '');
            
            // If it includes country code, extract local part
            if (phone.length > 9 && phone.indexOf('995') === 0) {
                phone = phone.substring(3);
            }
            
            // Take only last 9 digits
            if (phone.length > 9) {
                phone = phone.substring(phone.length - 9);
            }
        }

        return phone;
    }

    /**
     * Send OTP via AJAX
     */
    function sendOtp(phone, successCallback) {
        showMessage(i18n.sendingOtp, 'info');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'club_anketa_send_otp',
                nonce: nonce,
                phone: phone
            },
            success: function(response) {
                if (response.success) {
                    showMessage(i18n.enterCode, 'success');
                    startResendCountdown(response.data.expires || 60);
                    if (typeof successCallback === 'function') {
                        successCallback();
                    }
                } else {
                    showMessage(response.data.message || i18n.error, 'error');
                }
            },
            error: function() {
                showMessage(i18n.error, 'error');
            }
        });
    }

    /**
     * Verify OTP via AJAX
     */
    function verifyOtp() {
        var code = getOtpCode();
        var phone = $('.otp-phone-display').data('phone');

        if (code.length !== 6) {
            showMessage(i18n.enterCode, 'error');
            return;
        }

        var $btn = $('.otp-verify-btn');
        $btn.prop('disabled', true).text(i18n.verifying);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'club_anketa_verify_otp',
                nonce: nonce,
                phone: phone,
                code: code
            },
            success: function(response) {
                $btn.prop('disabled', false).text(i18n.verify);

                if (response.success) {
                    isVerified = true;
                    verificationToken = response.data.token;
                    updateVerificationToken(verificationToken);
                    showMessage(i18n.verified, 'success');
                    
                    // Close modal after a short delay
                    setTimeout(function() {
                        closeModal();
                        // Re-submit the form
                        submitVerifiedForm();
                    }, 1000);
                } else {
                    showMessage(response.data.message || i18n.invalidCode, 'error');
                    clearOtpInputs();
                }
            },
            error: function() {
                $btn.prop('disabled', false).text(i18n.verify);
                showMessage(i18n.error, 'error');
            }
        });
    }

    /**
     * Update verification token in hidden field
     */
    function updateVerificationToken(token) {
        $('.otp-verification-token').val(token);
    }

    /**
     * Submit the form after verification
     */
    function submitVerifiedForm() {
        var $form = $('.club-anketa-form, form.checkout, form.woocommerce-EditAccountForm').filter(':visible').first();
        if ($form.length > 0) {
            // For WooCommerce checkout, trigger checkout submission
            if ($form.hasClass('checkout')) {
                $form.trigger('submit');
            } else {
                $form[0].submit();
            }
        }
    }

    /**
     * Get OTP code from inputs
     */
    function getOtpCode() {
        var code = '';
        $('.otp-digit').each(function() {
            code += $(this).val();
        });
        return code;
    }

    /**
     * Clear OTP inputs
     */
    function clearOtpInputs() {
        $('.otp-digit').val('').first().focus();
    }

    /**
     * Handle OTP input
     */
    function handleOtpInput() {
        var $this = $(this);
        var val = $this.val();

        // Only allow digits
        val = val.replace(/\D/g, '');
        if (val.length > 1) {
            val = val.charAt(0);
        }
        $this.val(val);

        // Auto-advance to next input
        if (val.length === 1) {
            var index = parseInt($this.data('index'));
            var $next = $('.otp-digit[data-index="' + (index + 1) + '"]');
            if ($next.length > 0) {
                $next.focus();
            }
        }

        // Check if all digits are filled
        if (getOtpCode().length === 6) {
            verifyOtp();
        }
    }

    /**
     * Handle OTP keydown
     */
    function handleOtpKeydown(e) {
        var $this = $(this);
        var index = parseInt($this.data('index'));

        // Handle backspace
        if (e.key === 'Backspace' && $this.val() === '' && index > 0) {
            var $prev = $('.otp-digit[data-index="' + (index - 1) + '"]');
            $prev.val('').focus();
        }

        // Handle left/right arrow keys
        if (e.key === 'ArrowLeft' && index > 0) {
            $('.otp-digit[data-index="' + (index - 1) + '"]').focus();
        }
        if (e.key === 'ArrowRight' && index < 5) {
            $('.otp-digit[data-index="' + (index + 1) + '"]').focus();
        }
    }

    /**
     * Handle OTP paste
     */
    function handleOtpPaste(e) {
        e.preventDefault();
        var pastedData = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
        var digits = pastedData.replace(/\D/g, '').substring(0, 6);

        for (var i = 0; i < digits.length; i++) {
            $('.otp-digit[data-index="' + i + '"]').val(digits.charAt(i));
        }

        // Focus the next empty input or last input
        var nextEmpty = $('.otp-digit').filter(function() { return !$(this).val(); }).first();
        if (nextEmpty.length > 0) {
            nextEmpty.focus();
        } else {
            $('.otp-digit').last().focus();
        }

        // Auto-verify if 6 digits pasted
        if (digits.length === 6) {
            verifyOtp();
        }
    }

    /**
     * Open OTP modal
     */
    function openModal(phone) {
        var formattedPhone = '+995 ' + phone;
        $('.otp-phone-display').text(formattedPhone).data('phone', phone);
        clearOtpInputs();
        $('#club-anketa-otp-modal').fadeIn(200);
        $('.otp-digit').first().focus();
        $('body').addClass('club-anketa-modal-open');
    }

    /**
     * Close OTP modal
     */
    function closeModal() {
        $('#club-anketa-otp-modal').fadeOut(200);
        $('body').removeClass('club-anketa-modal-open');
        clearCountdown();
    }

    /**
     * Show message in modal
     */
    function showMessage(message, type) {
        var $msgEl = $('.otp-message');
        $msgEl.removeClass('success error info').addClass(type).text(message);
    }

    /**
     * Start resend countdown
     */
    function startResendCountdown(seconds) {
        resendCountdown = seconds || 60;
        $('.otp-resend-btn').hide();
        $('.otp-countdown').show();

        clearCountdown();

        updateCountdownDisplay();
        countdownInterval = setInterval(function() {
            resendCountdown--;
            updateCountdownDisplay();

            if (resendCountdown <= 0) {
                clearCountdown();
                $('.otp-countdown').hide();
                $('.otp-resend-btn').show();
            }
        }, 1000);
    }

    /**
     * Update countdown display
     */
    function updateCountdownDisplay() {
        $('.countdown-timer').text(resendCountdown);
    }

    /**
     * Clear countdown interval
     */
    function clearCountdown() {
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
    }

    // Initialize on document ready
    $(document).ready(init);

})(jQuery);
