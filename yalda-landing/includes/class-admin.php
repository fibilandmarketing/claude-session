<?php
/**
 * Admin: settings screen (section by section), AJAX save, product search, page creation.
 */

defined( 'ABSPATH' ) || exit;

class YL_Admin {

	const SLUG = 'yalda-landing';

	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_yl_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_yl_products', array( __CLASS__, 'ajax_products' ) );
		add_action( 'wp_ajax_yl_cat_products', array( __CLASS__, 'ajax_cat_products' ) );
		add_action( 'wp_ajax_yl_create_page', array( __CLASS__, 'ajax_create_page' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'post_state' ), 10, 2 );
	}

	public static function menu() {
		$n     = YL_Leads::new_count();
		$badge = $n ? ' <span class="awaiting-mod">' . (int) $n . '</span>' : '';
		add_menu_page( 'لندینگ شب یلدا', 'لندینگ یلدا' . $badge, 'manage_options', self::SLUG, array( __CLASS__, 'render' ), 'dashicons-star-filled', 58 );
		self::$hook = add_submenu_page( self::SLUG, 'تنظیمات لندینگ شب یلدا', 'تنظیمات لندینگ', 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function post_state( $states, $post ) {
		if ( (int) YL_Settings::get( 'general', 'page_id', 0 ) === (int) $post->ID ) {
			$states['yl'] = 'لندینگ شب یلدا';
		}
		return $states;
	}

	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( $screen && YL_Leads::TYPE === $screen->post_type ) {
			wp_enqueue_style( 'yl-admin', YL_URL . 'assets/css/admin.css', array(), YL_VERSION . '.' . filemtime( YL_DIR . 'assets/css/admin.css' ) );
		}
		if ( $hook !== self::$hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'yl-admin', YL_URL . 'assets/css/admin.css', array(), YL_VERSION . '.' . filemtime( YL_DIR . 'assets/css/admin.css' ) );
		wp_enqueue_script( 'yl-admin', YL_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), YL_VERSION . '.' . filemtime( YL_DIR . 'assets/js/admin.js' ), true );
		wp_localize_script( 'yl-admin', 'YLA', array(
			'ajax'   => admin_url( 'admin-ajax.php' ),
			'nonce'  => wp_create_nonce( 'yl_admin' ),
			'woo'    => yl_woo(),
			'img'    => YL_URL . 'assets/img/',
			'months' => array_values( yl_months() ),
		) );
	}

	/* ------------------------------------------------------------------ AJAX */

	private static function guard() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'yl_admin', 'nonce', false ) ) {
			wp_send_json_error( array( 'msg' => 'نشست شما منقضی شده؛ صفحه را تازه کنید.' ), 403 );
		}
	}

	public static function ajax_save() {
		self::guard();
		$raw  = isset( $_POST['data'] ) ? json_decode( wp_unslash( $_POST['data'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'msg' => 'داده‌ای دریافت نشد.' ), 400 );
		}
		$data = YL_Schema::sanitize( wp_slash( $raw ) );
		YL_Settings::save( $data );
		self::warm();
		wp_send_json_success( array(
			'msg'    => 'تغییرات ذخیره شد.',
			'status' => self::status_html(),
		) );
	}

	/** Prepare WebP copies right after saving, so visitors never wait for them. */
	private static function warm() {
		YL_Images::unlimited( 15 );
		$F    = 'YL_Frontend';
		$list = array(
			array( 'general', 'logo', array( 120 ) ),
			array( 'general', 'bg', array( 1200 ) ),
			array( 'hero', 'title_img', array( 720, 1200 ) ),
			array( 'hero', 'still_img', array( 600, 1000 ) ),
			array( 'build', 'deco_img', array( 320 ) ),
			array( 'footer', 'seal_img', array( 150 ) ),
		);
		foreach ( array( 'yaldaf', 'build', 'recent', 'proof', 'fal', 'faq' ) as $s ) {
			$list[] = array( $s, 'title_img', array( 480, 840 ) );
		}
		foreach ( $list as $l ) {
			YL_Images::get( $F::img_value( $l[0], $l[1] ), $l[2] );
		}
		YL_Data::deals();
		YL_Data::catalog();
		YL_Data::recent();
		foreach ( (array) YL_Settings::get( 'proof', 'logos', array() ) as $lg ) {
			if ( ! empty( $lg['img'] ) ) {
				YL_Images::get( $lg['img'], array( 300 ) );
			}
		}
	}

	private static function product_info( $pid ) {
		if ( ! yl_woo() || ! $pid ) {
			return null;
		}
		$p = wc_get_product( $pid );
		if ( ! $p ) {
			return array( 'id' => (int) $pid, 'name' => 'محصول حذف شده (#' . (int) $pid . ')', 'price' => '', 'img' => '', 'warn' => 'این محصول دیگر وجود ندارد و نمایش داده نمی‌شود.' );
		}
		$f    = yl_toman_factor();
		$warn = '';
		if ( 'publish' !== $p->get_status() ) {
			$warn = 'محصول منتشر نشده و در لندینگ نمایش داده نمی‌شود.';
		} elseif ( ! $p->is_in_stock() ) {
			$warn = 'ناموجود است؛ دکمه‌ی پرداخت آنلاین برایش نمایش داده نمی‌شود.';
		} elseif ( ! $p->is_type( 'simple' ) ) {
			$warn = 'محصول ساده نیست؛ برای پرداخت به صفحه‌ی محصول هدایت می‌شود.';
		}
		$price = yl_money( (float) $p->get_price() * $f ) . ' تومان';
		if ( $p->is_on_sale() && (float) $p->get_regular_price() > (float) $p->get_price() ) {
			$price = '<s>' . yl_money( (float) $p->get_regular_price() * $f ) . '</s> ' . $price;
		}
		$img = wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' );
		return array(
			'id'    => $p->get_id(),
			'name'  => $p->get_name(),
			'price' => $price,
			'img'   => $img ? $img : ( function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src( 'thumbnail' ) : '' ),
			'warn'  => $warn,
		);
	}

	public static function ajax_products() {
		self::guard();
		if ( ! yl_woo() ) {
			wp_send_json_success( array() );
		}
		$q    = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		$args = array( 'limit' => 20, 'status' => array( 'publish', 'private', 'draft' ), 'orderby' => 'date', 'order' => 'DESC', 'return' => 'ids' );
		$ids  = array();
		if ( '' !== $q ) {
			if ( ctype_digit( yl_en( $q ) ) && wc_get_product( (int) yl_en( $q ) ) ) {
				$ids[] = (int) yl_en( $q );
			}
			$ds  = WC_Data_Store::load( 'product' );
			$ids = array_merge( $ids, array_slice( (array) $ds->search_products( $q, '', false, true, 20 ), 0, 20 ) );
		} else {
			$ids = wc_get_products( $args );
		}
		$out = array();
		foreach ( array_unique( array_filter( $ids ) ) as $id ) {
			$info = self::product_info( $id );
			if ( $info && empty( $info['missing'] ) ) {
				$out[] = $info;
			}
		}
		wp_send_json_success( array_slice( $out, 0, 20 ) );
	}

	public static function ajax_cat_products() {
		self::guard();
		if ( ! yl_woo() ) {
			wp_send_json_success( array() );
		}
		$cat  = isset( $_POST['cat'] ) ? absint( $_POST['cat'] ) : 0;
		$term = $cat ? get_term( $cat, 'product_cat' ) : null;
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'msg' => 'دسته را انتخاب کنید.' ) );
		}
		$ids = wc_get_products( array( 'category' => array( $term->slug ), 'status' => 'publish', 'limit' => 60, 'return' => 'ids' ) );
		$out = array();
		foreach ( $ids as $id ) {
			$out[] = self::product_info( $id );
		}
		wp_send_json_success( $out );
	}

	public static function ajax_create_page() {
		self::guard();
		$id = wp_insert_post( array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'هدیه سازمانی شب یلدا',
			'post_name'   => 'yalda',
		), true );
		if ( is_wp_error( $id ) ) {
			wp_send_json_error( array( 'msg' => $id->get_error_message() ) );
		}
		$all                       = YL_Settings::all();
		$saved                     = get_option( YL_OPTION, array() );
		$saved                     = is_array( $saved ) ? $saved : array();
		$saved['general']          = isset( $saved['general'] ) ? $saved['general'] : array();
		$saved['general']['page_id'] = (int) $id;
		YL_Settings::save( YL_Settings::merge( $all, $saved ) );
		wp_send_json_success( array(
			'id'     => (int) $id,
			'title'  => get_the_title( $id ),
			'url'    => get_permalink( $id ),
			'status' => self::status_html(),
		) );
	}

	/* ------------------------------------------------------------------ page */

	private static function icon( $name ) {
		$map = array(
			'home' => 'admin-home', 'layout' => 'layout', 'hero' => 'cover-image', 'tag' => 'tag', 'form' => 'feedback',
			'box' => 'archive', 'calc' => 'calculator', 'history' => 'backup', 'quote' => 'format-quote', 'book' => 'book-alt',
			'doc' => 'media-text', 'help' => 'editor-help', 'flag' => 'flag', 'inbox' => 'email-alt', 'page' => 'admin-page',
			'brand' => 'store', 'clock' => 'clock', 'search' => 'search', 'layers' => 'excerpt-view', 'palette' => 'art',
			'sparkle' => 'star-filled', 'bolt' => 'performance', 'menu' => 'menu-alt', 'image' => 'format-image', 'text' => 'editor-paragraph',
			'heading' => 'heading', 'cart' => 'cart', 'list' => 'editor-ol', 'user' => 'id', 'check' => 'yes-alt', 'mobile' => 'smartphone',
			'users' => 'groups', 'stairs' => 'chart-bar', 'cursor' => 'button', 'swap' => 'randomize', 'footer' => 'align-wide', 'bell' => 'bell',
		);
		return '<span class="dashicons dashicons-' . esc_attr( isset( $map[ $name ] ) ? $map[ $name ] : 'admin-generic' ) . '" aria-hidden="true"></span>';
	}

	private static function status_html() {
		YL_Settings::flush();
		$id = (int) YL_Settings::get( 'general', 'page_id', 0 );
		$p  = $id ? get_post( $id ) : null;
		if ( ! $p || 'page' !== $p->post_type || 'trash' === $p->post_status ) {
			return '<span class="yl-pill is-warn">' . self::icon( 'flag' ) . 'هنوز صفحه‌ای برای لندینگ انتخاب نشده</span>'
				. '<a class="yl-btn yl-btn-ghost" href="#tab=start">انتخاب صفحه</a>';
		}
		$draft = 'publish' !== $p->post_status;
		$url   = $draft ? get_preview_post_link( $p ) : get_permalink( $p );
		return '<span class="yl-pill ' . ( $draft ? 'is-warn' : 'is-ok' ) . '">' . self::icon( $draft ? 'flag' : 'check' )
			. esc_html( ( $draft ? 'صفحه‌ی لندینگ منتشر نشده: ' : 'لندینگ روی صفحه‌ی ' ) . '«' . get_the_title( $p ) . '»' . ( $draft ? '' : ' فعال است' ) ) . '</span>'
			. '<a class="yl-btn yl-btn-ghost" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">مشاهده‌ی لندینگ <span class="dashicons dashicons-external" aria-hidden="true"></span></a>';
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$S    = YL_Settings::all();
		$tabs = YL_Schema::tabs();
		?>
		<div class="wrap yl-admin" dir="rtl">
			<h1 class="screen-reader-text">لندینگ شب یلدا</h1>
			<div class="yl-head">
				<div class="yl-brand">
					<span class="yl-moon" aria-hidden="true"><img src="<?php echo esc_url( YL_URL . 'assets/img/moon.webp' ); ?>" alt=""></span>
					<div><b>لندینگ شب یلدا</b><small>هر بخش صفحه را از منوی کنار انتخاب و تنظیم کنید؛ در پایان «ذخیره» را بزنید.</small></div>
				</div>
				<div class="yl-status" id="yl-status"><?php echo self::status_html(); // phpcs:ignore ?></div>
				<div class="yl-actions">
					<span class="yl-dirty" id="yl-dirty" hidden>تغییرات ذخیره نشده</span>
					<button type="button" class="yl-btn yl-btn-primary" id="yl-save"><span class="dashicons dashicons-saved" aria-hidden="true"></span>ذخیره‌ی تغییرات</button>
				</div>
			</div>

			<div class="yl-body">
				<nav class="yl-side" aria-label="بخش‌های تنظیمات">
					<?php
					$group = '';
					foreach ( $tabs as $t ) {
						if ( $t['group'] !== $group ) {
							$group = $t['group'];
							echo '<p class="yl-side-g">' . esc_html( $group ) . '</p>';
						}
						$dot = ! empty( $t['toggle'] ) ? '<span class="yl-dot' . ( YL_Settings::on( $t['sec'] ) ? ' is-on' : '' ) . '" data-dot="' . esc_attr( $t['sec'] ) . '" title="روشن/خاموش"></span>' : '';
						if ( 'leads' === $t['id'] ) {
							$n   = YL_Leads::new_count();
							$dot = $n ? '<span class="yl-count-b">' . esc_html( yl_fa( $n ) ) . '</span>' : '';
						}
						echo '<a class="yl-side-i" href="#tab=' . esc_attr( $t['id'] ) . '" data-tab="' . esc_attr( $t['id'] ) . '">' . self::icon( $t['icon'] ) . '<span>' . esc_html( $t['title'] ) . '</span>' . $dot . '</a>'; // phpcs:ignore
					}
					?>
				</nav>

				<form class="yl-main" id="yl-form" autocomplete="off" onsubmit="return false">
					<?php foreach ( $tabs as $t ) : ?>
					<section class="yl-panel" data-panel="<?php echo esc_attr( $t['id'] ); ?>" hidden>
						<header class="yl-panel-h">
							<div>
								<h2><?php echo self::icon( $t['icon'] ) . esc_html( $t['title'] ); // phpcs:ignore ?></h2>
								<p><?php echo esc_html( $t['desc'] ); ?></p>
							</div>
							<?php if ( ! empty( $t['toggle'] ) ) : ?>
							<label class="yl-sw yl-sw-big"><input type="checkbox" data-proxy="<?php echo esc_attr( $t['sec'] ); ?>"<?php checked( YL_Settings::on( $t['sec'] ) ); ?>><span class="yl-sw-ui"></span><span class="yl-sw-l">نمایش این بخش در صفحه</span></label>
							<?php endif; ?>
						</header>
						<?php
						foreach ( $t['cards'] as $card ) {
							$sec = isset( $card['sec'] ) ? $card['sec'] : $t['sec'];
							echo '<div class="yl-card">';
							echo '<h3 class="yl-card-t">' . self::icon( $card['icon'] ) . esc_html( $card['title'] ) . '</h3>'; // phpcs:ignore
							if ( ! empty( $card['desc'] ) ) {
								echo '<p class="yl-card-d">' . esc_html( $card['desc'] ) . '</p>';
							}
							if ( ! empty( $card['status'] ) ) {
								self::checklist();
							}
							if ( ! empty( $card['leads'] ) ) {
								self::leads_box();
							}
							if ( ! empty( $card['bulk'] ) ) {
								self::bulk_box();
							}
							echo '<div class="yl-grid">';
							foreach ( $card['fields'] as $f ) {
								$fsec = isset( $f['sec'] ) ? $f['sec'] : $sec;
								$val  = isset( $f['k'], $S[ $fsec ][ $f['k'] ] ) ? $S[ $fsec ][ $f['k'] ] : null;
								self::field( $f, 'yl[' . $fsec . '][' . ( isset( $f['k'] ) ? $f['k'] : '' ) . ']', $val, $fsec, 0 );
							}
							echo '</div></div>';
						}
						?>
					</section>
					<?php endforeach; ?>
				</form>
			</div>
			<div class="yl-toast" id="yl-toast" role="status" aria-live="polite"></div>
		</div>
		<?php
	}

	private static function checklist() {
		$id    = (int) YL_Settings::get( 'general', 'page_id', 0 );
		$p     = $id ? get_post( $id ) : null;
		$items = array();
		if ( $p && 'publish' === $p->post_status ) {
			$items[] = array( 'ok', 'صفحه‌ی لندینگ منتشر شده است.' );
		} elseif ( $p ) {
			$items[] = array( 'warn', 'صفحه‌ی انتخاب‌شده هنوز منتشر نشده است.' );
		} else {
			$items[] = array( 'warn', 'یک صفحه انتخاب کنید یا با دکمه‌ی «ساخت صفحه‌ی جدید» بسازید.' );
		}
		$items[] = yl_woo()
			? array( 'ok', 'ووکامرس فعال است (واحد پول فروشگاه: ' . get_woocommerce_currency() . '؛ قیمت‌ها در لندینگ به تومان نمایش داده می‌شوند).' )
			: array( 'info', 'ووکامرس فعال نیست؛ پک‌ها را به‌صورت دستی وارد کنید.' );
		$items[] = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) )
			? array( 'ok', 'تصاویری که انتخاب می‌کنید خودکار به WebP سبک و اندازه‌ی مناسب تبدیل می‌شوند.' )
			: array( 'info', 'سرور از ساخت WebP پشتیبانی نمی‌کند؛ از اندازه‌های معمول وردپرس استفاده می‌شود.' );
		echo '<ul class="yl-check">';
		foreach ( $items as $i ) {
			echo '<li class="is-' . esc_attr( $i[0] ) . '">' . esc_html( $i[1] ) . '</li>';
		}
		echo '</ul>';
	}

	private static function leads_box() {
		$total = (int) wp_count_posts( YL_Leads::TYPE )->publish;
		$new   = YL_Leads::new_count();
		echo '<div class="yl-leads">';
		echo '<div class="yl-stat"><b>' . esc_html( yl_fa( $total ) ) . '</b><span>کل درخواست‌ها</span></div>';
		echo '<div class="yl-stat is-new"><b>' . esc_html( yl_fa( $new ) ) . '</b><span>درخواست جدید</span></div>';
		echo '<div class="yl-leads-a"><a class="yl-btn yl-btn-primary" href="' . esc_url( admin_url( 'edit.php?post_type=' . YL_Leads::TYPE ) ) . '">مشاهده‌ی درخواست‌ها</a>';
		echo '<a class="yl-btn yl-btn-ghost" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=yl_export' ), 'yl_export' ) ) . '">دانلود فایل اکسل (CSV)</a></div>';
		echo '</div>';
	}

	private static function bulk_box() {
		if ( ! yl_woo() ) {
			return;
		}
		echo '<div class="yl-bulk"><span>' . self::icon( 'cart' ) . 'افزودن گروهی از ووکامرس:</span>'; // phpcs:ignore
		self::cat_select( 'yl-bulk-cat', '', 0, '— یک دسته انتخاب کنید —' );
		echo '<button type="button" class="yl-btn yl-btn-ghost" id="yl-bulk-add">افزودن همه‌ی محصولات این دسته</button></div>';
	}

	private static function cat_select( $id, $name, $val, $empty ) {
		echo '<select class="yl-in" id="' . esc_attr( $id ) . '"' . ( $name ? ' name="' . esc_attr( $name ) . '"' : '' ) . '><option value="0">' . esc_html( $empty ) . '</option>';
		if ( taxonomy_exists( 'product_cat' ) ) {
			$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					echo '<option value="' . (int) $t->term_id . '"' . selected( (int) $val, (int) $t->term_id, false ) . '>' . esc_html( $t->name . ' (' . yl_fa( $t->count ) . ')' ) . '</option>';
				}
			}
		}
		echo '</select>';
	}

	private static function img_url( $v ) {
		if ( is_string( $v ) && 0 === strpos( $v, 'b:' ) ) {
			return YL_URL . 'assets/img/' . substr( $v, 2 ) . '.webp';
		}
		$id = absint( $v );
		if ( $id ) {
			$u = wp_get_attachment_image_url( $id, 'medium' );
			return $u ? $u : '';
		}
		return '';
	}

	/* ------------------------------------------------------------------ fields */

	private static function fid( $name ) {
		return 'yl_' . trim( preg_replace( '/[^A-Za-z0-9_]+/', '_', $name ), '_' );
	}

	private static function field( $f, $name, $val, $sec, $depth ) {
		$t    = $f['t'];
		$k    = isset( $f['k'] ) ? $f['k'] : '';
		$id   = self::fid( $name );
		$w    = isset( $f['w'] ) ? $f['w'] : 'full';
		$show = isset( $f['show'] ) ? ' data-show="' . esc_attr( $f['show'] ) . '"' : '';
		$lab  = isset( $f['l'] ) ? $f['l'] : '';
		$ph   = isset( $f['ph'] ) ? ' placeholder="' . esc_attr( $f['ph'] ) . '"' : '';

		if ( 'key' === $t ) {
			echo '<input type="hidden" class="yl-key" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $val ) . '">';
			return;
		}
		if ( 'note' === $t ) {
			echo '<div class="yl-f yl-w-full"' . $show . '><p class="yl-note">' . esc_html( $lab ) . '</p></div>'; // phpcs:ignore
			return;
		}

		echo '<div class="yl-f yl-w-' . esc_attr( $w ) . ' yl-t-' . esc_attr( $t ) . '" data-k="' . esc_attr( $k ) . '"' . $show . '>'; // phpcs:ignore
		if ( $lab && ! in_array( $t, array( 'toggle' ), true ) ) {
			echo '<label class="yl-l" for="' . esc_attr( $id ) . '">' . esc_html( $lab ) . '</label>';
		}

		switch ( $t ) {
			case 'text':
			case 'tel':
			case 'email':
			case 'url':
				$type = 'text' === $t ? 'text' : $t;
				$dir  = in_array( $t, array( 'tel', 'email', 'url' ), true ) ? ' dir="ltr"' : '';
				echo '<input class="yl-in" type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $val ) . '"' . $ph . $dir . '>'; // phpcs:ignore
				break;
			case 'money':
				$v = '' === (string) $val || null === $val ? '' : number_format( (float) $val );
				echo '<div class="yl-money"><input class="yl-in" type="text" inputmode="numeric" dir="ltr" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '"' . $ph . '><span>تومان</span></div>'; // phpcs:ignore
				echo '<small class="yl-words" data-words></small>';
				break;
			case 'number':
				echo '<input class="yl-in" type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $val ) . '"'
					. ( isset( $f['min'] ) ? ' min="' . esc_attr( $f['min'] ) . '"' : '' )
					. ( isset( $f['max'] ) ? ' max="' . esc_attr( $f['max'] ) . '"' : '' )
					. ' step="' . esc_attr( isset( $f['step'] ) ? $f['step'] : 1 ) . '" dir="ltr">';
				break;
			case 'textarea':
				echo '<textarea class="yl-in" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $ph . '>' . esc_textarea( (string) $val ) . '</textarea>'; // phpcs:ignore
				break;
			case 'code':
				echo '<textarea class="yl-in yl-code" rows="3" dir="ltr" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $val ) . '</textarea>';
				break;
			case 'editor':
				wp_editor( (string) $val, 'yl_seo_content', array(
					'textarea_name' => $name,
					'textarea_rows' => 14,
					'media_buttons' => false,
					'teeny'         => false,
					'quicktags'     => true,
					'tinymce'       => array( 'block_formats' => 'پاراگراف=p;سربرگ ۳=h3', 'toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo', 'toolbar2' => '' ),
				) );
				break;
			case 'toggle':
				echo '<label class="yl-sw"><input type="hidden" name="' . esc_attr( $name ) . '" value="0"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $val ), true, false ) . '><span class="yl-sw-ui"></span><span class="yl-sw-l">' . esc_html( $lab ) . '</span></label>';
				break;
			case 'select':
				echo '<select class="yl-in" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( $f['o'] as $ov => $ol ) {
					echo '<option value="' . esc_attr( $ov ) . '"' . selected( (string) $val, (string) $ov, false ) . '>' . esc_html( $ol ) . '</option>';
				}
				echo '</select>';
				break;
			case 'seg':
				$cur = null === $val || '' === $val ? ( isset( $f['def'] ) ? $f['def'] : key( $f['o'] ) ) : $val;
				if ( 'src' === $k && ! yl_woo() && null === $val ) {
					$cur = 'manual';
				}
				echo '<div class="yl-seg" role="radiogroup">';
				foreach ( $f['o'] as $ov => $ol ) {
					$dis = ( 'woo' === $ov && ! yl_woo() ) ? ' disabled' : '';
					echo '<label><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $ov ) . '"' . checked( (string) $cur, (string) $ov, false ) . $dis . '><span>' . esc_html( $ol ) . '</span></label>'; // phpcs:ignore
				}
				echo '</div>';
				break;
			case 'image':
				$def  = ! empty( $f['def'] ) ? YL_Defaults::image( $sec, $k ) : '';
				$url  = self::img_url( $val );
				$isdf = ! $url && $def;
				if ( $isdf ) {
					$url = self::img_url( $def );
				}
				echo '<div class="yl-img' . ( $url ? ' has-img' : '' ) . ( $isdf ? ' is-def' : '' ) . '" data-def="' . esc_attr( $def ? self::img_url( $def ) : '' ) . '">';
				echo '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $val ) . '">';
				echo '<button type="button" class="yl-img-prev" aria-label="انتخاب تصویر">' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="">' : '<span class="dashicons dashicons-format-image"></span>' ) . '</button>';
				echo '<div class="yl-img-a"><button type="button" class="yl-btn yl-btn-ghost yl-img-pick">' . ( $url ? 'تغییر تصویر' : 'انتخاب تصویر' ) . '</button>';
				echo $def ? '<button type="button" class="yl-link yl-img-def">بازگشت به تصویر طرح</button>' : '<button type="button" class="yl-link yl-img-del">حذف تصویر</button>';
				echo '<span class="yl-img-badge">تصویر پیش‌فرض طرح</span></div>';
				echo '</div>';
				break;
			case 'color':
				echo '<div class="yl-color"><input type="color" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $val ) . '"><code>' . esc_html( strtoupper( (string) $val ) ) . '</code></div>';
				break;
			case 'product':
				if ( ! yl_woo() ) {
					echo '<p class="yl-note">ووکامرس فعال نیست؛ منبع «ورود دستی» را انتخاب کنید.</p>';
					echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $val ) . '">';
					break;
				}
				$info = self::product_info( (int) $val );
				echo '<div class="yl-prod' . ( $info ? ' has-prod' : '' ) . '">';
				echo '<input type="hidden" class="yl-prod-v" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $val ) . '">';
				echo '<div class="yl-prod-sel">' . ( $info ? self::prod_card( $info ) : '' ) . '</div>';
				echo '<div class="yl-prod-find"><span class="dashicons dashicons-search" aria-hidden="true"></span><input type="search" class="yl-in yl-prod-q" placeholder="نام محصول یا شناسه را بنویسید…" aria-label="جستجوی محصول"><div class="yl-prod-res" role="listbox"></div></div>';
				echo '</div>';
				break;
			case 'page':
				$pages = get_pages( array( 'post_status' => array( 'publish', 'draft', 'private', 'pending' ), 'sort_column' => 'post_title' ) );
				echo '<div class="yl-page"><select class="yl-in" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"><option value="0">— صفحه‌ای انتخاب نشده —</option>';
				foreach ( $pages as $pg ) {
					$st = 'publish' === $pg->post_status ? '' : ' (' . ( 'draft' === $pg->post_status ? 'پیش‌نویس' : 'منتشرنشده' ) . ')';
					echo '<option value="' . (int) $pg->ID . '"' . selected( (int) $val, (int) $pg->ID, false ) . '>' . esc_html( ( $pg->post_title ? $pg->post_title : '(بدون عنوان)' ) . $st ) . '</option>';
				}
				echo '</select><button type="button" class="yl-btn yl-btn-ghost" id="yl-new-page">' . self::icon( 'page' ) . 'ساخت صفحه‌ی جدید</button></div>'; // phpcs:ignore
				break;
			case 'pcat':
				self::cat_select( $id, $name, (int) $val, 'همه‌ی دسته‌ها' );
				break;
			case 'jalali':
				$v = wp_parse_args( is_array( $val ) ? $val : array(), array( 'y' => 1405, 'm' => 9, 'd' => 30, 't' => '17:00' ) );
				echo '<div class="yl-jal" data-jal>';
				echo '<label><small>روز</small><input class="yl-in" type="number" min="1" max="31" name="' . esc_attr( $name ) . '[d]" value="' . (int) $v['d'] . '" dir="ltr"></label>';
				echo '<label><small>ماه</small><select class="yl-in" name="' . esc_attr( $name ) . '[m]">';
				foreach ( yl_months() as $mi => $mn ) {
					echo '<option value="' . (int) $mi . '"' . selected( (int) $v['m'], $mi, false ) . '>' . esc_html( $mn ) . '</option>';
				}
				echo '</select></label>';
				echo '<label><small>سال</small><input class="yl-in" type="number" min="1390" max="1500" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '[y]" value="' . (int) $v['y'] . '" dir="ltr"></label>';
				echo '<label><small>ساعت</small><input class="yl-in" type="time" name="' . esc_attr( $name ) . '[t]" value="' . esc_attr( $v['t'] ) . '" dir="ltr"></label>';
				echo '</div><p class="yl-jal-info" data-jal-info></p>';
				break;
			case 'order':
				$labels = YL_Schema::sections();
				echo '<ul class="yl-order">';
				echo '<li class="yl-ord is-fixed"><span class="dashicons dashicons-lock"></span><b>هدر و هیرو</b><small>همیشه بالای صفحه</small></li>';
				foreach ( (array) $val as $s ) {
					if ( ! isset( $labels[ $s ] ) ) {
						continue;
					}
					$on = YL_Settings::on( $s );
					echo '<li class="yl-ord' . ( $on ? '' : ' is-off' ) . '" data-sec="' . esc_attr( $s ) . '">';
					echo '<input type="hidden" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $s ) . '">';
					echo '<span class="yl-drag dashicons dashicons-move" title="برای جابه‌جایی بکشید"></span><b>' . esc_html( $labels[ $s ] ) . '</b>';
					echo '<a class="yl-link" href="#tab=' . esc_attr( 'seo' === $s ? 'seo' : $s ) . '">تنظیمات</a>';
					echo '<button type="button" class="yl-ibtn" data-ord="-1" aria-label="بالاتر"><span class="dashicons dashicons-arrow-up-alt2"></span></button><button type="button" class="yl-ibtn" data-ord="1" aria-label="پایین‌تر"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
					echo '<label class="yl-sw"><input type="hidden" name="yl[' . esc_attr( $s ) . '][on]" value="0"><input type="checkbox" name="yl[' . esc_attr( $s ) . '][on]" value="1" data-sec-on="' . esc_attr( $s ) . '"' . checked( $on, true, false ) . '><span class="yl-sw-ui"></span><span class="yl-sw-l">' . ( $on ? 'روشن' : 'خاموش' ) . '</span></label>';
					echo '</li>';
				}
				echo '<li class="yl-ord is-fixed"><span class="dashicons dashicons-lock"></span><b>دعوت پایانی و فوتر</b><small>همیشه پایین صفحه</small></li>';
				echo '</ul>';
				break;
			case 'tags':
				$cur = (array) $val;
				echo '<div class="yl-tags" data-name="' . esc_attr( $name ) . '[]">';
				if ( empty( $f['o'] ) ) {
					echo '<span class="yl-note">ابتدا در تب «فرم پک‌ساز» یک سؤال از نوع «حال‌وهوا» بسازید.</span>';
				}
				foreach ( (array) $f['o'] as $ov => $ol ) {
					echo '<label class="yl-chip"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $ov ) . '"' . checked( in_array( (string) $ov, array_map( 'strval', $cur ), true ), true, false ) . '><span>' . esc_html( $ol ) . '</span></label>';
				}
				echo '</div>';
				break;
			case 'repeater':
				self::repeater( $f, $name, $val, $sec, $depth );
				break;
		}

		if ( ! empty( $f['h'] ) ) {
			echo '<p class="yl-h">' . esc_html( $f['h'] ) . '</p>';
		}
		echo '</div>';
	}

	private static function prod_card( $info ) {
		return '<span class="yl-pc">' . ( $info['img'] ? '<img src="' . esc_url( $info['img'] ) . '" alt="">' : '' )
			. '<span><b>' . esc_html( $info['name'] ) . '</b><small>' . wp_kses( $info['price'], array( 's' => array() ) ) . ' · شناسه ' . esc_html( yl_fa( $info['id'] ) ) . '</small>'
			. ( ! empty( $info['warn'] ) ? '<em>' . esc_html( $info['warn'] ) . '</em>' : '' ) . '</span>'
			. '<button type="button" class="yl-link yl-prod-change">تغییر محصول</button></span>';
	}

	private static function repeater( $f, $name, $rows, $sec, $depth ) {
		$inline = ! empty( $f['inline'] );
		$rows   = array_values( (array) $rows );
		echo '<div class="yl-rep' . ( $inline ? ' is-inline' : '' ) . '" data-depth="' . (int) $depth . '" data-title="' . esc_attr( isset( $f['title'] ) ? $f['title'] : '' ) . '">';
		echo '<div class="yl-rows">';
		foreach ( $rows as $i => $row ) {
			self::row( $f, $name . '[r' . $i . ']', $row, $sec, $depth, false );
		}
		echo '</div>';
		echo '<div class="yl-rep-f"><button type="button" class="yl-btn yl-btn-add yl-add"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>' . esc_html( $f['add'] ) . '</button>';
		echo '<span class="yl-rep-n" data-n></span>';
		if ( ! $inline && count( $rows ) > 3 ) {
			echo '<button type="button" class="yl-link yl-fold">باز/بسته کردن همه</button>';
		}
		echo '</div>';
		echo '<template class="yl-tpl">';
		self::row( $f, $name . '[__R' . (int) $depth . '__]', null, $sec, $depth, true );
		echo '</template>';
		echo '</div>';
	}

	private static function row( $f, $name, $row, $sec, $depth, $tpl ) {
		$row    = is_array( $row ) ? $row : array();
		$inline = ! empty( $f['inline'] );
		$tf     = isset( $f['title'] ) ? $f['title'] : '';
		$title  = isset( $row[ $tf ] ) ? (string) $row[ $tf ] : '';
		$thumb  = '';
		$is_prod = false;
		foreach ( $f['fields'] as $sf ) {
			if ( 'pid' === $sf['k'] ) {
				$is_prod = true;
			}
		}
		if ( $is_prod ) {
			$src = isset( $row['src'] ) ? $row['src'] : ( yl_woo() ? 'woo' : 'manual' );
			if ( 'woo' === $src && ! empty( $row['pid'] ) ) {
				$info  = self::product_info( (int) $row['pid'] );
				$title = $info ? $info['name'] : $title;
				$thumb = $info ? $info['img'] : '';
			} else {
				$thumb = self::img_url( isset( $row['img'] ) ? $row['img'] : '' );
			}
		}
		if ( 'q' === $tf && isset( $row['q'], $row['pct'] ) && ! isset( $row['a'] ) ) {
			$title = 'از ' . yl_fa( $row['q'] ) . ' نفر: ' . yl_fa( $row['pct'] ) . '٪';
		}

		echo '<div class="yl-row' . ( $inline ? ' is-inline' : '' ) . ( $tpl ? ' is-open is-new' : '' ) . '">';
		if ( $inline ) {
			echo '<span class="yl-drag yl-drag-d' . (int) $depth . ' dashicons dashicons-move" title="برای جابه‌جایی بکشید"></span><div class="yl-grid">';
			foreach ( $f['fields'] as $sf ) {
				self::field( $sf, $name . '[' . $sf['k'] . ']', array_key_exists( $sf['k'], $row ) ? $row[ $sf['k'] ] : null, $sec, $depth + 1 );
			}
			echo '</div>';
			echo '<div class="yl-row-a"><button type="button" class="yl-ibtn yl-del" aria-label="حذف"><span class="dashicons dashicons-trash"></span></button></div>';
			echo '</div>';
			return;
		}
		echo '<div class="yl-row-h">';
		echo '<span class="yl-drag yl-drag-d' . (int) $depth . ' dashicons dashicons-move" title="برای جابه‌جایی بکشید"></span>';
		echo '<span class="yl-row-th">' . ( $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="">' : '' ) . '</span>';
		echo '<button type="button" class="yl-row-t" aria-expanded="' . ( $tpl ? 'true' : 'false' ) . '"><b data-row-title>' . esc_html( '' !== $title ? $title : 'بدون عنوان' ) . '</b><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>';
		echo '<div class="yl-row-a">';
		echo '<button type="button" class="yl-ibtn" data-move="-1" aria-label="بالاتر"><span class="dashicons dashicons-arrow-up-alt2"></span></button>';
		echo '<button type="button" class="yl-ibtn" data-move="1" aria-label="پایین‌تر"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
		echo '<button type="button" class="yl-ibtn yl-dup" aria-label="کپی ردیف" title="کپی"><span class="dashicons dashicons-admin-page"></span></button>';
		echo '<button type="button" class="yl-ibtn yl-del" aria-label="حذف ردیف" title="حذف"><span class="dashicons dashicons-trash"></span></button>';
		echo '</div></div>';
		echo '<div class="yl-row-b"><div class="yl-grid">';
		foreach ( $f['fields'] as $sf ) {
			self::field( $sf, $name . '[' . $sf['k'] . ']', array_key_exists( $sf['k'], $row ) ? $row[ $sf['k'] ] : null, $sec, $depth + 1 );
		}
		echo '</div></div></div>';
	}
}
