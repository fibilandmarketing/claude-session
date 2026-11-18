<?php
/**
 * Shared helper functions.
 *
 * @package TiademTennisLanding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read a setting value: tdl_opt( 'hero', 'title' ).
 *
 * @param string $section Section key.
 * @param string $key     Field key (optional — whole section when empty).
 * @param mixed  $default Fallback.
 * @return mixed
 */
function tdl_opt( $section, $key = '', $default = '' ) {
	$all = TDL_Settings::get();
	if ( ! isset( $all[ $section ] ) ) {
		return $default;
	}
	if ( '' === $key ) {
		return $all[ $section ];
	}
	return isset( $all[ $section ][ $key ] ) ? $all[ $section ][ $key ] : $default;
}

/**
 * Convert latin digits to Persian digits.
 *
 * @param string|int|float $value Value.
 * @return string
 */
function tdl_fa( $value ) {
	return strtr( (string) $value, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫' ) );
}

/**
 * Format a price as Persian digits with Persian thousands separator.
 *
 * @param float $amount Amount.
 * @return string
 */
function tdl_money( $amount ) {
	$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 0;
	$formatted = number_format( (float) $amount, $decimals, '.', ',' );
	return strtr( tdl_fa( $formatted ), array( ',' => '٬' ) );
}

/**
 * Currency label shown after prices.
 *
 * @return string
 */
function tdl_currency() {
	$label = (string) tdl_opt( 'general', 'currency', '' );
	if ( '' !== trim( $label ) ) {
		return $label;
	}
	if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
		$symbol = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
		$map    = array(
			'IRT' => 'تومان',
			'IRR' => 'ریال',
		);
		$code = get_woocommerce_currency();
		if ( isset( $map[ $code ] ) ) {
			return $map[ $code ];
		}
		return $symbol;
	}
	return 'تومان';
}

/**
 * Icon library (stroke SVG paths, 24x24).
 *
 * @return array
 */
function tdl_icons() {
	return array(
		'diamond'  => array( 'الماس', 'M6 3h12l4 6-10 12L2 9l4-6zM2 9h20' ),
		'layers'   => array( 'لایه‌ها', 'M4 7h16M4 12h16M4 17h10' ),
		'lock'     => array( 'قفل', 'M5 11h14v9H5zM8 11V8a4 4 0 0 1 8 0v3' ),
		'chat'     => array( 'گفتگو', 'M4 5h16v11H8l-4 4z' ),
		'drop'     => array( 'قطره', 'M12 3s6 7 6 11a6 6 0 0 1-12 0c0-4 6-11 6-11z' ),
		'perfume'  => array( 'عطر', 'M9 3h6v4H9zM7 7h10v14H7z' ),
		'brush'    => array( 'تمیزکاری', 'M4 20l6-6M14 4l6 6-8 8-6-6z' ),
		'box'      => array( 'جعبه', 'M4 8h16v12H4zM8 8V5h8v3' ),
		'star'     => array( 'ستاره', 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z' ),
		'sparkle'  => array( 'درخشش', 'M20 12l-8 8-8-8 8-8 8 8zM12 8v8M8 12h8' ),
		'book'     => array( 'راهنما', 'M4 5h16v14H4zM8 9h8M8 13h5' ),
		'question' => array( 'سؤال', 'M9 9a3 3 0 1 1 4 2.8c-.6.3-1 .9-1 1.6V14M12 18h.01' ),
		'truck'    => array( 'ارسال', 'M3 7h11v9H3zM14 10h4l3 3v3h-7zM7 19a2 2 0 1 0 .01 0M17 19a2 2 0 1 0 .01 0' ),
		'shield'   => array( 'ضمانت', 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z' ),
		'gift'     => array( 'هدیه', 'M4 10h16v10H4zM3 7h18v3H3zM12 7v13M12 7c-2-4-6-3-5 0M12 7c2-4 6-3 5 0' ),
		'heart'    => array( 'قلب', 'M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z' ),
		'tag'      => array( 'تخفیف', 'M3 12V4h8l10 10-8 8L3 12zM7.5 7.5h.01' ),
		'phone'    => array( 'تلفن', 'M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z' ),
	);
}

/**
 * Print an icon SVG.
 *
 * @param string $name   Icon key.
 * @param int    $size   Pixel size.
 * @param float  $stroke Stroke width.
 * @return string
 */
function tdl_icon( $name, $size = 22, $stroke = 1.6 ) {
	$icons = tdl_icons();
	$path  = isset( $icons[ $name ] ) ? $icons[ $name ][1] : $icons['diamond'][1];
	return sprintf(
		'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="%2$s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="%3$s"/></svg>',
		(int) $size,
		esc_attr( $stroke ),
		esc_attr( $path )
	);
}

/**
 * Inline UI glyphs used by the templates.
 *
 * @param string $name Glyph.
 * @param int    $size Size.
 * @return string
 */
function tdl_glyph( $name, $size = 18 ) {
	$s = (int) $size;
	switch ( $name ) {
		case 'search':
			return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>';
		case 'bag':
			return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 7h12l-1 13H7L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>';
		case 'close':
			return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>';
		case 'arrow-left':
			return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>';
		case 'chev-left':
			return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6"/></svg>';
		case 'chev-right':
			return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg>';
		case 'chev-down':
			return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6"/></svg>';
		case 'plus':
			return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"/></svg>';
		case 'star':
			return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/></svg>';
		case 'heart':
			return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/></svg>';
		case 'check':
			return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
		case 'phone':
			return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z"/></svg>';
		case 'instagram':
			return '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><defs><radialGradient id="tdl-ig" cx="30%" cy="107%" r="150%"><stop offset="0" stop-color="#FDF497"/><stop offset=".08" stop-color="#FDF497"/><stop offset=".45" stop-color="#FD5949"/><stop offset=".62" stop-color="#D6249F"/><stop offset=".9" stop-color="#285AEB"/></radialGradient></defs><rect x="2" y="2" width="20" height="20" rx="6" fill="url(#tdl-ig)"/><rect x="6.4" y="6.4" width="11.2" height="11.2" rx="3.6" fill="none" stroke="#FFFFFF" stroke-width="1.8"/><circle cx="12" cy="12" r="2.7" fill="none" stroke="#FFFFFF" stroke-width="1.8"/><circle cx="16.4" cy="7.6" r="1" fill="#FFFFFF"/></svg>';
		case 'telegram':
			return '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="11" fill="#2AABEE"/><path d="M5.6 11.7l10.6-4.1c.5-.2.9.1.8.8l-1.8 8.5c-.1.6-.5.8-1 .5l-2.8-2.1-1.4 1.3c-.2.2-.3.3-.6.3l.2-2.9 5.2-4.7c.2-.2 0-.3-.3-.1l-6.4 4-2.7-.9c-.6-.2-.6-.6.2-.6z" fill="#FFFFFF"/></svg>';
		case 'bale':
			return '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="11" fill="#2EB67D"/><path d="M7 7.5h6.2a3.2 3.2 0 0 1 1.9 5.8 3.4 3.4 0 0 1-1.6 6.2H9.2l-2.2 1.8z" fill="#FFFFFF"/><circle cx="12.3" cy="15.6" r="1.3" fill="#2EB67D"/></svg>';
	}
	return '';
}

/**
 * Known intrinsic sizes of bundled default images.
 *
 * @return array name => [w, h]
 */
function tdl_asset_sizes() {
	return array(
		'logo.png'          => array( 689, 134 ),
		'sparkle.png'       => array( 160, 160 ),
		'hero-anim.webp'    => array( 1057, 336 ),
		'hero-poster.webp'  => array( 1057, 336 ),
		'economy-side.webp' => array( 720, 229 ),
		'master-side.webp'  => array( 625, 720 ),
		'bg-texture.webp'   => array( 1100, 1100 ),
	);
}

/**
 * Resolve an image value (attachment ID, "asset:name" or URL) to a URL.
 *
 * @param mixed  $value Stored value.
 * @param string $size  WP image size for attachments.
 * @return string
 */
function tdl_img_url( $value, $size = 'full' ) {
	if ( is_numeric( $value ) && (int) $value > 0 ) {
		$src = wp_get_attachment_image_url( (int) $value, $size );
		return $src ? $src : '';
	}
	$value = (string) $value;
	if ( 0 === strpos( $value, 'asset:' ) ) {
		$name = substr( $value, 6 );
		if ( false === strpos( $name, '.' ) ) {
			$name .= '-800.webp';
		}
		return TDL_URL . 'assets/img/' . rawurlencode( $name );
	}
	if ( preg_match( '#^https?://#', $value ) ) {
		return $value;
	}
	return '';
}

/**
 * Original, untouched file for an image value (needed for animated GIF/WebP:
 * WordPress' resized sub-sizes are static single frames).
 *
 * @param mixed $value Stored value.
 * @return array url, width, height
 */
function tdl_img_original( $value ) {
	if ( is_numeric( $value ) && (int) $value > 0 ) {
		$id   = (int) $value;
		$url  = function_exists( 'wp_get_original_image_url' ) ? wp_get_original_image_url( $id ) : '';
		$url  = $url ? $url : wp_get_attachment_url( $id );
		$meta = wp_get_attachment_metadata( $id );
		$w    = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
		$h    = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
		return array( (string) $url, $w, $h );
	}
	$value = (string) $value;
	if ( 0 === strpos( $value, 'asset:' ) ) {
		$name  = substr( $value, 6 );
		$sizes = tdl_asset_sizes();
		return array(
			TDL_URL . 'assets/img/' . rawurlencode( $name ),
			isset( $sizes[ $name ] ) ? $sizes[ $name ][0] : 0,
			isset( $sizes[ $name ] ) ? $sizes[ $name ][1] : 0,
		);
	}
	return array( preg_match( '#^https?://#', $value ) ? $value : '', 0, 0 );
}

/**
 * Build an <img> tag for an image value.
 *
 * @param mixed $value Stored value.
 * @param array $args  alt, class, size, sizes, loading, fetchpriority, style, width, height.
 * @return string
 */
function tdl_img( $value, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'alt'           => '',
			'class'         => '',
			'size'          => 'woocommerce_single',
			'sizes'         => '(max-width: 700px) 50vw, 320px',
			'loading'       => 'lazy',
			'fetchpriority' => '',
			'style'         => '',
			'extra'         => '',
		)
	);

	$attrs = array(
		'alt'      => $args['alt'],
		'class'    => $args['class'],
		'loading'  => $args['loading'],
		'decoding' => 'async',
	);
	if ( $args['fetchpriority'] ) {
		$attrs['fetchpriority'] = $args['fetchpriority'];
	}
	if ( $args['style'] ) {
		$attrs['style'] = $args['style'];
	}

	if ( is_numeric( $value ) && (int) $value > 0 ) {
		$attrs['sizes'] = $args['sizes'];
		$html           = wp_get_attachment_image( (int) $value, $args['size'], false, $attrs );
		return $html ? $html : '';
	}

	$value = (string) $value;
	if ( 0 === strpos( $value, 'asset:' ) ) {
		$name = substr( $value, 6 );
		if ( false === strpos( $name, '.' ) ) {
			// Bundled photo with 480/800 variants (4:5).
			$base             = TDL_URL . 'assets/img/' . rawurlencode( $name );
			$attrs['src']     = $base . '-800.webp';
			$attrs['srcset']  = $base . '-480.webp 480w, ' . $base . '-800.webp 800w';
			$attrs['sizes']   = $args['sizes'];
			$attrs['width']   = 800;
			$attrs['height']  = 1000;
		} else {
			$attrs['src'] = TDL_URL . 'assets/img/' . rawurlencode( $name );
			$sizes        = tdl_asset_sizes();
			if ( isset( $sizes[ $name ] ) ) {
				$attrs['width']  = $sizes[ $name ][0];
				$attrs['height'] = $sizes[ $name ][1];
			}
		}
	} elseif ( preg_match( '#^https?://#', $value ) ) {
		$attrs['src'] = $value;
	} else {
		return '';
	}

	$out = '<img';
	foreach ( $attrs as $k => $v ) {
		if ( '' === $v || null === $v ) {
			if ( 'alt' !== $k ) {
				continue;
			}
		}
		$out .= ' ' . $k . '="' . ( 'src' === $k ? esc_url( $v ) : esc_attr( $v ) ) . '"';
	}
	if ( $args['extra'] ) {
		$out .= ' ' . $args['extra'];
	}
	return $out . '>';
}

/**
 * Resolve link fields: product_cat term wins over URL.
 *
 * @param int    $term Term id.
 * @param string $url  URL.
 * @param string $fallback Fallback.
 * @return string
 */
function tdl_link( $term, $url, $fallback = '#grid' ) {
	if ( $term ) {
		$link = get_term_link( (int) $term, 'product_cat' );
		if ( ! is_wp_error( $link ) ) {
			return $link;
		}
	}
	return '' !== (string) $url ? (string) $url : $fallback;
}

/**
 * Allowed inline HTML for short rich fields.
 *
 * @param string $html HTML.
 * @return string
 */
function tdl_kses( $html ) {
	return wp_kses_post( (string) $html );
}

/**
 * Escape text and convert digits to Persian.
 *
 * @param string $text Text.
 * @return string
 */
function tdl_e( $text ) {
	return esc_html( (string) $text );
}
