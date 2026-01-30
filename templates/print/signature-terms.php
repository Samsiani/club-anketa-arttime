<?php
/**
 * Signature Terms Print Template
 * URL: /signature-terms/?user_id=ID&terms_type=sms|call
 *
 * Priority:
 * 1) Rich editor content (styled; auto-paragraphs if only inline markup)
 * 2) External URL iframe (fallback)
 * 3) Built-in fallback HTML.
 *
 * @package ClubAnketa
 */

use ClubAnketa\Core\Utils;

if (!defined('ABSPATH')) {
    exit;
}

$user_id    = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
$terms_type = isset($_GET['terms_type']) ? sanitize_key($_GET['terms_type']) : '';
$anketa_url = esc_url(add_query_arg('user_id', $user_id, home_url('/print-anketa/')));

// Determine which content and title to display based on terms_type
$is_specific_terms = false;
if ($terms_type === 'sms') {
    $terms_html_raw = (string) get_option('club_anketa_sms_terms_html', '');
    $page_title     = __('SMS შეტყობინების პირობები', 'club-anketa');
    $is_specific_terms = true;
} elseif ($terms_type === 'call') {
    $terms_html_raw = (string) get_option('club_anketa_call_terms_html', '');
    $page_title     = __('სატელეფონო ზარის პირობები', 'club-anketa');
    $is_specific_terms = true;
} else {
    $terms_html_raw = (string) get_option('club_anketa_terms_html', '');
    $page_title     = __('წესები და პირობები', 'club-anketa');
}
$terms_url = (string) get_option('club_anketa_terms_url', '');

// Get fallback rules from centralized Utils method
$fallback_rules = Utils::get_default_rules_text();

// Prepare rich content (auto-paragraph if only inline spans/bolds).
$terms_html_prepared = '';
if (trim($terms_html_raw) !== '') {
    $terms_html_prepared = Utils::prepare_terms_html($terms_html_raw);
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html__('Terms & Signature', 'club-anketa'); ?></title>
<link rel="stylesheet" href="<?php echo esc_url(CLUB_ANKETA_URL . 'assets/css/print.css?v=' . urlencode(CLUB_ANKETA_VERSION)); ?>" media="all" />
<style>
.signature-terms-wrapper { max-width: 210mm; margin: 0 auto; padding: 14mm; background: #fff; }
.terms-iframe {
  width: 100%;
  min-height: 240mm;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
}
.rules-inner { margin-top: 8mm; }
.rules-inner p { margin: 0 0 8px; }
</style>
</head>
<body>
<div class="print-actions">
    <button onclick="window.print()"><?php echo esc_html__('Print Terms', 'club-anketa'); ?></button>
    <a class="button button-secondary print-terms-btn" href="<?php echo $anketa_url; ?>"><?php echo esc_html__('Print Anketa', 'club-anketa'); ?></a>
</div>

<div class="signature-terms-wrapper page">
    <h2 style="text-align:center; margin:0 0 8mm;"><?php echo esc_html($page_title); ?></h2>

    <?php if ($terms_html_prepared !== ''): ?>
        <div class="rules-inner">
            <?php
            // Already sanitized + formatted; output directly.
            echo $terms_html_prepared; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </div>
    <?php elseif ($is_specific_terms): ?>
        <div class="rules-inner">
            <p style="color:#555;"><?php echo esc_html__('No content has been configured for this terms type. Please configure it in the Club Anketa Settings.', 'club-anketa'); ?></p>
        </div>
    <?php elseif (!empty($terms_url)): ?>
        <iframe class="terms-iframe" src="<?php echo esc_url($terms_url); ?>"></iframe>
        <p class="description" style="margin-top:8px;color:#555;"><?php echo esc_html__('Loaded from configured URL (no custom editor content provided).', 'club-anketa'); ?></p>
    <?php else: ?>
        <div class="rules-inner">
            <?php echo wp_kses_post($fallback_rules); ?>
        </div>
    <?php endif; ?>

    <div class="row signature-row no-break" style="margin-top:12mm;">
        <div class="label"><?php echo esc_html__('მომხმარებლის ხელმოწერა', 'club-anketa'); ?></div>
        <div class="value value-line"></div>
    </div>
</div>
</body>
</html>
