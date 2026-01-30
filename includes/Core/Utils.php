<?php
/**
 * Utils Class - Static helper methods for phone normalization and verification
 *
 * @package ClubAnketa\Core
 */

namespace ClubAnketa\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Utils {

    /**
     * Rate limiting constants
     */
    const OTP_MAX_ATTEMPTS = 3;
    const OTP_RATE_LIMIT_MINUTES = 10;
    const OTP_EXPIRY_SECONDS = 300;

    /**
     * Normalize phone number to 9-digit local format
     *
     * @param string $phone Raw phone number
     * @return string Normalized 9-digit phone number
     */
    public static function normalize_phone($phone) {
        if (empty($phone)) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone);

        // If it includes country code (995), extract local part
        if (strlen($digits) > 9 && strpos($digits, '995') === 0) {
            $digits = substr($digits, 3);
        }

        // Take only last 9 digits
        if (strlen($digits) > 9) {
            $digits = substr($digits, -9);
        }

        return $digits;
    }

    /**
     * Format phone number for display with country code
     *
     * @param string $phone Raw phone number
     * @return string Formatted phone number (+995 XXXXXXXXX)
     */
    public static function format_phone($phone) {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        
        if (preg_match('/^995(\d{9})$/', $digits, $m)) {
            return '+995 ' . $m[1];
        }
        
        if (preg_match('/^\d{9}$/', $digits)) {
            return '+995 ' . $digits;
        }
        
        $raw = trim((string) $phone);
        return $raw !== '' ? $raw : '';
    }

    /**
     * Check if phone is verified via OTP token
     *
     * @param string $phone Phone number to check
     * @return bool Whether phone is verified
     */
    public static function is_phone_verified($phone) {
        $phone_digits = self::normalize_phone($phone);
        
        if (strlen($phone_digits) !== 9) {
            return false;
        }
        
        $verify_key = 'otp_verified_' . $phone_digits;
        $token = isset($_POST['otp_verification_token']) ? sanitize_text_field(wp_unslash($_POST['otp_verification_token'])) : '';

        if (empty($token)) {
            return false;
        }

        $stored_token = get_transient($verify_key);
        return $stored_token !== false && $stored_token === $token;
    }

    /**
     * Get user's verified phone number (9-digit local format)
     *
     * @param int $user_id User ID
     * @return string Verified phone number or empty string
     */
    public static function get_user_verified_phone($user_id) {
        $verified_phone = get_user_meta($user_id, '_verified_phone_number', true);
        
        if ($verified_phone) {
            $verified_phone = self::normalize_phone($verified_phone);
        }
        
        return $verified_phone ?: '';
    }

    /**
     * Check if user already has SMS consent
     *
     * @param int|null $user_id User ID (optional, uses current user if not provided)
     * @return bool Whether user has SMS consent
     */
    public static function user_has_sms_consent($user_id = null) {
        if (!$user_id && is_user_logged_in()) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        $consent = get_user_meta($user_id, '_sms_consent', true);
        return $consent === 'yes';
    }

    /**
     * Sanitize input value by type
     *
     * @param mixed  $value Raw input value
     * @param string $type  Type of sanitization (text, email, date, tel)
     * @return string Sanitized value
     */
    public static function sanitize_by_type($value, $type) {
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

    /**
     * Get client IP address
     *
     * Note: For security-critical rate limiting, we prioritize REMOTE_ADDR 
     * over proxy headers which can be spoofed. Proxy headers are only used
     * as a fallback when REMOTE_ADDR is not available.
     *
     * @return string Client IP address
     */
    public static function get_client_ip() {
        // Prefer REMOTE_ADDR for security (cannot be easily spoofed)
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
            // Validate it's a proper IP address
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        
        // Fallback to proxy headers only if REMOTE_ADDR is unavailable
        // Note: These can be spoofed, so use with caution
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Take only the first IP if multiple are present
            $forwarded = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
            $ips = explode(',', $forwarded);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        
        return '0.0.0.0'; // Fallback if no valid IP found
    }

    /**
     * Get rate limit key for phone/IP combination
     *
     * @param string $phone Phone number
     * @return string Rate limit key
     */
    public static function get_rate_limit_key($phone) {
        $ip = self::get_client_ip();
        return 'otp_rate_' . md5($phone . $ip);
    }

    /**
     * Check if rate limit has been exceeded
     *
     * @param string $phone Phone number
     * @return bool Whether rate limit is OK (true = can send, false = exceeded)
     */
    public static function check_rate_limit($phone) {
        $key = self::get_rate_limit_key($phone);
        $attempts = get_transient($key);

        if ($attempts === false) {
            return true; // No previous attempts
        }

        return (int) $attempts < self::OTP_MAX_ATTEMPTS;
    }

    /**
     * Increment rate limit counter
     *
     * @param string $phone Phone number
     */
    public static function increment_rate_limit($phone) {
        $key = self::get_rate_limit_key($phone);
        $attempts = get_transient($key);

        if ($attempts === false) {
            set_transient($key, 1, self::OTP_RATE_LIMIT_MINUTES * MINUTE_IN_SECONDS);
        } else {
            set_transient($key, (int) $attempts + 1, self::OTP_RATE_LIMIT_MINUTES * MINUTE_IN_SECONDS);
        }
    }

    /**
     * Generate a 6-digit OTP code
     *
     * @return string 6-digit OTP code
     */
    public static function generate_otp() {
        // Use random_int for cryptographically secure OTP generation
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Custom sanitizer allowing richer HTML for terms content
     *
     * @param string $html Raw HTML content
     * @return string Sanitized HTML
     */
    public static function sanitize_terms_html($html) {
        if (!is_string($html)) {
            return '';
        }

        $allowed = [
            'div'   => ['class' => true, 'id' => true, 'style' => true, 'data-*' => true],
            'span'  => ['class' => true, 'id' => true, 'style' => true, 'data-*' => true],
            'p'     => ['class' => true, 'id' => true, 'style' => true, 'data-*' => true],
            'br'    => [],
            'hr'    => ['class' => true, 'id' => true, 'style' => true],
            'strong'=> ['class' => true, 'id' => true, 'style' => true],
            'em'    => ['class' => true, 'id' => true, 'style' => true],
            'b'     => ['class' => true, 'id' => true, 'style' => true],
            'i'     => ['class' => true, 'id' => true, 'style' => true],
            'u'     => ['class' => true, 'id' => true, 'style' => true],
            'small' => ['class' => true, 'id' => true, 'style' => true],
            'mark'  => ['class' => true, 'id' => true, 'style' => true],
            'sub'   => ['class' => true, 'id' => true, 'style' => true],
            'sup'   => ['class' => true, 'id' => true, 'style' => true],
            'code'  => ['class' => true, 'id' => true, 'style' => true],
            'pre'   => ['class' => true, 'id' => true, 'style' => true],
            'h1'    => ['class' => true, 'id' => true, 'style' => true],
            'h2'    => ['class' => true, 'id' => true, 'style' => true],
            'h3'    => ['class' => true, 'id' => true, 'style' => true],
            'h4'    => ['class' => true, 'id' => true, 'style' => true],
            'h5'    => ['class' => true, 'id' => true, 'style' => true],
            'h6'    => ['class' => true, 'id' => true, 'style' => true],
            'ul'    => ['class' => true, 'id' => true, 'style' => true],
            'ol'    => ['class' => true, 'id' => true, 'style' => true],
            'li'    => ['class' => true, 'id' => true, 'style' => true],
            'table' => ['class' => true, 'id' => true, 'style' => true, 'data-*' => true],
            'thead' => ['class' => true, 'id' => true, 'style' => true],
            'tbody' => ['class' => true, 'id' => true, 'style' => true],
            'tr'    => ['class' => true, 'id' => true, 'style' => true],
            'td'    => ['class' => true, 'id' => true, 'style' => true, 'colspan' => true, 'rowspan' => true, 'data-*' => true],
            'th'    => ['class' => true, 'id' => true, 'style' => true, 'colspan' => true, 'rowspan' => true, 'data-*' => true],
            'a'     => ['class' => true, 'id' => true, 'style' => true, 'href' => true, 'title' => true, 'target' => true, 'rel' => true, 'data-*' => true],
            'img'   => ['class' => true, 'id' => true, 'style' => true, 'src' => true, 'alt' => true, 'title' => true, 'width' => true, 'height' => true, 'loading' => true, 'data-*' => true],
        ];

        $expanded_allowed = [];
        foreach ($allowed as $tag => $attrs) {
            $expanded_attrs = [];
            foreach ($attrs as $attr => $v) {
                if ($attr === 'data-*') {
                    $expanded_attrs['data-title']   = true;
                    $expanded_attrs['data-name']    = true;
                    $expanded_attrs['data-id']      = true;
                    $expanded_attrs['data-value']   = true;
                    $expanded_attrs['data-type']    = true;
                    $expanded_attrs['data-state']   = true;
                    $expanded_attrs['data-extra']   = true;
                    $expanded_attrs['data-label']   = true;
                    $expanded_attrs['data-key']     = true;
                    $expanded_attrs['data-role']    = true;
                    $expanded_attrs['data-index']   = true;
                    $expanded_attrs['data-active']  = true;
                    $expanded_attrs['data-toggle']  = true;
                    $expanded_attrs['data-color']   = true;
                } else {
                    $expanded_attrs[$attr] = true;
                }
            }
            $expanded_allowed[$tag] = $expanded_attrs;
        }

        return wp_kses($html, $expanded_allowed);
    }

    /**
     * Prepare terms HTML for output (auto-paragraph if needed)
     *
     * @param string $html Raw HTML content
     * @return string Prepared HTML
     */
    public static function prepare_terms_html($html) {
        if (!is_string($html) || $html === '') {
            return '';
        }
        
        // Detect if content already has block-level tags
        if (preg_match('/<(p|div|ul|ol|li|table|thead|tbody|tr|td|th|h[1-6])\b/i', $html)) {
            return $html;
        }
        
        // Otherwise attempt to create paragraphs
        return wpautop($html);
    }

    /**
     * Get default rules text for Anketa
     * 
     * This centralized method ensures consistency across all templates.
     *
     * @return string Default rules HTML
     */
    public static function get_default_rules_text() {
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

        // Allow filtering of default rules
        return apply_filters('club_anketa_rules_text', $rules);
    }
}
