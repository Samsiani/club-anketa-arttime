<?php
/**
 * Activator Class - Handles plugin activation tasks
 *
 * @package ClubAnketa\Core
 */

namespace ClubAnketa\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Activator {

    /**
     * Run activation tasks
     */
    public static function activate() {
        self::register_rewrite_rules();
        flush_rewrite_rules();
    }

    /**
     * Register custom rewrite rules
     */
    private static function register_rewrite_rules() {
        add_rewrite_rule('^print-anketa/?$', 'index.php?is_anketa_print_page=1', 'top');
        add_rewrite_rule('^signature-terms/?$', 'index.php?is_signature_terms_page=1', 'top');
    }
}
