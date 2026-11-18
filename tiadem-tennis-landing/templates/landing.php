<?php
/**
 * Full-page landing template.
 *
 * @package TiademTennisLanding
 */

defined( 'ABSPATH' ) || exit;

if ( TDL_Frontend::theme_layout() ) {
	get_header();
	echo TDL_Render::embedded(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in templates.
	get_footer();
	return;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php if ( ! current_theme_supports( 'title-tag' ) ) : ?>
<title><?php echo esc_html( wp_get_document_title() ); ?></title>
<?php endif; ?>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="tdl-skip" href="#tdl-main">پرش به محتوا</a>
<?php TDL_Render::page(); ?>
<?php wp_footer(); ?>
</body>
</html>
