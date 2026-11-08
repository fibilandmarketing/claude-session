<?php
/**
 * Front end: serves the landing as the site's homepage (or as a preview for admins).
 */

defined( 'ABSPATH' ) || exit;

/** Is the landing being rendered on this request? */
function nrn_is_active_request() {
	static $active = null;
	if ( null !== $active ) {
		return $active;
	}
	$active = false;
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() ) {
		return $active;
	}
	$c = NRN_Content::get();
	// ?nrn_preview=1 lets an administrator see the page before switching it on
	if ( isset( $_GET['nrn_preview'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$active = true;
	} elseif ( $c->setting( 'enabled' ) && is_front_page() && ! is_paged() ) {
		$active = true;
	}
	return $active;
}

add_filter( 'template_include', function ( $template ) {
	if ( nrn_is_active_request() ) {
		status_header( 200 );
		return NRN_DIR . 'templates/page.php';
	}
	return $template;
}, 99 );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! nrn_is_active_request() ) {
		return;
	}
	$c   = NRN_Content::get();
	$ver = NRN_VERSION . '.' . (int) get_option( 'nrn_rev', 0 );
	wp_enqueue_style( 'nrn-base', NRN_URL . 'assets/css/base.css', array(), $ver );
	wp_enqueue_style( 'nrn-desktop', NRN_URL . 'assets/css/desktop.css', array( 'nrn-base' ), $ver );
	wp_enqueue_style( 'nrn-mobile', NRN_URL . 'assets/css/mobile.css', array( 'nrn-base' ), $ver );

	$bp  = max( 320, min( 1400, (int) $c->setting( 'breakpoint' ) ) );
	$css = '@media (max-width:' . ( $bp - 1 ) . 'px){.nrn-d{display:none!important}.nrn-m{display:block!important}}';
	wp_add_inline_style( 'nrn-base', $css );

	wp_enqueue_script( 'nrn-main', NRN_URL . 'assets/js/neuron.js', array(), $ver, true );
	$map = $c->map_cfg();
	wp_localize_script( 'nrn-main', 'NRN_CFG', array(
		'map' => array(
			'def'         => $map['def'],
			'url'         => $map['url'],
			'countSuffix' => $map['count_suffix'],
			'noneText'    => $map['none_text'],
			'ctaHas'      => $map['cta_has'],
			'ctaNone'     => $map['cta_none'],
			'noneUrl'     => $map['none_url'],
		),
	) );
}, 20 );

/**
 * Keeps theme (and optionally all other) stylesheets off the landing so the
 * design renders exactly; scripts are left alone (analytics, chat widgets...).
 */
add_action( 'wp_enqueue_scripts', 'nrn_strip_styles', 9999 );
add_action( 'wp_print_styles', 'nrn_strip_styles', 9999 );
add_action( 'wp_print_footer_scripts', 'nrn_strip_styles', 1 );
function nrn_strip_styles() {
	if ( ! nrn_is_active_request() ) {
		return;
	}
	$mode = NRN_Content::get()->setting( 'strip' );
	if ( 'none' === $mode ) {
		return;
	}
	global $wp_styles;
	if ( ! $wp_styles instanceof WP_Styles ) {
		return;
	}
	$keep = array( 'nrn-base', 'nrn-desktop', 'nrn-mobile', 'admin-bar', 'dashicons', 'wp-img-auto-sizes-contain' );
	$core = array( 'wp-block-library', 'wp-block-library-theme', 'global-styles', 'classic-theme-styles', 'core-block-supports', 'core-block-supports-duotone', 'wp-emoji-styles', 'wp-block-template-skip-link' );
	foreach ( (array) $wp_styles->queue as $handle ) {
		if ( in_array( $handle, $keep, true ) ) {
			continue;
		}
		$dep  = isset( $wp_styles->registered[ $handle ] ) ? $wp_styles->registered[ $handle ] : null;
		$src  = $dep ? (string) $dep->src : '';
		$path = $dep && isset( $dep->extra['path'] ) ? wp_normalize_path( (string) $dep->extra['path'] ) : '';
		$from_theme = ( $src && false !== strpos( $src, '/themes/' ) ) || ( $path && 0 === strpos( $path, wp_normalize_path( get_theme_root() ) ) );
		if ( 'all' === $mode || in_array( $handle, $core, true ) || $from_theme ) {
			wp_dequeue_style( $handle );
		}
	}
}

/* theme.json / block-supports inline CSS printed outside the queue */
add_action( 'wp', function () {
	if ( ! nrn_is_active_request() || 'none' === NRN_Content::get()->setting( 'strip' ) ) {
		return;
	}
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
	remove_action( 'wp_head', 'wp_global_styles_render_svg_filters' );
	remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
	remove_action( 'wp_body_open', 'wp_render_duotone_filter_preset' );
	remove_action( 'wp_head', 'wp_print_font_faces', 50 );
} );


/** Bump the asset version whenever content is saved (busts page-cache CSS/JS). */
function nrn_bump_rev() {
	update_option( 'nrn_rev', (int) get_option( 'nrn_rev', 0 ) + 1, false );
}
