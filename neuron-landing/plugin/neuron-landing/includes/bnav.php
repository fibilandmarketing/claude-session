<?php
/**
 * Mobile bottom navigation bar (phones only): a floating dark-glass bar in the
 * landing's style, with a raised orange main button and a live cart badge.
 */

defined( 'ABSPATH' ) || exit;

/** Line icons drawn in the landing's style (24 x 24, round strokes). */
function nrn_bnav_icons() {
	return array(
		'agents'    => array( 'نمایندگان', '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5a5.5 5.5 0 0 1 11 0"/><circle cx="17" cy="9.5" r="2.4"/><path d="M15.6 14.4a4.6 4.6 0 0 1 5.4 4.6"/>' ),
		'map'       => array( 'نقشه / مکان', '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.6"/>' ),
		'download'  => array( 'نرم‌افزار / دانلود', '<rect x="6.5" y="2.5" width="11" height="19" rx="2.6"/><path d="M12 7v6.5"/><path d="M9.3 11L12 13.7 14.7 11"/><path d="M10.5 18.5h3"/>' ),
		'dashboard' => array( 'سامانه', '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><path d="M9 9.6l2 2 4-4"/>' ),
		'radar'     => array( 'ردیابی', '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><path d="M12 12l6-6"/><circle cx="12" cy="12" r="1.2"/>' ),
		'cart'      => array( 'سبد خرید', '<path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6.2"/><circle cx="10" cy="20.5" r="1"/><circle cx="17" cy="20.5" r="1"/>' ),
		'store'     => array( 'فروشگاه', '<path d="M4 9.5l1.2-5h13.6L20 9.5"/><path d="M4 9.5a2.7 2.7 0 0 0 5.3 0 2.7 2.7 0 0 0 5.4 0 2.7 2.7 0 0 0 5.3 0"/><path d="M5.5 12v8.5h13V12"/><path d="M10 20.5v-5h4v5"/>' ),
		'support'   => array( 'پشتیبانی', '<path d="M4.5 13.5v-1.8a7.5 7.5 0 0 1 15 0v1.8"/><rect x="3.5" y="13" width="4" height="6" rx="1.6"/><rect x="16.5" y="13" width="4" height="6" rx="1.6"/><path d="M18.5 19c0 1.6-1.8 2.5-4.5 2.5"/>' ),
		'phone'     => array( 'تماس', '<path d="M5 4h3.5l1.6 4.2-2.2 1.4a11 11 0 0 0 6.5 6.5l1.4-2.2L20 15.5V19a1.5 1.5 0 0 1-1.6 1.5C10.6 20 4 13.4 3.5 5.6A1.5 1.5 0 0 1 5 4z"/>' ),
		'user'      => array( 'حساب کاربری', '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>' ),
		'home'      => array( 'خانه', '<path d="M3 10.5L12 3l9 7.5"/><path d="M5 9.5V20h5v-6h4v6h5V9.5"/>' ),
		'tag'       => array( 'محصولات', '<path d="M3.5 12.2V4.5a1 1 0 0 1 1-1h7.7l8.3 8.3a1.5 1.5 0 0 1 0 2.1l-6.1 6.1a1.5 1.5 0 0 1-2.1 0z"/><circle cx="8" cy="8" r="1.4"/>' ),
		'menu'      => array( 'منو (باز کردن منوی کشویی)', '<path d="M4 7h16M4 12h16M4 17h10"/>' ),
	);
}

function nrn_bnav_defaults() {
	return array(
		array( 'label' => 'نمایندگان', 'icon' => 'agents', 'url' => 'https://noroncar.ir/namayandegi/', 'main' => false ),
		array( 'label' => 'نرم‌افزار', 'icon' => 'download', 'url' => 'https://noroncar.ir/download-app/', 'main' => false ),
		array( 'label' => 'سامانه', 'icon' => 'dashboard', 'url' => '#', 'main' => true ),
		array( 'label' => 'سبد خرید', 'icon' => 'cart', 'url' => '', 'main' => false ),
		array( 'label' => 'پشتیبانی', 'icon' => 'support', 'url' => 'https://noroncar.ir/support/', 'main' => false ),
	);
}

function nrn_render_bnav( NRN_Content $c ) {
	if ( ! $c->on( 'bnav' ) ) {
		return;
	}
	$items = array_values( array_filter( (array) $c->items( 'bnav' ), function ( $it ) {
		return '' !== trim( (string) ( $it['label'] ?? '' ) );
	} ) );
	if ( ! $items ) {
		return;
	}
	$icons    = nrn_bnav_icons();
	$main_set = false;
	$cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '#';
	echo '<div class="nrn-bnav-space" aria-hidden="true"></div>';
	echo '<nav class="nrn-bnav" aria-label="دسترسی سریع" style="--n:' . count( $items ) . '">';
	foreach ( $items as $it ) {
		$icon = isset( $icons[ $it['icon'] ?? '' ] ) ? $it['icon'] : 'home';
		$url  = trim( (string) ( $it['url'] ?? '' ) );
		if ( '' === $url ) {
			$url = 'cart' === $icon ? $cart_url : '#';
		}
		$main = ! empty( $it['main'] ) && ! $main_set;
		$main_set = $main_set || $main;
		$attrs = 'menu' === $icon || '#menu' === $url ? ' data-menu-open="1" aria-controls="m-menu"' : '';
		echo '<a class="bn-item' . ( $main ? ' bn-main' : '' ) . '" href="' . esc_url( '#menu' === $url ? '#m-top' : $url ) . '"' . $attrs . '>';
		echo '<span class="bn-ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[ $icon ][1] . '</svg>'; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed icon set
		if ( 'cart' === $icon ) {
			echo '<span class="bn-badge" data-cart-count hidden></span>';
		}
		echo '</span><span class="bn-t">' . esc_html( $it['label'] ) . '</span></a>';
	}
	echo '</nav>';
}
