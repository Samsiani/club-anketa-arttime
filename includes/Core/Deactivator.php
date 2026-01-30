<?php
/**
 * Deactivator Class - Handles plugin deactivation tasks
 *
 * @package ClubAnketa\Core
 */

namespace ClubAnketa\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Deactivator {

    /**
     * Run deactivation tasks
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }
}
