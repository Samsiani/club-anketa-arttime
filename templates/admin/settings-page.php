<?php
/**
 * Admin Settings Page Template
 *
 * @package ClubAnketa
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Club Anketa Settings', 'club-anketa'); ?></h1>
    <form action="options.php" method="post">
        <?php
        settings_fields('club_anketa_settings_group');
        do_settings_sections('club_anketa_settings');
        submit_button();
        ?>
    </form>
</div>
