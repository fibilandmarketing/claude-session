<?php
/**
 * Content model: design defaults + what the admin saved.
 *
 * Stored option (NRN_OPTION) shape:
 *   fields    => [ field_key => string | [ 'url' => , 'alt' => , 'id' => ] ]
 *   sections  => [ group_id => 0|1 ]
 *   faq, orgs, videos, provinces => list items
 *   mag       => [ 'source' => latest|category|manual, 'cat' => int, 'more' => string, 'items' => [] ]
 *   map       => [ 'def', 'url', 'count_suffix', 'none_text', 'cta_has', 'cta_none', 'none_url' ]
 *   settings  => [ 'enabled', 'strip', 'hide_tbd', 'hide_phb', 'breakpoint' ]
 */

defined( 'ABSPATH' ) || exit;

class NRN_Content {

	/** @var NRN_Content */
	private static $instance;

	public $schema;
	public $lists;
	public $geo;
	public $data;
	public $layout = 'd';

	public static function get() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function reset_cache() {
		self::$instance = null;
	}

	private function __construct() {
		$this->schema = require NRN_DIR . 'includes/schema-generated.php';
		$this->lists  = require NRN_DIR . 'includes/defaults-lists.php';
		$this->geo    = require NRN_DIR . 'includes/map-geometry.php';
		$saved        = get_option( NRN_OPTION, array() );
		$this->data   = is_array( $saved ) ? $saved : array();
	}

	/* ------------------------------------------------------------ groups */

	public static function groups() {
		return array(
			'settings' => array( 'title' => 'تنظیمات کلی', 'icon' => 'admin-generic', 'toggle' => false ),
			'brand'    => array( 'title' => 'لوگو', 'icon' => 'format-image', 'toggle' => false, 'desc' => 'لوگوی هدر، فوتر و منوی موبایل.' ),
			'header'   => array( 'title' => 'هدر و منو', 'icon' => 'menu', 'toggle' => false ),
			'hero'     => array( 'title' => 'هیرو (بخش اول)', 'icon' => 'cover-image', 'toggle' => false ),
			'why'      => array( 'title' => 'چرا نورون: ضمانت‌ها، آمار، سازمان‌ها', 'icon' => 'shield', 'toggle' => true ),
			'choose'   => array( 'title' => 'انتخاب بر اساس نیاز', 'icon' => 'screenoptions', 'toggle' => true ),
			'compare'  => array( 'title' => 'مقایسهٔ S350 و S600', 'icon' => 'columns', 'toggle' => true ),
			'personal' => array( 'title' => 'ردیابی وسایل شخصی', 'icon' => 'tag', 'toggle' => true ),
			'heavy'    => array( 'title' => 'خودروی سنگین', 'icon' => 'car', 'toggle' => true ),
			'cold'     => array( 'title' => 'خودروی یخچال‌دار', 'icon' => 'cloud', 'toggle' => true ),
			'theft'    => array( 'title' => 'سناریوی سرقت (اپلیکیشن)', 'icon' => 'smartphone', 'toggle' => true ),
			'videos'   => array( 'title' => 'ویدئوی مشتریان', 'icon' => 'video-alt3', 'toggle' => true ),
			'city'     => array( 'title' => 'خرید و نصب + نقشهٔ نمایندگی‌ها', 'icon' => 'location', 'toggle' => true ),
			'fleet'    => array( 'title' => 'سازمان‌ها و ناوگان', 'icon' => 'building', 'toggle' => true ),
			'faq'      => array( 'title' => 'سؤالات متداول', 'icon' => 'editor-help', 'toggle' => true ),
			'mag'      => array( 'title' => 'مجلهٔ نورون', 'icon' => 'welcome-write-blog', 'toggle' => true ),
			'footer'   => array( 'title' => 'فوتر', 'icon' => 'align-wide', 'toggle' => false ),
			'social'   => array( 'title' => 'شبکه‌های اجتماعی', 'icon' => 'share', 'toggle' => false, 'desc' => 'در فوتر و منوی موبایل استفاده می‌شود.' ),
			'drawer'   => array( 'title' => 'منوی کشویی موبایل', 'icon' => 'smartphone', 'toggle' => false ),
			'todo'     => array( 'title' => 'فهرست موارد تکمیل‌نشده', 'icon' => 'warning', 'toggle' => true, 'desc' => 'کادر زرد انتهای صفحه که در طرح برای بررسی کارفرما گذاشته شده است.' ),
		);
	}

	/* ---------------------------------------------------- field access */

	public function field_def( $key ) {
		$gid = strstr( $key, '.', true );
		return isset( $this->schema[ $gid ][ $key ] ) ? $this->schema[ $gid ][ $key ] : null;
	}

	public function raw( $key ) {
		if ( isset( $this->data['fields'][ $key ] ) ) {
			return $this->data['fields'][ $key ];
		}
		$def = $this->field_def( $key );
		if ( ! $def ) {
			return '';
		}
		if ( 'img' === $def['type'] ) {
			return array( 'url' => '', 'alt' => isset( $def['alt'] ) ? $def['alt'] : '', 'id' => 0 );
		}
		return $def['default'];
	}

	public function text( $key ) {
		$v = $this->raw( $key );
		return is_string( $v ) ? $v : '';
	}

	public function t( $key ) {
		echo esc_html( $this->text( $key ) );
	}

	public function has( $key ) {
		return '' !== trim( $this->text( $key ) );
	}

	public static function asset( $rel ) {
		if ( '' === $rel || preg_match( '#^(https?:)?//#', $rel ) ) {
			return $rel;
		}
		return NRN_URL . 'assets/' . ltrim( $rel, '/' );
	}

	public function img_url( $key ) {
		$v = $this->raw( $key );
		if ( is_array( $v ) && ! empty( $v['url'] ) ) {
			return $v['url'];
		}
		$def = $this->field_def( $key );
		return $def ? self::asset( $def['default'] ) : '';
	}

	public function img( $key ) {
		echo esc_url( $this->img_url( $key ) );
	}

	public function alt( $key ) {
		$v   = $this->raw( $key );
		$alt = is_array( $v ) && isset( $v['alt'] ) ? $v['alt'] : '';
		echo esc_attr( $alt );
	}

	public function url( $key ) {
		echo esc_url( $this->text( $key ) );
	}

	/* --------------------------------------------------------- sections */

	public function on( $gid ) {
		if ( isset( $this->data['sections'][ $gid ] ) ) {
			return (bool) $this->data['sections'][ $gid ];
		}
		return true;
	}

	/* ------------------------------------------------------------ lists */

	public function items( $list ) {
		if ( isset( $this->data[ $list ] ) && is_array( $this->data[ $list ] ) && 'mag' !== $list ) {
			return $this->data[ $list ];
		}
		return $this->lists[ $list ];
	}

	public function mag() {
		$m = $this->lists['mag'];
		if ( isset( $this->data['mag'] ) && is_array( $this->data['mag'] ) ) {
			$m = array_merge( $m, $this->data['mag'] );
		}
		return $m;
	}

	/** Magazine cards for the front end: real posts or the manual cards. */
	public function mag_cards() {
		$m      = $this->mag();
		$manual = array();
		foreach ( $m['items'] as $it ) {
			$manual[] = array(
				'url'   => $it['url'],
				'img'   => self::asset( $it['img'] ),
				'alt'   => $it['alt'],
				'phb'   => $it['phb'],
				'label' => $it['label'],
				'title' => $it['title'],
			);
		}
		if ( 'manual' === $m['source'] ) {
			return $manual;
		}
		$args = array(
			'post_type'           => 'post',
			'posts_per_page'      => 3,
			'post_status'         => 'publish',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		if ( 'category' === $m['source'] && ! empty( $m['cat'] ) ) {
			$args['cat'] = (int) $m['cat'];
		}
		$posts = get_posts( $args );
		if ( ! $posts ) {
			return $manual;
		}
		$cards = array();
		foreach ( $posts as $i => $p ) {
			$thumb = get_the_post_thumbnail_url( $p, 'medium_large' );
			$cats  = get_the_category( $p->ID );
			$fb    = isset( $manual[ $i ] ) ? $manual[ $i ] : $manual[0];
			$cards[] = array(
				'url'   => get_permalink( $p ),
				'img'   => $thumb ? $thumb : $fb['img'],
				'alt'   => $thumb ? get_the_title( $p ) : $fb['alt'],
				'phb'   => $thumb ? '' : $fb['phb'],
				'label' => $cats ? $cats[0]->name : '',
				'title' => get_the_title( $p ),
			);
		}
		return $cards;
	}

	/* -------------------------------------------------------------- map */

	public function map_cfg() {
		$d = array(
			'def'          => 'tehran',
			'url'          => 'https://noroncar.ir/namayandegi/',
			'count_suffix' => 'نمایندگی فعال',
			'none_text'    => 'هنوز نمایندگی در این استان ثبت نشده است',
			'cta_has'      => 'مشاهدهٔ نمایندگی‌ها',
			'cta_none'     => 'تماس با نورون',
			'none_url'     => 'tel:05152220010',
		);
		if ( isset( $this->data['map'] ) && is_array( $this->data['map'] ) ) {
			$d = array_merge( $d, array_filter( $this->data['map'], 'strlen' ) );
		}
		return $d;
	}

	public function provinces() {
		$list = $this->items( 'provinces' );
		$out  = array();
		foreach ( $list as $p ) {
			if ( isset( $this->geo['paths'][ $p['slug'] ] ) ) {
				$out[ $p['slug'] ] = $p;
			}
		}
		// keep every province of the map, even if the saved list misses one
		foreach ( $this->lists['provinces'] as $p ) {
			if ( ! isset( $out[ $p['slug'] ] ) ) {
				$out[ $p['slug'] ] = $p;
			}
		}
		return $out;
	}

	public function province( $slug ) {
		$all = $this->provinces();
		return isset( $all[ $slug ] ) ? $all[ $slug ] : reset( $all );
	}

	/** Heat colour of a province, on the design's scale (0 -> dark, max -> orange). */
	public function province_fill( $n, $max ) {
		$scale = $this->geo['fills'];
		if ( $n <= 0 || $max <= 0 ) {
			return $scale[0][1];
		}
		// the design's anchors are relative to its maximum (13)
		$dmax = end( $scale );
		$dmax = $dmax[0];
		$x    = $n / $max * $dmax;
		$prev = $scale[1];
		foreach ( $scale as $i => $pt ) {
			if ( 0 === $i ) {
				continue;
			}
			if ( $x <= $pt[0] ) {
				if ( $pt[0] === $prev[0] ) {
					return $pt[1];
				}
				$f = max( 0, ( $x - $prev[0] ) / ( $pt[0] - $prev[0] ) );
				return self::mix( $prev[1], $pt[1], $f );
			}
			$prev = $pt;
		}
		return $prev[1];
	}

	private static function mix( $a, $b, $f ) {
		$a = array_map( 'hexdec', str_split( ltrim( $a, '#' ), 2 ) );
		$b = array_map( 'hexdec', str_split( ltrim( $b, '#' ), 2 ) );
		$c = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$c .= sprintf( '%02X', (int) round( $a[ $i ] + ( $b[ $i ] - $a[ $i ] ) * $f ) );
		}
		return $c;
	}

	public static function fa( $n ) {
		return strtr( (string) $n, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}

	public function map_name() {
		$cfg = $this->map_cfg();
		$p   = $this->province( $cfg['def'] );
		echo esc_html( $p['name'] );
	}

	public function map_count() {
		$cfg = $this->map_cfg();
		$p   = $this->province( $cfg['def'] );
		$n   = (int) $p['n'];
		echo esc_html( $n ? self::fa( $n ) . ' ' . $cfg['count_suffix'] : $cfg['none_text'] );
	}

	/* ---------------------------------------------------------- settings */

	public function setting( $k ) {
		$d = array(
			'enabled'    => 0,
			'strip'      => 'theme',
			'hide_tbd'   => 0,
			'hide_phb'   => 0,
			'breakpoint' => 768,
		);
		$s = isset( $this->data['settings'] ) && is_array( $this->data['settings'] ) ? array_merge( $d, $this->data['settings'] ) : $d;
		return isset( $s[ $k ] ) ? $s[ $k ] : null;
	}

	/* --------------------------------------------------------- partials */

	public function partial( $name, $layout ) {
		$fn = 'nrn_partial_' . $name;
		if ( function_exists( $fn ) ) {
			$fn( $this, $layout );
		}
	}
}
