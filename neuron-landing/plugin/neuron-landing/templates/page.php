<?php
/**
 * Full-page template of the landing (replaces the theme's homepage template).
 */
defined( 'ABSPATH' ) || exit;

$nl   = NRN_Content::get();
$wrap = 'nrn';
if ( $nl->setting( 'hide_tbd' ) ) {
	$wrap .= ' nrn-hide-tbd';
}
if ( $nl->setting( 'hide_phb' ) ) {
	$wrap .= ' nrn-hide-phb';
}
?><!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0B0C0F">
<?php if ( ! current_theme_supports( 'title-tag' ) ) : ?>
<title><?php echo esc_html( wp_get_document_title() ); ?></title>
<?php endif; ?>
<?php wp_head(); ?>
</head>
<body <?php body_class( 'nrn-body' ); ?>>
<?php wp_body_open(); ?>
<div class="<?php echo esc_attr( $wrap ); ?> nrn-d" data-layout="d">
<?php $nl->layout = 'd'; include NRN_DIR . 'templates/layout-desktop.php'; ?>
</div>
<div class="<?php echo esc_attr( $wrap ); ?> nrn-m" data-layout="m">
<?php $nl->layout = 'm'; include NRN_DIR . 'templates/layout-mobile.php'; ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
