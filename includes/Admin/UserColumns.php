<?php
/**
 * UserColumns Class - Admin user list custom columns
 *
 * @package ClubAnketa\Admin
 */

namespace ClubAnketa\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class UserColumns {

    /**
     * Add custom columns to user list
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_columns($columns) {
        $columns['club_anketa_sms'] = __('SMS accept', 'club-anketa');
        $columns['club_anketa_call'] = __('Call accept', 'club-anketa');
        return $columns;
    }

    /**
     * Render custom column content
     *
     * @param string $output      Default output
     * @param string $column_name Column name
     * @param int    $user_id     User ID
     * @return string Column output
     */
    public function render_columns($output, $column_name, $user_id) {
        if ($column_name === 'club_anketa_sms') {
            $val = get_user_meta((int) $user_id, '_sms_consent', true);
            $val = is_string($val) ? strtolower($val) : '';
            
            if ($val === 'yes') {
                return '<span style="color:#2e7d32;font-weight:600;">' . esc_html__('Yes', 'club-anketa') . '</span>';
            }
            if ($val === 'no') {
                return '<span style="color:#c62828;font-weight:600;">' . esc_html__('No', 'club-anketa') . '</span>';
            }
            return '<span style="color:#616161;">' . esc_html__('(blank)', 'club-anketa') . '</span>';
        }

        if ($column_name === 'club_anketa_call') {
            $val = get_user_meta((int) $user_id, '_call_consent', true);
            $val = is_string($val) ? strtolower($val) : '';
            
            if ($val === 'yes') {
                return '<span style="color:#2e7d32;font-weight:600;">' . esc_html__('Yes', 'club-anketa') . '</span>';
            }
            if ($val === 'no') {
                return '<span style="color:#c62828;font-weight:600;">' . esc_html__('No', 'club-anketa') . '</span>';
            }
            return '<span style="color:#616161;">' . esc_html__('(blank)', 'club-anketa') . '</span>';
        }

        return $output;
    }
}
