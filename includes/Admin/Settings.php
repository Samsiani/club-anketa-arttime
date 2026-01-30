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

        // Add settings fields
        $this->add_sms_api_fields();
        $this->add_terms_fields();
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
