<?php
/**
 * Loader Class - Orchestrates all hooks and initializes components
 *
 * @package ClubAnketa\Core
 */

namespace ClubAnketa\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Loader {

    /**
     * Array of actions to be registered with WordPress
     *
     * @var array
     */
    protected $actions = [];

    /**
     * Array of filters to be registered with WordPress
     *
     * @var array
     */
    protected $filters = [];

    /**
     * Initialize the Loader and define all hooks
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_frontend_hooks();
        $this->define_woocommerce_hooks();
        $this->define_ajax_hooks();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Utils is loaded via autoloader, but ensure it's available
        // No explicit require needed due to autoloading
    }

    /**
     * Register all admin-related hooks
     */
    private function define_admin_hooks() {
        $settings = new \ClubAnketa\Admin\Settings();
        $user_columns = new \ClubAnketa\Admin\UserColumns();

        // Admin menu
        $this->add_action('admin_menu', $settings, 'add_settings_page');
        $this->add_action('admin_init', $settings, 'register_settings');

        // User columns
        $this->add_filter('manage_users_columns', $user_columns, 'add_columns');
        $this->add_filter('manage_users_custom_column', $user_columns, 'render_columns', 10, 3);
    }

    /**
     * Register all frontend-related hooks
     */
    private function define_frontend_hooks() {
        $shortcode = new \ClubAnketa\Frontend\Shortcode();
        $printer = new \ClubAnketa\Frontend\Printer();
        $otp_handler = new \ClubAnketa\Frontend\OtpHandler();

        // Shortcode
        add_shortcode('club_anketa_form', [$shortcode, 'render']);

        // Form processing
        $this->add_action('template_redirect', $shortcode, 'process_submission', 1);

        // Rewrite rules for print pages
        $this->add_action('init', $printer, 'register_rewrite_rules');
        $this->add_filter('query_vars', $printer, 'register_query_vars');
        $this->add_filter('template_include', $printer, 'maybe_use_print_template');

        // Enqueue scripts
        $this->add_action('wp_enqueue_scripts', $otp_handler, 'enqueue_scripts');

        // Modal injection via wp_footer (Global Modal Strategy)
        $this->add_action('wp_footer', $otp_handler, 'inject_modal_html', 50);
    }

    /**
     * Register all WooCommerce-related hooks
     */
    private function define_woocommerce_hooks() {
        $woocommerce = new \ClubAnketa\Integrations\WooCommerce();

        // Checkout phone verification UI (Using proper hooks, not preg_replace)
        $this->add_action('woocommerce_after_checkout_billing_form', $woocommerce, 'add_phone_verification_button');
        
        // Account page phone verification
        $this->add_action('woocommerce_after_edit_address_form_billing', $woocommerce, 'add_account_phone_verification');

        // SMS consent fields
        $this->add_action('woocommerce_review_order_before_submit', $woocommerce, 'checkout_sms_consent');
        $this->add_action('woocommerce_edit_account_form', $woocommerce, 'account_sms_consent');
        $this->add_action('woocommerce_save_account_details', $woocommerce, 'save_account_sms_consent', 10, 1);

        // Validation hooks
        $this->add_action('woocommerce_checkout_process', $woocommerce, 'validate_checkout_phone_verification');
        $this->add_action('woocommerce_save_account_details_errors', $woocommerce, 'validate_account_phone_verification', 10, 1);
    }

    /**
     * Register all AJAX hooks
     */
    private function define_ajax_hooks() {
        $otp_handler = new \ClubAnketa\Frontend\OtpHandler();

        // OTP AJAX handlers - both logged in and not logged in
        $this->add_action('wp_ajax_club_anketa_send_otp', $otp_handler, 'ajax_send_otp');
        $this->add_action('wp_ajax_nopriv_club_anketa_send_otp', $otp_handler, 'ajax_send_otp');
        $this->add_action('wp_ajax_club_anketa_verify_otp', $otp_handler, 'ajax_verify_otp');
        $this->add_action('wp_ajax_nopriv_club_anketa_verify_otp', $otp_handler, 'ajax_verify_otp');
    }

    /**
     * Add a new action to the collection
     *
     * @param string $hook          WordPress hook name
     * @param object $component     Object instance containing the callback
     * @param string $callback      Callback method name
     * @param int    $priority      Priority (default: 10)
     * @param int    $accepted_args Number of accepted arguments (default: 1)
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
    }

    /**
     * Add a new filter to the collection
     *
     * @param string $hook          WordPress hook name
     * @param object $component     Object instance containing the callback
     * @param string $callback      Callback method name
     * @param int    $priority      Priority (default: 10)
     * @param int    $accepted_args Number of accepted arguments (default: 1)
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
    }

    /**
     * Register all hooks with WordPress
     */
    public function run() {
        // Register all actions
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        // Register all filters
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}
