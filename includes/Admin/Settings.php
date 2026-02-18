<?php
/**
 * Settings Class - Admin settings page and options registration
 *
 * @package ClubAnketa\Admin
 */

namespace ClubAnketa\Admin;

use ClubAnketa\Core\Utils;

if (!defined('ABSPATH')) {
    exit;
}

class Settings {

    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_options_page(
            __('Club Anketa Settings', 'club-anketa'),
            __('Club Anketa Settings', 'club-anketa'),
            'manage_options',
            'club-anketa-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register all settings
     */
    public function register_settings() {
        // Terms URL
        register_setting('club_anketa_settings_group', 'club_anketa_terms_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ]);

        // Terms HTML (General)
        register_setting('club_anketa_settings_group', 'club_anketa_terms_html', [
            'type'              => 'string',
            'sanitize_callback' => [Utils::class, 'sanitize_terms_html'],
            'default'           => '',
        ]);

        // SMS Terms HTML
        register_setting('club_anketa_settings_group', 'club_anketa_sms_terms_html', [
            'type'              => 'string',
            'sanitize_callback' => [Utils::class, 'sanitize_terms_html'],
            'default'           => '',
        ]);

        // Call Terms HTML
        register_setting('club_anketa_settings_group', 'club_anketa_call_terms_html', [
            'type'              => 'string',
            'sanitize_callback' => [Utils::class, 'sanitize_terms_html'],
            'default'           => '',
        ]);

        // Email Notification Settings
        register_setting('club_anketa_settings_group', 'club_anketa_enable_email_notification', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        register_setting('club_anketa_settings_group', 'club_anketa_notification_email', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default'           => '',
        ]);

        // SMS API Settings
        register_setting('club_anketa_settings_group', 'club_anketa_sms_username', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        register_setting('club_anketa_settings_group', 'club_anketa_sms_password', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        register_setting('club_anketa_settings_group', 'club_anketa_sms_client_id', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]);

        register_setting('club_anketa_settings_group', 'club_anketa_sms_service_id', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]);

        // General Settings Section
        add_settings_section(
            'club_anketa_main',
            __('General', 'club-anketa'),
            function () {
                echo '<p>' . esc_html__('Configure the Club Anketa plugin.', 'club-anketa') . '</p>';
            },
            'club_anketa_settings'
        );

        // SMS API Settings Section
        add_settings_section(
            'club_anketa_sms_api',
            __('MS Group SMS API Settings', 'club-anketa'),
            function () {
                echo '<p>' . esc_html__('Configure your MS Group SMS API credentials for OTP verification.', 'club-anketa') . '</p>';
            },
            'club_anketa_settings'
        );

        // Email Notifications Section
        add_settings_section(
            'club_anketa_email_notifications',
            __('Email Notifications', 'club-anketa'),
            function () {
                echo '<p>' . esc_html__('Configure email notifications for new registrations.', 'club-anketa') . '</p>';
            },
            'club_anketa_settings'
        );

        // Add settings fields
        $this->add_sms_api_fields();
        $this->add_terms_fields();
        $this->add_email_notification_fields();
        $this->add_shortcodes_section();
    }

    /**
     * Add SMS API settings fields
     */
    private function add_sms_api_fields() {
        add_settings_field(
            'club_anketa_sms_username',
            __('SMS API Username', 'club-anketa'),
            function () {
                $val = esc_attr(get_option('club_anketa_sms_username', ''));
                echo '<input type="text" name="club_anketa_sms_username" value="' . $val . '" class="regular-text" placeholder="Your API username" />';
            },
            'club_anketa_settings',
            'club_anketa_sms_api'
        );

        add_settings_field(
            'club_anketa_sms_password',
            __('SMS API Password', 'club-anketa'),
            function () {
                $val = esc_attr(get_option('club_anketa_sms_password', ''));
                echo '<input type="password" name="club_anketa_sms_password" value="' . $val . '" class="regular-text" placeholder="Your API password" autocomplete="new-password" />';
                echo '<p class="description">' . esc_html__('Keep this secure. Consider using wp-config.php constants for sensitive credentials.', 'club-anketa') . '</p>';
            },
            'club_anketa_settings',
            'club_anketa_sms_api'
        );

        add_settings_field(
            'club_anketa_sms_client_id',
            __('SMS API Client ID', 'club-anketa'),
            function () {
                $val = esc_attr(get_option('club_anketa_sms_client_id', ''));
                echo '<input type="number" name="club_anketa_sms_client_id" value="' . $val . '" class="regular-text" placeholder="Client identifier" />';
            },
            'club_anketa_settings',
            'club_anketa_sms_api'
        );

        add_settings_field(
            'club_anketa_sms_service_id',
            __('SMS API Service ID', 'club-anketa'),
            function () {
                $val = esc_attr(get_option('club_anketa_sms_service_id', ''));
                echo '<input type="number" name="club_anketa_sms_service_id" value="' . $val . '" class="regular-text" placeholder="Brand-name identifier" />';
            },
            'club_anketa_settings',
            'club_anketa_sms_api'
        );
    }

    /**
     * Add terms settings fields
     */
    private function add_terms_fields() {
        add_settings_field(
            'club_anketa_terms_url',
            __('Terms & Conditions URL (Print Terms fallback)', 'club-anketa'),
            function () {
                $val = esc_url(get_option('club_anketa_terms_url', ''));
                echo '<input type="url" name="club_anketa_terms_url" value="' . $val . '" class="regular-text" placeholder="https://example.com/terms" />';
                echo '<p class="description">' . esc_html__('Used only if the rich text editor content is empty.', 'club-anketa') . '</p>';
            },
            'club_anketa_settings',
            'club_anketa_main'
        );

        add_settings_field(
            'club_anketa_terms_html',
            __('Terms & Conditions Content (rich HTML)', 'club-anketa'),
            function () {
                $val = get_option('club_anketa_terms_html', '');
                echo '<p class="description" style="margin-top:-6px;">' . esc_html__('If provided, this full styled HTML is printed (URL ignored). Inline styles & classes are preserved.', 'club-anketa') . '</p>';
                wp_editor(
                    $val,
                    'club_anketa_terms_html',
                    [
                        'textarea_name' => 'club_anketa_terms_html',
                        'media_buttons' => true,
                        'textarea_rows' => 14,
                        'teeny'         => false,
                        'editor_height' => 320,
                    ]
                );
            },
            'club_anketa_settings',
            'club_anketa_main'
        );

        add_settings_field(
            'club_anketa_sms_terms_html',
            __('SMS Terms Content (rich HTML)', 'club-anketa'),
            function () {
                $val = get_option('club_anketa_sms_terms_html', '');
                echo '<p class="description" style="margin-top:-6px;">' . esc_html__('Content for the Print SMS Terms button.', 'club-anketa') . '</p>';
                wp_editor(
                    $val,
                    'club_anketa_sms_terms_html',
                    [
                        'textarea_name' => 'club_anketa_sms_terms_html',
                        'media_buttons' => true,
                        'textarea_rows' => 14,
                        'teeny'         => false,
                        'editor_height' => 320,
                    ]
                );
            },
            'club_anketa_settings',
            'club_anketa_main'
        );

        add_settings_field(
            'club_anketa_call_terms_html',
            __('Phone Call Terms Content (rich HTML)', 'club-anketa'),
            function () {
                $val = get_option('club_anketa_call_terms_html', '');
                echo '<p class="description" style="margin-top:-6px;">' . esc_html__('Content for the Print Phone Call Terms button.', 'club-anketa') . '</p>';
                wp_editor(
                    $val,
                    'club_anketa_call_terms_html',
                    [
                        'textarea_name' => 'club_anketa_call_terms_html',
                        'media_buttons' => true,
                        'textarea_rows' => 14,
                        'teeny'         => false,
                        'editor_height' => 320,
                    ]
                );
            },
            'club_anketa_settings',
            'club_anketa_main'
        );
    }

    /**
     * Add shortcodes information section
     */
    private function add_shortcodes_section() {
        add_settings_section(
            'club_anketa_shortcodes',
            __('Shortcodes', 'club-anketa'),
            function () {
                echo '<p>' . esc_html__('Available shortcodes:', 'club-anketa') . '</p>';
                echo '<ul><li><code>[club_anketa_form]</code> — ' . esc_html__('Displays the Anketa registration form.', 'club-anketa') . '</li></ul>';
                echo '<p class="description">' . esc_html__('Printable pages: /print-anketa/, /signature-terms/', 'club-anketa') . '</p>';
            },
            'club_anketa_settings'
        );
    }

    /**
     * Add email notification settings fields
     */
    private function add_email_notification_fields() {
        add_settings_field(
            'club_anketa_enable_email_notification',
            __('Enable email notification', 'club-anketa'),
            function () {
                $val = get_option('club_anketa_enable_email_notification', '');
                echo '<label><input type="checkbox" name="club_anketa_enable_email_notification" value="yes" ' . checked($val, 'yes', false) . ' /> '
                    . esc_html__('Send an email when a new registration is submitted.', 'club-anketa') . '</label>';
            },
            'club_anketa_settings',
            'club_anketa_email_notifications'
        );

        add_settings_field(
            'club_anketa_notification_email',
            __('Notification Email Address', 'club-anketa'),
            function () {
                $val = esc_attr(get_option('club_anketa_notification_email', ''));
                $nonce = wp_create_nonce('club_anketa_test_email');
                echo '<input type="email" id="club_anketa_notification_email" name="club_anketa_notification_email" value="' . $val . '" class="regular-text" placeholder="admin@example.com" /> ';
                echo '<button type="button" class="button" id="club-anketa-send-test-email">' . esc_html__('Send Test Email', 'club-anketa') . '</button>';
                echo '<script>
                    document.getElementById("club-anketa-send-test-email").addEventListener("click", function() {
                        var email = document.getElementById("club_anketa_notification_email").value;
                        if (!email) {
                            alert("' . esc_js(__('Please enter an email address.', 'club-anketa')) . '");
                            return;
                        }
                        var xhr = new XMLHttpRequest();
                        xhr.open("POST", "' . esc_url(admin_url('admin-ajax.php')) . '", true);
                        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4) {
                                try {
                                    var resp = JSON.parse(xhr.responseText);
                                    alert(resp.data || "Unknown response.");
                                } catch(e) {
                                    alert("Error: Invalid server response.");
                                }
                            }
                        };
                        xhr.send("action=club_anketa_test_email&email=" + encodeURIComponent(email) + "&_ajax_nonce=' . esc_js($nonce) . '");
                    });
                </script>';
            },
            'club_anketa_settings',
            'club_anketa_email_notifications'
        );
    }

    /**
     * AJAX handler to send a test email
     */
    public function ajax_send_test_email() {
        check_ajax_referer('club_anketa_test_email');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'club-anketa'));
        }

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

        if (!is_email($email)) {
            wp_send_json_error(__('Invalid email address.', 'club-anketa'));
        }

        $subject = __('Club Anketa - Test Email', 'club-anketa');
        $body    = __('This is a test email to verify SMTP configuration.', 'club-anketa');
        $sent    = wp_mail($email, $subject, $body);

        if ($sent) {
            wp_send_json_success(__('Test email sent successfully!', 'club-anketa'));
        } else {
            wp_send_json_error(__('Failed to send test email. Check your SMTP settings.', 'club-anketa'));
        }
    }

    /**
     * Render the settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Load the settings page template
        include CLUB_ANKETA_PATH . 'templates/admin/settings-page.php';
    }
}
