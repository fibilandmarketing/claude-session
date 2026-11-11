<?php
/* Rendering logic for the repeatable lists; appended to includes/partials.php by make_partials.py */

function nrn_faq_items( NRN_Content $c ) {
	$out = array();
	foreach ( (array) $c->items( 'faq' ) as $it ) {
		if ( '' === trim( (string) ( isset( $it['q'] ) ? $it['q'] : '' ) ) ) {
			continue;
		}
		$out[] = $it;
	}
	return $out;
}

function nrn_partial_faq( NRN_Content $c, $layout ) {
	$t     = nrn_tpl();
	$parts = array();
	foreach ( nrn_faq_items( $c ) as $it ) {
		$parts[] = strtr( $t[ 'faq_' . $layout ], array(
			'{OPEN}' => ! empty( $it['open'] ) ? ' open=""' : '',
			'{Q}'    => esc_html( $it['q'] ),
			'{A}'    => esc_html( isset( $it['a'] ) ? $it['a'] : '' ),
		) );
	}
	echo implode( $t[ 'faq_sep_' . $layout ], $parts ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
}

function nrn_partial_orgs( NRN_Content $c, $layout ) {
	$t    = nrn_tpl();
	$sets = array( '', '' );
	foreach ( (array) $c->items( 'orgs' ) as $it ) {
		$name = isset( $it['name'] ) ? $it['name'] : '';
		$logo = isset( $it['logo'] ) ? $it['logo'] : '';
		if ( '' === $name && '' === $logo ) {
			continue;
		}
		foreach ( array( 0, 1 ) as $copy ) {
			$hid = $copy ? ' aria-hidden="true"' : '';
			if ( '' !== $logo ) {
				$sets[ $copy ] .= strtr( $t[ 'org_' . $layout ], array(
					'{HID}'  => $hid,
					'{SRC}'  => esc_url( NRN_Content::asset( $logo ) ),
					'{ALT}'  => $copy ? '' : esc_attr( isset( $it['alt'] ) ? $it['alt'] : '' ),
					'{NAME}' => esc_html( $name ),
				) );
			} else {
				$sets[ $copy ] .= strtr( $t[ 'badge_' . $layout ], array(
					'{HID}'  => $hid,
					'{NAME}' => esc_html( $name ),
				) );
			}
		}
	}
	// the second copy makes the marquee loop seamless
	echo "\n" . $sets[0] . "\n" . $sets[1] . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
}

function nrn_partial_videos( NRN_Content $c, $layout ) {
	$t     = nrn_tpl();
	$parts = array();
	$i     = 0;
	foreach ( (array) $c->items( 'videos' ) as $it ) {
		$label = isset( $it['label'] ) ? trim( $it['label'] ) : '';
		$sub   = isset( $it['sub'] ) ? trim( $it['sub'] ) : '';
		$video = isset( $it['video'] ) ? trim( $it['video'] ) : '';
		$ring  = ( $t[ 'vid_ring_all_' . $layout ] || 0 === $i ) ? $t[ 'vid_ring_' . $layout ] : '';
		$parts[] = strtr( $t[ 'vid_' . $layout ], array(
			'{ARIA}'  => esc_attr( isset( $it['aria'] ) && '' !== $it['aria'] ? $it['aria'] : 'پخش ویدئوی مشتری ' . NRN_Content::fa( $i + 1 ) ),
			'{VID}'   => '' !== $video ? ' data-video="' . esc_url( $video ) . '"' : '',
			'{LABEL}' => '' !== $label ? $t[ 'vid_phb_' . $layout ] . esc_html( $label ) . '</span>' : '',
			'{SRC}'   => esc_url( NRN_Content::asset( isset( $it['cover'] ) ? $it['cover'] : '' ) ),
			'{ALT}'   => esc_attr( isset( $it['alt'] ) ? $it['alt'] : '' ),
			'{TITLE}' => esc_html( isset( $it['title'] ) ? $it['title'] : '' ),
			'{SUB}'   => ( '' !== $sub && '' !== $t[ 'vid_sub_' . $layout ] ) ? $t[ 'vid_sub_' . $layout ] . esc_html( $sub ) . '</p>' : '',
			'{RING}'  => $ring,
		) );
		$i++;
	}
	echo implode( $t[ 'vid_sep_' . $layout ], $parts ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
}

function nrn_partial_mag( NRN_Content $c, $layout ) {
	static $cards = null;
	if ( null === $cards ) {
		$cards = $c->mag_cards();
	}
	$t     = nrn_tpl();
	$m     = $c->mag();
	$parts = array();
	foreach ( $cards as $it ) {
		$phb = isset( $it['phb'] ) ? trim( $it['phb'] ) : '';
		$parts[] = strtr( $t[ 'mag_' . $layout ], array(
			'{URL}'   => esc_url( $it['url'] ),
			'{SRC}'   => esc_url( $it['img'] ),
			'{ALT}'   => esc_attr( $it['alt'] ),
			'{PHB}'   => '' !== $phb ? '<span class="phb">' . esc_html( $phb ) . '</span>' : '',
			'{LABEL}' => '' !== trim( $it['label'] ) ? $t[ 'mag_label_' . $layout ] . esc_html( $it['label'] ) . '</span>' : '',
			'{TITLE}' => esc_html( $it['title'] ),
			'{MORE}'  => '' !== trim( (string) $m['more'] ) ? $t[ 'mag_more_' . $layout ] . esc_html( $m['more'] ) . '</span>' : '',
		) );
	}
	echo implode( $t[ 'mag_sep_' . $layout ], $parts ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
}

function nrn_partial_map_paths( NRN_Content $c, $layout ) {
	$t     = nrn_tpl();
	$provs = $c->provinces();
	$max   = 0;
	foreach ( $provs as $p ) {
		$max = max( $max, (int) $p['n'] );
	}
	$out = '';
	// paint order of the design (outlines overlap at borders)
	foreach ( $c->geo['paths'] as $slug => $d ) {
		$p   = $provs[ $slug ];
		$n   = max( 0, (int) $p['n'] );
		$url = isset( $p['url'] ) ? trim( $p['url'] ) : '';
		$out .= strtr( $t[ 'path_' . $layout ], array(
			'{CLS}'  => $n ? 'prv has' : 'prv',
			'{SLUG}' => esc_attr( $slug ),
			'{NAME}' => esc_attr( $p['name'] ),
			'{URL}'  => '' !== $url ? ' data-url="' . esc_url( $url ) . '"' : '',
			'{N}'    => $n,
			'{FILL}' => $c->province_fill( $n, $max ),
			'{D}'    => $d,
			'{ARIA}' => esc_attr( $p['name'] . '، ' . ( $n ? NRN_Content::fa( $n ) . ' نمایندگی' : 'بدون نمایندگی' ) ),
		) );
	}
	echo $out; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
}

function nrn_partial_map_options( NRN_Content $c, $layout ) {
	$provs = $c->provinces();
	$out   = '';
	foreach ( $c->geo['order'] as $slug ) {
		$out .= '<option value="' . esc_attr( $slug ) . '">' . esc_html( $provs[ $slug ]['name'] ) . '</option>';
	}
	echo $out; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
}

function nrn_partial_badges( NRN_Content $c, $layout ) {
	$t     = nrn_tpl();
	$parts = array();
	foreach ( (array) $c->items( 'badges' ) as $it ) {
		$code = isset( $it['code'] ) ? trim( (string) $it['code'] ) : '';
		$link = isset( $it['link'] ) ? trim( (string) $it['link'] ) : '';
		$img  = isset( $it['img'] ) ? trim( (string) $it['img'] ) : '';
		if ( '' !== $code ) {
			// trust-seal code (eNamad, Samandehi...): stored by an administrator, printed as is
			$parts[] = '<div class="nrn-seal" style="' . esc_attr( $t[ 'seal_box_' . $layout ] ) . '">' . $code . '</div>';
			continue;
		}
		if ( '' === $img && '' === trim( (string) ( $it['label'] ?? '' ) ) ) {
			continue;
		}
		$label = isset( $it['label'] ) ? trim( $it['label'] ) : '';
		$html  = strtr( $t[ 'seal_' . $layout ], array(
			'{SRC}'   => esc_url( NRN_Content::asset( '' !== $img ? $img : 'img/placeholder-badge.svg' ) ),
			'{ALT}'   => esc_attr( isset( $it['alt'] ) ? $it['alt'] : '' ),
			'{LABEL}' => '' !== $label ? $t[ 'seal_label_' . $layout ] . esc_html( $label ) . '</span>' : '',
		) );
		if ( '' !== $link ) {
			$html = '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener" style="display: block; border-radius: inherit">' . $html . '</a>';
		}
		$parts[] = $html;
	}
	echo implode( $t[ 'seal_sep_' . $layout ], $parts ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above / admin-provided seal code
}

function nrn_partial_products( NRN_Content $c, $layout ) {
	static $cards = null;
	if ( null === $cards ) {
		$cards = $c->product_cards();
	}
	$t     = nrn_tpl();
	$parts = array();
	foreach ( $cards as $it ) {
		$cls  = 'cover' === ( $it['fit'] ?? '' ) ? 'pc-cover' : 'mul';
		$size = ! empty( $it['w'] ) && ! empty( $it['h'] ) ? ' width="' . (int) $it['w'] . '" height="' . (int) $it['h'] . '"' : '';
		$img  = '<img class="' . $cls . '" src="' . esc_url( $it['img'] ) . '" alt="' . esc_attr( $it['alt'] ?? '' ) . '" loading="lazy" decoding="async"' . $size . '>';
		$phb  = trim( (string) ( $it['phb'] ?? '' ) );
		$tag  = trim( (string) ( $it['tag'] ?? '' ) );
		$parts[] = strtr( $t[ 'prod_' . $layout ], array(
			'{URL}'   => esc_url( '' !== ( $it['url'] ?? '' ) ? $it['url'] : '#' ),
			'{ARIA}'  => esc_attr( 'مشاهدهٔ محصول ' . $it['name'] ),
			'{PHB}'   => '' !== $phb ? $t[ 'prod_phb_' . $layout ] . esc_html( $phb ) . '</span>' : '',
			'{IMG}'   => $img,
			'{TAG}'   => '' !== $tag ? '<span class="pc-tag">' . esc_html( $tag ) . '</span>' : '',
			'{NAME}'  => esc_html( $it['name'] ),
			'{PRICE}' => esc_html( $it['price'] ?? '' ),
			'{UNIT}'  => esc_html( $it['unit'] ?? '' ),
		) );
	}
	echo implode( $t[ 'prod_sep_' . $layout ], $parts ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
}
