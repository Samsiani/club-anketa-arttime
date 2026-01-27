/**
 * SMS OTP Verification Script
 * Club Anketa Registration for WooCommerce
 * Version: 2.0.0
 * 
 * Implements inline phone verification with:
 * - Verify button next to phone field
 * - Real-time verification status tracking
 * - Reset on phone number change
 * - Form submission blocking until verified
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
    var verifiedPhone = clubAnketaSms.verifiedPhone || '';
    var sessionVerifiedPhone = ''; // Phone verified in current session (not yet saved)
    var verificationToken = '';
    var countdownInterval = null;
    var resendCountdown = 60;
    var pendingFormSubmit = false;

    /**
     * Initialize the SMS verification system
     */
    function init() {
        // Inject OTP Modal HTML
        injectModalHtml();

        // Inject verify button for WooCommerce fields that don't have it
        injectVerifyButtonForWooCommerce();

        // Bind events
        bindEvents();

        // Initialize phone field states
        initializePhoneFields();

        // Update submit button states
        updateSubmitButtonStates();
    }

    /**
     * Inject verify button for WooCommerce billing phone field
     */
    function injectVerifyButtonForWooCommerce() {
        // Check for WooCommerce billing phone field without verify button
        var $billingPhone = $('#billing_phone');
        if ($billingPhone.length > 0 && !$billingPhone.closest('.phone-verify-group').length) {
            // Wrap the phone input if needed
            var $wrapper = $billingPhone.parent();
            if (!$wrapper.hasClass('phone-verify-group')) {
                $billingPhone.wrap('<div class="phone-verify-group wc-phone-verify-group"></div>');
            }
            
            // Add verify container after the input
            if (!$billingPhone.siblings('.phone-verify-container').length) {
                var verifyHtml = '<div class="phone-verify-container">' +
                    '<button type="button" class="phone-verify-btn" aria-label="' + i18n.verify + '">' + 
                    (i18n.verifyBtn || 'Verify') + '</button>' +
                    '<span class="phone-verified-icon" style="display:none;" aria-label="' + i18n.verified + '">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>' +
                    '</span></div>';
                $billingPhone.after(verifyHtml);
            }
        }
    }

    /**
     * Inject the OTP Modal HTML into the page
     */
    function injectModalHtml() {
        if ($('#club-anketa-otp-modal').length > 0) {
            return; // Modal already exists
        }

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
     * Initialize phone field states on page load
     */
    function initializePhoneFields() {
        // Check each phone field and set initial state
        $('.phone-local, #billing_phone, #anketa_phone_local').each(function() {
            var $input = $(this);
            var currentPhone = normalizePhone($input.val());
            updatePhoneFieldState($input, currentPhone);
        });
    }

    /**
     * Normalize phone number to 9-digit format
     */
    function normalizePhone(phone) {
        if (!phone) return '';
        
        var digits = phone.replace(/\D/g, '');
        
        // If it includes country code, extract local part
        if (digits.length > 9 && digits.indexOf('995') === 0) {
            digits = digits.substring(3);
        }
        
        // Take only last 9 digits
        if (digits.length > 9) {
            digits = digits.substring(digits.length - 9);
        }
        
        return digits;
    }

    /**
     * Check if a phone number is verified
     */
    function isPhoneVerified(phone) {
        var normalizedPhone = normalizePhone(phone);
        if (!normalizedPhone || normalizedPhone.length !== 9) {
            return false;
        }
        
        // Check against stored verified phone or session verified phone
        return normalizedPhone === verifiedPhone || normalizedPhone === sessionVerifiedPhone;
    }

    /**
     * Update the visual state of a phone field (button vs checkmark)
     */
    function updatePhoneFieldState($input, currentPhone) {
        var $container = $input.closest('.phone-verify-group, .phone-group');
        var $verifyBtn = $container.find('.phone-verify-btn');
        var $verifiedIcon = $container.find('.phone-verified-icon');
        
        if ($verifyBtn.length === 0 && $verifiedIcon.length === 0) {
            return; // No verify UI for this field
        }

        var normalizedPhone = normalizePhone(currentPhone);
        var phoneValid = normalizedPhone.length === 9;
        var phoneVerified = isPhoneVerified(normalizedPhone);

        if (phoneVerified && phoneValid) {
            // State 2: Verified - show green checkmark
            $verifyBtn.hide();
            $verifiedIcon.show();
            $container.addClass('phone-verified').removeClass('phone-unverified');
        } else if (phoneValid) {
            // State 1: Unverified but valid - show verify button
            $verifyBtn.show();
            $verifiedIcon.hide();
            $container.addClass('phone-unverified').removeClass('phone-verified');
        } else {
            // Invalid/empty - hide both
            $verifyBtn.hide();
            $verifiedIcon.hide();
            $container.removeClass('phone-verified phone-unverified');
        }

        // Update submit button states
        updateSubmitButtonStates();
    }

    /**
     * Update submit button enabled/disabled states based on verification
     */
    function updateSubmitButtonStates() {
        // Find all forms with phone verification
        $('.club-anketa-form, form.checkout, form.woocommerce-EditAccountForm').each(function() {
            var $form = $(this);
            var $phoneInput = $form.find('#anketa_phone_local, .phone-local, #billing_phone').first();
            var $submitBtn = $form.find('.submit-btn, button[type="submit"], input[type="submit"]').not('.phone-verify-btn, .otp-verify-btn, .otp-resend-btn');
            
            if ($phoneInput.length === 0 || $submitBtn.length === 0) {
                return;
            }

            var currentPhone = normalizePhone($phoneInput.val());
            var phoneValid = currentPhone.length === 9;
            var phoneVerified = isPhoneVerified(currentPhone);

            // Check if phone field is required (registration form always requires verification)
            var isRegistrationForm = $form.hasClass('club-anketa-form');
            var requiresVerification = isRegistrationForm || $form.find('.phone-verify-group').length > 0;

            if (requiresVerification && phoneValid && !phoneVerified) {
                // Phone is filled but not verified - disable submit
                $submitBtn.prop('disabled', true).addClass('verification-blocked');
                
                // Add or update warning message
                if (!$form.find('.phone-verify-warning').length) {
                    $submitBtn.before('<p class="phone-verify-warning">' + (i18n.verificationRequired || 'Phone verification required') + '</p>');
                }
            } else {
                // Enable submit (either verified or not required)
                $submitBtn.prop('disabled', false).removeClass('verification-blocked');
                $form.find('.phone-verify-warning').remove();
            }
        });
    }

    /**
     * Bind all event handlers
     */
    function bindEvents() {
        // Phone input change - real-time monitoring
        $(document).on('input change', '.phone-local, #billing_phone, #anketa_phone_local', function() {
            var $input = $(this);
            var currentPhone = $input.val();
            updatePhoneFieldState($input, currentPhone);
        });

        // Verify button click
        $(document).on('click', '.phone-verify-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $container = $btn.closest('.phone-verify-group, .phone-group');
            var $input = $container.find('.phone-local, #billing_phone, #anketa_phone_local, input[type="tel"]').first();
            
            var phone = normalizePhone($input.val());
            
            if (!phone || phone.length !== 9) {
                alert(i18n.phoneRequired);
                $input.focus();
                return;
            }

            // Disable button and show loading
            $btn.prop('disabled', true).addClass('loading');

            // Send OTP
            sendOtp(phone, function() {
                $btn.prop('disabled', false).removeClass('loading');
                openModal(phone);
            }, function() {
                $btn.prop('disabled', false).removeClass('loading');
            });
        });

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
        var $phoneInput = $form.find('#anketa_phone_local, .phone-local, #billing_phone').first();
        
        if ($phoneInput.length === 0) {
            return true; // No phone field, allow submission
        }

        var currentPhone = normalizePhone($phoneInput.val());
        
        // Check if phone is filled but not verified
        var isRegistrationForm = $form.hasClass('club-anketa-form');
        var requiresVerification = isRegistrationForm || $form.find('.phone-verify-group').length > 0;

        if (requiresVerification && currentPhone.length === 9 && !isPhoneVerified(currentPhone)) {
            e.preventDefault();
            e.stopPropagation();

            // Highlight the verify button
            var $verifyBtn = $form.find('.phone-verify-btn');
            if ($verifyBtn.length > 0 && $verifyBtn.is(':visible')) {
                $verifyBtn.addClass('highlight-pulse');
                setTimeout(function() {
                    $verifyBtn.removeClass('highlight-pulse');
                }, 2000);
            }

            alert(i18n.verificationRequired || 'Please verify your phone number before submitting.');
            return false;
        }

        // Update verification token in form
        updateVerificationToken(verificationToken);

        return true;
    }

    /**
     * Send OTP via AJAX
     */
    function sendOtp(phone, successCallback, errorCallback) {
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
                    if (typeof errorCallback === 'function') {
                        errorCallback();
                    }
                }
            },
            error: function() {
                showMessage(i18n.error, 'error');
                if (typeof errorCallback === 'function') {
                    errorCallback();
                }
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
                    // Update session verified phone
                    sessionVerifiedPhone = response.data.verifiedPhone || phone;
                    verificationToken = response.data.token;
                    
                    // Update stored verified phone if returned
                    if (response.data.verifiedPhone) {
                        verifiedPhone = response.data.verifiedPhone;
                    }

                    updateVerificationToken(verificationToken);
                    showMessage(i18n.verified, 'success');
                    
                    // Update all phone field states
                    $('.phone-local, #billing_phone, #anketa_phone_local').each(function() {
                        updatePhoneFieldState($(this), $(this).val());
                    });

                    // Close modal after a short delay
                    setTimeout(function() {
                        closeModal();
                    }, 800);
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
        
        // Also add to any form that needs it
        $('form.club-anketa-form, form.checkout, form.woocommerce-EditAccountForm').each(function() {
            var $form = $(this);
            if (!$form.find('.otp-verification-token').length) {
                $form.append('<input type="hidden" name="otp_verification_token" value="' + token + '" class="otp-verification-token" />');
            } else {
                $form.find('.otp-verification-token').val(token);
            }
        });
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
        $('.otp-message').empty().removeClass('success error info');
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
