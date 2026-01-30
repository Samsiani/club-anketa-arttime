<?php
/**
 * SmsProvider Class - Handles SMS sending via bi.msg.ge API
 *
 * @package ClubAnketa\Api
 */

namespace ClubAnketa\Api;

if (!defined('ABSPATH')) {
    exit;
}

class SmsProvider {

    /**
     * API endpoint
     */
    const API_URL = 'http://bi.msg.ge/sendsms.php';

    /**
     * Get API credentials from options
     *
     * @return array API credentials
     */
    private static function get_credentials() {
        return [
            'username'   => get_option('club_anketa_sms_username', ''),
            'password'   => get_option('club_anketa_sms_password', ''),
            'client_id'  => get_option('club_anketa_sms_client_id', ''),
            'service_id' => get_option('club_anketa_sms_service_id', ''),
        ];
    }

    /**
     * Check if API is configured
     *
     * @return bool Whether API is properly configured
     */
    public static function is_configured() {
        $creds = self::get_credentials();
        return !empty($creds['username']) && 
               !empty($creds['password']) && 
               !empty($creds['client_id']) && 
               !empty($creds['service_id']);
    }

    /**
     * Send SMS message
     *
     * @param string $phone   Phone number (9 digits or international format)
     * @param string $message Message text
     * @return array Result array with 'success' bool and 'error' string or 'message_id'
     */
    public static function send_sms($phone, $message) {
        $creds = self::get_credentials();

        // Check if credentials are configured
        if (!self::is_configured()) {
            return [
                'success' => false,
                'error'   => __('SMS API not configured.', 'club-anketa'),
            ];
        }

        // Format phone number to international format (995XXXXXXXXX)
        $phone_digits = preg_replace('/\D+/', '', $phone);
        if (strlen($phone_digits) === 9) {
            $phone_digits = '995' . $phone_digits;
        }

        // Build API URL with parameters
        $api_url = add_query_arg([
            'username'   => $creds['username'],
            'password'   => $creds['password'],
            'client_id'  => $creds['client_id'],
            'service_id' => $creds['service_id'],
            'to'         => $phone_digits,
            'text'       => rawurlencode($message),
            'result'     => 'json',
        ], self::API_URL);

        // Make API request
        $response = wp_remote_get($api_url, [
            'timeout' => 30,
        ]);

        // Handle connection errors
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Validate response format to prevent potential injection from malformed API responses
        if (!is_array($data)) {
            $data = [];
        }

        // Check response code - only accept alphanumeric codes
        if (isset($data['code']) && is_string($data['code'])) {
            $code = preg_replace('/[^a-zA-Z0-9]/', '', $data['code']);
            
            if (strpos($code, '0000') === 0) {
                // Sanitize message_id to prevent any malicious content
                $message_id = isset($data['message_id']) ? sanitize_text_field($data['message_id']) : '';
                return [
                    'success'    => true,
                    'message_id' => $message_id,
                ];
            }

            $error_messages = [
                '0001' => __('Invalid API credentials or forbidden IP.', 'club-anketa'),
                '0007' => __('Invalid phone number.', 'club-anketa'),
                '0008' => __('Insufficient SMS balance.', 'club-anketa'),
            ];

            return [
                'success' => false,
                'error'   => isset($error_messages[$code]) ? $error_messages[$code] : __('SMS sending failed.', 'club-anketa'),
            ];
        }

        // Fallback: check if response starts with 0000 (plain text response)
        if (is_string($body) && strpos($body, '0000') === 0) {
            return [
                'success'    => true,
                'message_id' => trim(str_replace('0000-', '', $body)),
            ];
        }

        return [
            'success' => false,
            'error'   => __('Unexpected API response.', 'club-anketa'),
        ];
    }

    /**
     * Send OTP verification SMS
     *
     * @param string $phone Phone number
     * @param string $otp   OTP code
     * @return array Result array with 'success' bool and 'error' string or 'message_id'
     */
    public static function send_otp($phone, $otp) {
        $message = sprintf(__('თქვენი ვერიფიკაციის კოდია: %s', 'club-anketa'), $otp);
        return self::send_sms($phone, $message);
    }
}
