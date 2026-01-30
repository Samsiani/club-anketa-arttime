<?php
/**
 * Printer Class - Handles print template routing
 *
 * @package ClubAnketa\Frontend
 */

namespace ClubAnketa\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class Printer {

    /**
     * Register rewrite rules for print pages
     */
    public function register_rewrite_rules() {
        add_rewrite_rule('^print-anketa/?$', 'index.php?is_anketa_print_page=1', 'top');
        add_rewrite_rule('^signature-terms/?$', 'index.php?is_signature_terms_page=1', 'top');
    }

    /**
     * Register custom query variables
     *
     * @param array $vars Existing query vars
     * @return array Modified query vars
     */
    public function register_query_vars($vars) {
        $vars[] = 'is_anketa_print_page';
        $vars[] = 'is_signature_terms_page';
        return $vars;
    }

    /**
     * Load print template when appropriate
     *
     * @param string $template Current template path
     * @return string Modified template path
     */
    public function maybe_use_print_template($template) {
        if (get_query_var('is_anketa_print_page')) {
            return CLUB_ANKETA_PATH . 'templates/print/anketa.php';
        }
        if (get_query_var('is_signature_terms_page')) {
            return CLUB_ANKETA_PATH . 'templates/print/signature-terms.php';
        }
        return $template;
    }
}
