<?php
/**
 * Landing page renderer.
 *
 * @package TiademTennisLanding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render.
 */
class TDL_Render {

	/**
	 * Products shown on the page (for ItemList schema).
	 *
	 * @var array
	 */
	public static $listed = array();

	/**
	 * Include a template file with variables.
	 *
	 * @param string $file Relative path in templates/.
	 * @param array  $vars Variables.
	 */
	public static function part( $file, $vars = array() ) {
		$path = TDL_DIR . 'templates/' . $file . '.php';
		if ( ! file_exists( $path ) ) {
			return;
		}
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
		include $path;
	}

	/**
	 * Is a section enabled.
	 *
	 * @param string $key Section.
	 * @return bool
	 */
	public static function enabled( $key ) {
		$s = tdl_opt( $key );
		return is_array( $s ) && ! empty( $s['enabled'] );
	}

	/**
	 * Full page (own header/footer).
	 */
	public static function page() {
		echo '<div class="vt-root" id="tdl-root" dir="rtl">';
		self::part( 'partials/background' );
		echo '<div class="vt-content">';
		if ( self::enabled( 'announce' ) ) {
			self::part( 'sections/announce', array( 's' => tdl_opt( 'announce' ) ) );
		}
		self::part( 'partials/header', array( 's' => tdl_opt( 'header' ) ) );
		echo '<main id="tdl-main">';
		self::sections();
		echo '</main>';
		self::part( 'partials/footer', array( 's' => tdl_opt( 'footer' ) ) );
		echo '</div></div>';
		self::overlays( true );
	}

	/**
	 * Embedded mode (theme header/footer or shortcode).
	 *
	 * @return string
	 */
	public static function embedded() {
		ob_start();
		echo '<div class="vt-root vt-embedded" id="tdl-root" dir="rtl">';
		self::part( 'partials/background' );
		echo '<div class="vt-content">';
		self::sections();
		echo '</div></div>';
		self::overlays( false );
		return ob_get_clean();
	}

	/**
	 * Ordered sections.
	 */
	public static function sections() {
		foreach ( (array) tdl_opt( 'order', '', TDL_Defaults::sortable_sections() ) as $key ) {
			if ( ! self::enabled( $key ) ) {
				continue;
			}
			self::part( 'sections/' . $key, array( 's' => tdl_opt( $key ) ) );
		}
	}

	/**
	 * Elements that must live outside the container-query root (fixed positioning).
	 *
	 * @param bool $with_drawer Include the mobile drawer.
	 */
	public static function overlays( $with_drawer ) {
		if ( $with_drawer ) {
			self::part( 'partials/drawer', array( 's' => tdl_opt( 'header' ) ) );
		}
		$footer = tdl_opt( 'footer' );
		if ( ! empty( $footer['bar_enabled'] ) ) {
			self::part( 'partials/mobile-bar', array( 's' => $footer ) );
		}
		self::part( 'partials/overlays' );
	}

	/**
	 * Render a product card.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $ctx     fest|eco|master|grid.
	 * @param int        $index   Position (stagger).
	 * @return string
	 */
	public static function card( $product, $ctx = 'grid', $index = 0 ) {
		$c                                 = TDL_Products::card( $product );
		self::$listed[ $c['id'] ] = $c['url'];
		ob_start();
		self::part(
			'partials/card',
			array(
				'c'     => $c,
				'ctx'   => $ctx,
				'index' => (int) $index,
			)
		);
		return ob_get_clean();
	}

	/**
	 * Map a product_cat term to a grid chip filter key if a chip uses it.
	 *
	 * @param int $term Term id.
	 * @return string
	 */
	public static function chip_for_term( $term ) {
		$term = (int) $term;
		if ( ! $term ) {
			return '';
		}
		foreach ( (array) tdl_opt( 'grid', 'chips', array() ) as $chip ) {
			if ( isset( $chip['filter'] ) && 'cat:' . $term === $chip['filter'] ) {
				return $chip['filter'];
			}
		}
		$woo = tdl_opt( 'woo' );
		if ( $term === (int) $woo['eco_cat'] ) {
			return 'eco';
		}
		if ( $term === (int) $woo['master_cat'] ) {
			return 'master';
		}
		return '';
	}

	/**
	 * Section heading id helper.
	 *
	 * @param string $text Text with newlines.
	 * @return string HTML with <br> on desktop only.
	 */
	public static function multiline( $text ) {
		$lines = array_filter( array_map( 'trim', preg_split( "/\r\n|\n|\r/", (string) $text ) ) );
		return implode( '<br class="vt-br-d"> ', array_map( 'esc_html', $lines ) );
	}
}
