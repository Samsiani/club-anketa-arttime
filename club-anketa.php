<?php
/**
 * Plugin Name:       Club Anketa Registration for WooCommerce
 * Description:       Provides a custom WooCommerce registration "Anketa" form via shortcode and print-ready pages (Anketa + Terms). Includes SMS OTP verification for phone number validation.
 * Version:           3.0.0
 * Author:            Your Name
 * Text Domain:       club-anketa
 */

namespace ClubAnketa;

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('CLUB_ANKETA_VERSION', '3.0.0');
define('CLUB_ANKETA_PATH', plugin_dir_path(__FILE__));
define('CLUB_ANKETA_URL', plugin_dir_url(__FILE__));
define('CLUB_ANKETA_BASENAME', plugin_basename(__FILE__));

// Autoloader for ClubAnketa namespace
spl_autoload_register(function ($class) {
    // Only handle ClubAnketa namespace
    $prefix = 'ClubAnketa\\';
    $base_dir = CLUB_ANKETA_PATH . 'includes/';
    
    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Replace namespace separators with directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
function club_anketa_init() {
    // Load the Loader class which orchestrates all hooks
    $loader = new Core\Loader();
    $loader->run();
}
add_action('plugins_loaded', __NAMESPACE__ . '\\club_anketa_init');

// Activation hook
register_activation_hook(__FILE__, function () {
    require_once CLUB_ANKETA_PATH . 'includes/Core/Activator.php';
    Core\Activator::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    require_once CLUB_ANKETA_PATH . 'includes/Core/Deactivator.php';
    Core\Deactivator::deactivate();
});
