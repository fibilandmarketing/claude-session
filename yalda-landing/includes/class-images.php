<?php
/**
 * Images: bundled WebP assets and on-demand WebP copies of media-library images.
 *
 * Image values in settings are either:
 *   "b:<name>"  bundled file in assets/img (already optimized WebP)
 *   <int>       attachment ID from the media library
 *   ""          empty -> the field's bundled default (if any)
 */

defined( 'ABSPATH' ) || exit;

class YL_Images {

	/** Max WebP conversions during one front-end request (the rest are done on the next views). */
	const BUDGET = 6;

	private static $sizes  = null;
	private static $budget = self::BUDGET;
	private static $deadline = 0;

	/** Allow many conversions (used when settings are saved). */
	public static function unlimited( $seconds = 20 ) {
		self::$budget   = PHP_INT_MAX;
		self::$deadline = microtime( true ) + $seconds;
	}

	private static function sizes() {
		if ( null === self::$sizes ) {
			$f           = YL_DIR . 'assets/img/sizes.json';
			self::$sizes = file_exists( $f ) ? (array) json_decode( (string) file_get_contents( $f ), true ) : array();
		}
		return self::$sizes;
	}

	/** List of bundled images that can be chosen as a value. */
	public static function bundled_exists( $name ) {
		$name = ltrim( (string) $name, '/' );
		if ( '' === $name || false !== strpos( $name, '..' ) ) {
			return false;
		}
		return file_exists( YL_DIR . 'assets/img/' . $name . '.webp' );
	}

	/**
	 * Resolve an image value.
	 *
	 * @param mixed $value  Setting value.
	 * @param array $widths Wanted widths for srcset (largest = 2x of the biggest display size).
	 * @return array|null   [ src, srcset, w, h ]
	 */
	public static function get( $value, $widths = array( 600 ) ) {
		if ( is_string( $value ) && 0 === strpos( $value, 'b:' ) ) {
			return self::bundled( substr( $value, 2 ) );
		}
		$id = absint( $value );
		if ( ! $id ) {
			return null;
		}
		return self::attachment( $id, $widths );
	}

	private static function bundled( $name ) {
		if ( ! self::bundled_exists( $name ) ) {
			return null;
		}
		$sizes = self::sizes();
		$file  = $name . '.webp';
		$dim   = isset( $sizes[ $file ] ) ? $sizes[ $file ] : array( 300, 300 );
		$base  = YL_URL . 'assets/img/';
		$set   = array();
		foreach ( $sizes as $f => $d ) {
			if ( 0 === strpos( $f, $name . '-' ) && preg_match( '/^' . preg_quote( $name, '/' ) . '-(\d+)\.webp$/', $f ) ) {
				$set[] = $base . $f . ' ' . (int) $d[0] . 'w';
			}
		}
		if ( $set ) {
			$set[] = $base . $file . ' ' . (int) $dim[0] . 'w';
		}
		return array(
			'src'    => $base . $file,
			'srcset' => implode( ', ', $set ),
			'w'      => (int) $dim[0],
			'h'      => (int) $dim[1],
		);
	}

	private static function attachment( $id, $widths ) {
		$meta = wp_get_attachment_metadata( $id );
		$mime = get_post_mime_type( $id );
		if ( ! $mime || 0 !== strpos( $mime, 'image/' ) ) {
			return null;
		}
		$full = wp_get_attachment_image_src( $id, 'full' );
		if ( ! $full ) {
			return null;
		}
		$ow = (int) ( isset( $meta['width'] ) ? $meta['width'] : $full[1] );
		$oh = (int) ( isset( $meta['height'] ) ? $meta['height'] : $full[2] );

		// SVG/GIF: serve as is.
		if ( in_array( $mime, array( 'image/svg+xml', 'image/gif' ), true ) || ! $ow ) {
			return array( 'src' => $full[0], 'srcset' => '', 'w' => $ow ? $ow : 300, 'h' => $oh ? $oh : 300 );
		}

		sort( $widths );
		$set = array();
		$src = '';
		$sw  = 0;
		$sh  = 0;
		foreach ( $widths as $w ) {
			$w = min( (int) $w, $ow );
			$c = self::webp_copy( $id, $w );
			if ( ! $c ) {
				$set = array();
				break;
			}
			$set[ $c['w'] ] = $c['url'] . ' ' . $c['w'] . 'w';
			$src            = $c['url'];
			$sw             = $c['w'];
			$sh             = $c['h'];
		}
		if ( $src ) {
			return array(
				'src'    => $src,
				'srcset' => count( $set ) > 1 ? implode( ', ', $set ) : '',
				'w'      => $sw,
				'h'      => $sh,
			);
		}

		// Fallback: WordPress sizes.
		$want = max( $widths );
		$size = $want <= 300 ? 'medium' : ( $want <= 768 ? 'medium_large' : 'large' );
		$img  = wp_get_attachment_image_src( $id, $size );
		if ( ! $img ) {
			$img = $full;
		}
		$srcset = wp_get_attachment_image_srcset( $id, $size );
		return array(
			'src'    => $img[0],
			'srcset' => $srcset ? $srcset : '',
			'w'      => (int) $img[1],
			'h'      => (int) $img[2],
		);
	}

	/** Cached WebP copy of an attachment at a given width. */
	private static function webp_copy( $id, $w ) {
		$file = get_attached_file( $id );
		if ( ! $file || ! file_exists( $file ) ) {
			return null;
		}
		$up   = wp_upload_dir( null, false );
		$dir  = trailingslashit( $up['basedir'] ) . 'yalda-landing';
		$url  = trailingslashit( $up['baseurl'] ) . 'yalda-landing';
		$hash = substr( md5( $file . '|' . filesize( $file ) . '|' . filemtime( $file ) ), 0, 8 );
		$name = $id . '-' . $w . '-' . $hash . '.webp';
		$path = $dir . '/' . $name;

		if ( file_exists( $path ) ) {
			$d = self::dims( $path, $id, $w );
			return array( 'url' => $url . '/' . $name, 'w' => $d[0], 'h' => $d[1] );
		}

		if ( self::$budget <= 0 || ( self::$deadline && microtime( true ) > self::$deadline ) ) {
			return null;
		}
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			return null;
		}
		self::$budget--;

		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}
		$ed = wp_get_image_editor( $file );
		if ( is_wp_error( $ed ) ) {
			return null;
		}
		$size = $ed->get_size();
		if ( $w < $size['width'] ) {
			$ed->resize( $w, null, false );
		}
		$ed->set_quality( 78 );
		$saved = $ed->save( $path, 'image/webp' );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			return null;
		}
		return array( 'url' => $url . '/' . $name, 'w' => (int) $saved['width'], 'h' => (int) $saved['height'] );
	}

	private static function dims( $path, $id, $w ) {
		$k = 'yl_d_' . md5( $path );
		$d = wp_cache_get( $k, 'yl' );
		if ( ! $d ) {
			$s = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$d = $s ? array( (int) $s[0], (int) $s[1] ) : array( $w, $w );
			wp_cache_set( $k, $d, 'yl' );
		}
		return $d;
	}

	/**
	 * <img> tag.
	 *
	 * @param array|null $img   Result of get().
	 * @param array      $attr  alt, class, sizes, lazy (bool), high (bool), width/height override.
	 */
	public static function tag( $img, $attr = array() ) {
		if ( ! $img ) {
			return '';
		}
		$a = wp_parse_args( $attr, array( 'alt' => '', 'class' => '', 'sizes' => '', 'lazy' => true, 'high' => false, 'extra' => '' ) );
		$h = '<img src="' . esc_url( $img['src'] ) . '"';
		if ( ! empty( $img['srcset'] ) ) {
			$h .= ' srcset="' . esc_attr( $img['srcset'] ) . '"';
			if ( $a['sizes'] ) {
				$h .= ' sizes="' . esc_attr( $a['sizes'] ) . '"';
			}
		}
		if ( $a['class'] ) {
			$h .= ' class="' . esc_attr( $a['class'] ) . '"';
		}
		$h .= ' alt="' . esc_attr( $a['alt'] ) . '" width="' . (int) $img['w'] . '" height="' . (int) $img['h'] . '"';
		if ( $a['high'] ) {
			$h .= ' fetchpriority="high"';
		} elseif ( $a['lazy'] ) {
			$h .= ' loading="lazy"';
		}
		$h .= ' decoding="async"' . $a['extra'] . '>';
		return $h;
	}
}
