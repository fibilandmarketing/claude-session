<?php
/**
 * Admin: settings screen with a tab per landing section.
 *
 * @package TiademTennisLanding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
class TDL_Admin {

	const CAP  = 'manage_woocommerce';
	const SLUG = 'tdl-landing';

	const BACKUP = 'tdl_settings_backup';

	/**
	 * Cached product_cat options.
	 *
	 * @var array|null
	 */
	private static $term_options = null;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_action( 'admin_post_tdl_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_tdl_tool', array( __CLASS__, 'tool' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TDL_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Plugins list link.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">تنظیمات لندینگ</a>' );
		return $links;
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		add_menu_page( 'لندینگ تنیسی تیادم', 'لندینگ تنیسی', self::CAP, self::SLUG, array( __CLASS__, 'page' ), 'dashicons-star-filled', 56 );
	}

	/**
	 * Assets for our screen only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		if ( wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
			wp_enqueue_script( 'wc-enhanced-select' );
		}
		if ( wp_style_is( 'woocommerce_admin_styles', 'registered' ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}
		wp_enqueue_style( 'tdl-admin', TDL_URL . 'assets/css/admin.css', array(), TDL_VERSION . '.' . filemtime( TDL_DIR . 'assets/css/admin.css' ) );
		wp_enqueue_script( 'tdl-admin', TDL_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), TDL_VERSION . '.' . filemtime( TDL_DIR . 'assets/js/admin.js' ), true );
		wp_localize_script(
			'tdl-admin',
			'TDLAdmin',
			array(
				'assetUrl' => TDL_URL . 'assets/img/',
				'icons'    => array_map(
					static function ( $i ) {
						return $i[1];
					},
					tdl_icons()
				),
				'confirmDelete' => 'این آیتم حذف شود؟',
				'confirmReset'  => 'محتوای این سکشن به حالت پیش‌فرض طرح برگردد؟',
				'unsaved'       => 'تغییرات ذخیره نشده‌اند. خارج می‌شوید؟',
				'mediaTitle'    => 'انتخاب تصویر',
				'mediaButton'   => 'استفاده از این تصویر',
			)
		);
	}

	/**
	 * Product category options (hierarchical).
	 *
	 * @return array id => label
	 */
	private static function term_options() {
		if ( null !== self::$term_options ) {
			return self::$term_options;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$out   = array();
		if ( ! is_wp_error( $terms ) ) {
			$by_parent = array();
			foreach ( $terms as $t ) {
				$by_parent[ (int) $t->parent ][] = $t;
			}
			$walk = static function ( $parent, $depth ) use ( &$walk, &$out, $by_parent ) {
				if ( empty( $by_parent[ $parent ] ) ) {
					return;
				}
				foreach ( $by_parent[ $parent ] as $t ) {
					$out[ (int) $t->term_id ] = str_repeat( '— ', $depth ) . $t->name . ' (' . (int) $t->count . ')';
					$walk( (int) $t->term_id, $depth + 1 );
				}
			};
			$walk( 0, 0 );
		}
		self::$term_options = $out;
		return $out;
	}

	/**
	 * Render the settings screen.
	 */
	public static function page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'tiadem-tl' ) );
		}
		$settings = TDL_Settings::get();
		$tabs     = TDL_Schema::tabs();
		$active   = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'layout'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $tabs[ $active ] ) ) {
			$active = 'layout';
		}
		$page_id      = (int) get_option( TDL_Installer::PAGE_OPTION, 0 );
		$page_url     = $page_id ? get_permalink( $page_id ) : '';
		$placeholders = TDL_Settings::placeholders();
		$notice       = isset( $_GET['tdl_msg'] ) ? sanitize_key( $_GET['tdl_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$messages     = array(
			'saved'    => 'تنظیمات ذخیره شد.',
			'reset'    => 'سکشن به محتوای پیش‌فرض برگشت.',
			'mapped'   => 'تشخیص خودکار دسته‌ها انجام شد.',
			'nomap'    => 'دسته جدیدی برای تشخیص خودکار پیدا نشد.',
			'cache'    => 'کش محصولات پاک شد.',
			'imported' => 'تنظیمات وارد شد.',
			'badjson'  => 'فایل/متن تنظیمات معتبر نیست.',
			'page'     => 'صفحه لندینگ ساخته شد.',
			'resetall' => 'همه تنظیمات به پیش‌فرض اولیه افزونه برگشت (اتصال دسته‌های ووکامرس حفظ شد). نسخه پشتیبان قبلی ذخیره شده و از تب «پیشرفته و سرعت» قابل بازگردانی است.',
			'undone'   => 'تنظیمات قبل از ریست بازگردانی شد.',
			'nobackup' => 'نسخه پشتیبانی برای بازگردانی وجود ندارد.',
		);
		?>
		<div class="wrap tdl-admin" dir="rtl">
			<div class="tdl-top">
				<div class="tdl-brand">
					<span class="tdl-brand-ic dashicons dashicons-star-filled"></span>
					<div>
						<h1>لندینگ تنیسی تیادم</h1>
						<p>محتوای هر سکشن را ویرایش کنید؛ محصولات مستقیماً از ووکامرس خوانده می‌شوند.</p>
					</div>
				</div>
				<div class="tdl-top-actions">
					<?php if ( $page_url ) : ?>
						<a class="button" href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-visibility"></span> مشاهده لندینگ</a>
						<a class="button" href="<?php echo esc_url( get_edit_post_link( $page_id ) ); ?>"><span class="dashicons dashicons-edit-page"></span> صفحه وردپرس</a>
					<?php else : ?>
						<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tdl_tool&do=page' ), 'tdl_tool' ) ); ?>">ساخت صفحه لندینگ</a>
					<?php endif; ?>
					<button type="submit" form="tdl-form" class="button button-primary button-hero tdl-save">ذخیره تغییرات</button>
				</div>
			</div>

			<?php if ( $notice && isset( $messages[ $notice ] ) ) : ?>
				<div class="notice <?php echo in_array( $notice, array( 'badjson', 'nomap', 'nobackup' ), true ) ? 'notice-warning' : 'notice-success'; ?> is-dismissible"><p><?php echo esc_html( $messages[ $notice ] ); ?>
					<?php if ( 'resetall' === $notice && get_option( self::BACKUP ) ) : ?>
						<a class="button button-small tdl-undo" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tdl_tool&do=undoreset' ), 'tdl_tool' ) ); ?>">بازگشت از ریست</a>
					<?php endif; ?>
				</p></div>
			<?php endif; ?>
			<?php if ( $placeholders ) : ?>
				<div class="notice notice-info tdl-ph-notice"><p>چند متن هنوز عبارت‌های جایگزین طرح مثل <code>[شرایط ارسال…]</code> دارند. تب‌های علامت‌دار <span class="tdl-dot"></span> را بررسی و تکمیل کنید.</p></div>
			<?php endif; ?>

			<form id="tdl-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tdl_save">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $active ); ?>" data-tdl-tab-input>
				<input type="hidden" name="payload" value="" data-tdl-payload>
				<?php wp_nonce_field( 'tdl_save', 'tdl_nonce' ); ?>

				<div class="tdl-shell">
					<nav class="tdl-tabs" aria-label="سکشن‌ها">
						<?php foreach ( $tabs as $key => $tab ) : ?>
							<?php
							$off = isset( $settings[ $key ]['enabled'] ) && ! $settings[ $key ]['enabled'];
							?>
							<a href="#<?php echo esc_attr( $key ); ?>" class="tdl-tab<?php echo $key === $active ? ' is-active' : ''; ?><?php echo $off ? ' is-off' : ''; ?>" data-tab="<?php echo esc_attr( $key ); ?>">
								<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
								<span class="tdl-tab-t"><?php echo esc_html( $tab['title'] ); ?></span>
								<?php if ( isset( $placeholders[ $key ] ) ) : ?><span class="tdl-dot" title="متن جایگزین تکمیل‌نشده"></span><?php endif; ?>
							</a>
						<?php endforeach; ?>
					</nav>

					<div class="tdl-panels">
						<?php foreach ( $tabs as $key => $tab ) : ?>
						<section class="tdl-panel<?php echo $key === $active ? ' is-active' : ''; ?>" data-panel="<?php echo esc_attr( $key ); ?>">
							<header class="tdl-panel-head">
								<h2><?php echo esc_html( $tab['title'] ); ?></h2>
								<?php if ( ! in_array( $key, array( 'layout', 'advanced' ), true ) ) : ?>
									<a class="button-link tdl-reset" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tdl_tool&do=reset&section=' . $key ), 'tdl_tool' ) ); ?>" data-confirm>بازگشت به پیش‌فرض طرح</a>
								<?php endif; ?>
							</header>
							<?php if ( ! empty( $tab['desc'] ) ) : ?><p class="tdl-desc"><?php echo esc_html( $tab['desc'] ); ?></p><?php endif; ?>
							<?php if ( 'woo' === $key ) : ?>
								<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tdl_tool&do=automap' ), 'tdl_tool' ) ); ?>"><span class="dashicons dashicons-search"></span> تشخیص خودکار دسته‌ها از روی نام</a></p>
							<?php endif; ?>
							<div class="tdl-fields">
								<?php
								foreach ( $tab['fields'] as $field ) {
									if ( 'order' === $field['type'] ) {
										self::render_order( $settings );
										continue;
									}
									$value = isset( $settings[ $key ][ $field['key'] ] ) ? $settings[ $key ][ $field['key'] ] : '';
									self::render_field( $field, $value, $key, false );
								}
								?>
							</div>
							<?php if ( 'advanced' === $key ) : ?>
								<?php self::render_tools(); ?>
							<?php endif; ?>
						</section>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="tdl-savebar">
					<span class="tdl-dirty" data-tdl-dirty hidden>تغییرات ذخیره نشده دارید</span>
					<button type="submit" class="button button-primary button-large tdl-save">ذخیره تغییرات</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Section order list.
	 *
	 * @param array $settings Settings.
	 */
	private static function render_order( $settings ) {
		$labels = TDL_Schema::section_labels();
		$tabs   = TDL_Schema::tabs();
		echo '<div class="tdl-field" data-type="order" data-key="order" data-tab="order"><ul class="tdl-order" data-order>';
		foreach ( $settings['order'] as $key ) {
			$on = ! empty( $settings[ $key ]['enabled'] );
			printf(
				'<li data-key="%1$s"><span class="dashicons dashicons-move tdl-handle"></span><span class="dashicons %2$s"></span><span class="tdl-order-t">%3$s</span><span class="tdl-status %4$s">%5$s</span><a href="#%1$s" class="tdl-goto" data-goto="%1$s">ویرایش</a></li>',
				esc_attr( $key ),
				esc_attr( $tabs[ $key ]['icon'] ),
				esc_html( $labels[ $key ] ),
				$on ? 'is-on' : 'is-off',
				$on ? 'فعال' : 'غیرفعال'
			);
		}
		echo '</ul><p class="description">نوار اعلان و هدر همیشه بالای صفحه و فوتر همیشه پایین صفحه است. برای روشن/خاموش کردن هر سکشن، گزینه «نمایش» را در تب همان سکشن تغییر دهید.</p></div>';
	}

	/**
	 * Tools box (advanced tab).
	 */
	private static function render_tools() {
		$tool = static function ( $do, $label, $icon ) {
			return '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tdl_tool&do=' . $do ), 'tdl_tool' ) ) . '"><span class="dashicons ' . esc_attr( $icon ) . '"></span> ' . esc_html( $label ) . '</a> ';
		};
		echo '<div class="tdl-tools"><h3>ابزارها</h3><p>';
		echo $tool( 'cache', 'پاک کردن کش محصولات', 'dashicons-update' ); // phpcs:ignore
		echo $tool( 'export', 'دریافت خروجی تنظیمات (JSON)', 'dashicons-download' ); // phpcs:ignore
		echo $tool( 'page', 'ساخت/بازیابی صفحه لندینگ', 'dashicons-admin-page' ); // phpcs:ignore
		echo '</p>';

		$backup = get_option( self::BACKUP, array() );
		echo '<div class="tdl-danger-zone"><h3>بازگشت به تنظیمات پیش‌فرض</h3>';
		echo '<p class="description">همه متن‌ها، تصاویر، آیتم‌ها، ترتیب سکشن‌ها و تنظیمات پیشرفته به حالت اولیه طرح برمی‌گردند. <strong>اتصال دسته‌های ووکامرس حفظ می‌شود.</strong> پیش از ریست، یک نسخه پشتیبان خودکار ذخیره می‌شود.</p>';
		echo '<p><a class="button tdl-btn-danger" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tdl_tool&do=resetall' ), 'tdl_tool' ) ) . '" data-confirm="همه تنظیمات لندینگ به پیش‌فرض اولیه افزونه برگردد؟ (اتصال دسته‌های ووکامرس حفظ می‌شود و نسخه پشتیبان ذخیره می‌شود)"><span class="dashicons dashicons-image-rotate"></span> بازگشت کامل به تنظیمات پیش‌فرض</a> ';
		if ( ! empty( $backup['settings'] ) ) {
			$when = ! empty( $backup['time'] ) ? TDL_Jalali::format( (int) $backup['time'] ) : '';
			echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tdl_tool&do=undoreset' ), 'tdl_tool' ) ) . '" data-confirm="تنظیمات به حالت قبل از ریست (' . esc_attr( $when ) . ') بازگردانی شود؟"><span class="dashicons dashicons-backup"></span> بازگشت از ریست (' . esc_html( $when ) . ')</a>';
		}
		echo '</p></div>';

		echo '<h3>ورود تنظیمات</h3><p class="description">متن فایل JSON خروجی را اینجا قرار دهید و دکمه را بزنید.</p>';
		echo '<textarea class="large-text code" rows="4" dir="ltr" data-tdl-import></textarea>';
		echo '<p><button type="button" class="button" data-tdl-import-btn data-url="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tdl_tool&do=import' ), 'tdl_tool' ) ) . '">ورود تنظیمات</button></p></div>';
	}

	/**
	 * Render one field (top level or inside a repeater item).
	 *
	 * @param array  $f      Field definition.
	 * @param mixed  $value  Value.
	 * @param string $tab    Tab key.
	 * @param bool   $is_sub Inside repeater.
	 */
	public static function render_field( $f, $value, $tab, $is_sub ) {
		$cls  = $is_sub ? 'tdl-sub' : 'tdl-field';
		$wide = in_array( $f['type'], array( 'repeater', 'textarea', 'html', 'code', 'image', 'products', 'terms', 'jdatetime' ), true ) ? ' is-wide' : '';
		printf( '<div class="%1$s tdl-t-%2$s%3$s" data-type="%2$s" data-key="%4$s">', esc_attr( $cls ), esc_attr( $f['type'] ), esc_attr( $wide ), esc_attr( $f['key'] ) );
		if ( 'toggle' !== $f['type'] ) {
			echo '<label class="tdl-label">' . esc_html( $f['label'] ) . '</label>';
		}

		switch ( $f['type'] ) {
			case 'text':
				printf( '<input type="text" class="tdl-in" data-v value="%s">', esc_attr( $value ) );
				break;

			case 'url':
				printf( '<input type="text" class="tdl-in" dir="ltr" data-v value="%s" placeholder="https://… یا #grid">', esc_attr( $value ) );
				break;

			case 'number':
				printf(
					'<input type="number" class="tdl-in tdl-num" data-v value="%s"%s%s>',
					esc_attr( $value ),
					isset( $f['min'] ) ? ' min="' . (int) $f['min'] . '"' : '',
					isset( $f['max'] ) ? ' max="' . (int) $f['max'] . '"' : ''
				);
				break;

			case 'textarea':
			case 'html':
				printf( '<textarea class="tdl-in" rows="%d" data-v>%s</textarea>', 'html' === $f['type'] ? 6 : 3, esc_textarea( $value ) );
				break;

			case 'code':
				printf( '<textarea class="tdl-in code" rows="8" dir="ltr" data-v>%s</textarea>', esc_textarea( $value ) );
				break;

			case 'color':
				printf( '<span class="tdl-color"><input type="color" data-v value="%1$s"><code>%1$s</code></span>', esc_attr( $value ) );
				break;

			case 'toggle':
				printf( '<label class="tdl-switch"><input type="checkbox" data-v value="1"%s><span class="tdl-slider"></span><span class="tdl-switch-l">%s</span></label>', $value ? ' checked' : '', esc_html( $f['label'] ) );
				break;

			case 'select':
				echo '<select class="tdl-in" data-v>';
				foreach ( $f['options'] as $k => $label ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( (string) $value, (string) $k, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'term':
				echo '<select class="tdl-in" data-v><option value="0">— انتخاب نشده —</option>';
				foreach ( self::term_options() as $id => $label ) {
					printf( '<option value="%d"%s>%s</option>', (int) $id, selected( (int) $value, (int) $id, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'terms':
				echo '<select class="tdl-in wc-enhanced-select" multiple data-v data-placeholder="دسته‌ها را انتخاب کنید…" style="width:100%">';
				foreach ( self::term_options() as $id => $label ) {
					printf( '<option value="%d"%s>%s</option>', (int) $id, in_array( (int) $id, array_map( 'intval', (array) $value ), true ) ? ' selected' : '', esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'products':
				echo '<select class="tdl-in wc-product-search" multiple data-v data-action="woocommerce_json_search_products" data-placeholder="جستجوی محصول…" data-sortable="true" style="width:100%">';
				foreach ( (array) $value as $pid ) {
					$p = wc_get_product( $pid );
					if ( $p ) {
						printf( '<option value="%d" selected>%s</option>', (int) $pid, esc_html( wp_strip_all_tags( $p->get_formatted_name() ) ) );
					}
				}
				echo '</select>';
				break;

			case 'attribute':
				echo '<select class="tdl-in" data-v><option value="">— انتخاب نشده —</option>';
				foreach ( wc_get_attribute_taxonomies() as $attr ) {
					$tax = wc_attribute_taxonomy_name( $attr->attribute_name );
					printf( '<option value="%s"%s>%s</option>', esc_attr( $tax ), selected( (string) $value, $tax, false ), esc_html( $attr->attribute_label . ' (' . $tax . ')' ) );
				}
				echo '</select>';
				break;

			case 'filter':
				$opts = array(
					''       => '— غیرفعال —',
					'all'    => 'همه محصولات',
					'sale'   => 'تخفیف‌دار',
					'eco'    => 'کیفیت اکونومی',
					'master' => 'کیفیت مستر',
				);
				foreach ( self::term_options() as $id => $label ) {
					$opts[ 'cat:' . $id ] = 'دسته: ' . $label;
				}
				echo '<select class="tdl-in" data-v>';
				foreach ( $opts as $k => $label ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( (string) $value, (string) $k, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'icon':
				echo '<span class="tdl-icon-pick"><span class="tdl-icon-prev" data-icon-prev>' . tdl_icon( $value ? $value : 'diamond', 20 ) . '</span><select class="tdl-in" data-v>'; // phpcs:ignore
				foreach ( tdl_icons() as $k => $icon ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( (string) $value, $k, false ), esc_html( $icon[0] ) );
				}
				echo '</select></span>';
				break;

			case 'image':
				$url = tdl_img_url( $value, 'medium' );
				printf(
					'<div class="tdl-img" data-default=""><div class="tdl-img-prev">%1$s</div><input type="hidden" data-v value="%2$s"><div class="tdl-img-btns"><button type="button" class="button" data-img-pick>انتخاب از رسانه</button> <button type="button" class="button-link tdl-danger" data-img-clear>حذف</button></div></div>',
					$url ? '<img src="' . esc_url( $url ) . '" alt="">' : '<span class="tdl-img-empty">بدون تصویر</span>',
					esc_attr( is_scalar( $value ) ? $value : '' )
				);
				break;

			case 'jdatetime':
				self::render_jdatetime( (int) $value );
				break;

			case 'repeater':
				self::render_repeater( $f, (array) $value, $tab );
				break;
		}

		if ( ! empty( $f['help'] ) ) {
			echo '<p class="tdl-help">' . esc_html( $f['help'] ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Jalali date-time picker.
	 *
	 * @param int $ts Timestamp.
	 */
	private static function render_jdatetime( $ts ) {
		$p      = $ts ? TDL_Jalali::parts( $ts ) : array( 'y' => '', 'm' => '', 'd' => '', 'h' => 23, 'i' => 59 );
		$months = TDL_Jalali::months();
		$now    = time();
		$presets = array();
		foreach ( array( 1 => 'تا فردا', 3 => '۳ روز', 7 => '۷ روز', 14 => '۱۴ روز', 30 => '۳۰ روز' ) as $days => $label ) {
			$pp           = TDL_Jalali::parts( $now + $days * DAY_IN_SECONDS );
			$pp['h']      = 23;
			$pp['i']      = 59;
			$pp['label']  = $label;
			$presets[]    = $pp;
		}
		echo '<div class="tdl-jdt" data-presets="' . esc_attr( wp_json_encode( $presets ) ) . '">';
		echo '<span class="tdl-jdt-part"><small>روز</small><select data-part="d"><option value="">—</option>';
		for ( $i = 1; $i <= 31; $i++ ) {
			printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $i, selected( (int) $p['d'], $i, false ), esc_html( tdl_fa( $i ) ) );
		}
		echo '</select></span><span class="tdl-jdt-part"><small>ماه</small><select data-part="m"><option value="">—</option>';
		foreach ( $months as $i => $name ) {
			printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $i, selected( (int) $p['m'], $i, false ), esc_html( $name ) );
		}
		echo '</select></span>';
		printf( '<span class="tdl-jdt-part"><small>سال</small><input type="number" data-part="y" min="1400" max="1500" value="%s" placeholder="۱۴۰۵"></span>', esc_attr( $p['y'] ) );
		printf( '<span class="tdl-jdt-part"><small>ساعت</small><input type="number" data-part="h" min="0" max="23" value="%s"></span>', esc_attr( $p['h'] ) );
		printf( '<span class="tdl-jdt-part"><small>دقیقه</small><input type="number" data-part="i" min="0" max="59" value="%s"></span>', esc_attr( $p['i'] ) );
		echo '<span class="tdl-jdt-presets">';
		foreach ( $presets as $k => $pp ) {
			printf( '<button type="button" class="button button-small" data-preset="%d">%s</button>', (int) $k, esc_html( $pp['label'] ) );
		}
		echo '<button type="button" class="button-link tdl-danger" data-jdt-clear>بدون شمارش معکوس</button></span>';
		if ( $ts ) {
			$state = $ts > $now ? '<span class="tdl-ok">فعال — ' . esc_html( human_time_diff( $now, $ts ) ) . ' مانده</span>' : '<span class="tdl-bad">به پایان رسیده</span>';
			echo '<p class="tdl-help">ذخیره‌شده: ' . esc_html( TDL_Jalali::format( $ts ) ) . ' (' . esc_html( wp_date( 'Y-m-d H:i', $ts, new DateTimeZone( TDL_Jalali::TZ ) ) ) . ' میلادی) — ' . $state . '</p>'; // phpcs:ignore
		}
		echo '</div>';
	}

	/**
	 * Repeater field.
	 *
	 * @param array  $f     Field.
	 * @param array  $items Items.
	 * @param string $tab   Tab.
	 */
	private static function render_repeater( $f, $items, $tab ) {
		echo '<div class="tdl-rep" data-label-key="' . esc_attr( isset( $f['item_label'] ) ? $f['item_label'] : '' ) . '">';
		echo '<ol class="tdl-rep-list">';
		foreach ( $items as $item ) {
			self::render_item( $f, (array) $item, $tab );
		}
		echo '</ol>';
		echo '<script type="text/html" class="tdl-rep-tpl">';
		ob_start();
		self::render_item( $f, array(), $tab );
		echo str_replace( '</script', '<\/script', ob_get_clean() ); // phpcs:ignore
		echo '</script>';
		echo '<button type="button" class="button tdl-rep-add" data-rep-add><span class="dashicons dashicons-plus-alt2"></span> افزودن آیتم</button>';
		echo '</div>';
	}

	/**
	 * Repeater item.
	 *
	 * @param array  $f    Field.
	 * @param array  $item Values.
	 * @param string $tab  Tab.
	 */
	private static function render_item( $f, $item, $tab ) {
		$label_key = isset( $f['item_label'] ) ? $f['item_label'] : '';
		$title     = ( $label_key && ! empty( $item[ $label_key ] ) ) ? wp_strip_all_tags( (string) $item[ $label_key ] ) : 'آیتم جدید';
		echo '<li class="tdl-item">';
		echo '<div class="tdl-item-head"><span class="dashicons dashicons-move tdl-handle" title="جابجایی"></span><button type="button" class="tdl-item-toggle" data-item-toggle><span class="tdl-item-t">' . esc_html( wp_html_excerpt( $title, 60, '…' ) ) . '</span><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
		echo '<span class="tdl-item-acts"><button type="button" class="button-link" data-item-dup title="کپی"><span class="dashicons dashicons-admin-page"></span></button><button type="button" class="button-link tdl-danger" data-item-del title="حذف"><span class="dashicons dashicons-trash"></span></button></span></div>';
		echo '<div class="tdl-item-body">';
		foreach ( $f['fields'] as $sub ) {
			$v = isset( $item[ $sub['key'] ] ) ? $item[ $sub['key'] ] : ( 'icon' === $sub['type'] ? 'diamond' : '' );
			self::render_field( $sub, $v, $tab, true );
		}
		echo '</div></li>';
	}

	/**
	 * Save handler (JSON payload).
	 */
	public static function save() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		check_admin_referer( 'tdl_save', 'tdl_nonce' );
		$tab     = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'layout';
		$payload = isset( $_POST['payload'] ) ? json_decode( wp_unslash( $_POST['payload'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field below.
		if ( ! is_array( $payload ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'tab' => $tab, 'tdl_msg' => 'badjson' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		TDL_Settings::save( TDL_Settings::sanitize( $payload ) );
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'tab' => $tab, 'tdl_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Tools: reset, automap, cache, export, import, page.
	 */
	public static function tool() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		check_admin_referer( 'tdl_tool' );
		$do  = isset( $_REQUEST['do'] ) ? sanitize_key( wp_unslash( $_REQUEST['do'] ) ) : '';
		$tab = 'advanced';
		$msg = '';

		switch ( $do ) {
			case 'reset':
				$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
				$stored  = get_option( TDL_OPTION, array() );
				if ( is_array( $stored ) && isset( TDL_Defaults::get()[ $section ] ) ) {
					unset( $stored[ $section ] );
					TDL_Settings::save( $stored );
				}
				$tab = $section;
				$msg = 'reset';
				break;

			case 'resetall':
				$stored = get_option( TDL_OPTION, array() );
				update_option(
					self::BACKUP,
					array(
						'time'     => time(),
						'settings' => is_array( $stored ) ? $stored : array(),
					),
					false
				);
				$keep  = array( 'scope_cats', 'eco_cat', 'master_cat', 'clasp_mode', 'clasp_book_cat', 'clasp_cuban_cat', 'clasp_attr' );
				$woo   = TDL_Settings::get()['woo'];
				$fresh = array(
					'woo'      => array_intersect_key( $woo, array_flip( $keep ) ),
					'festival' => array( 'end_ts' => time() + 7 * DAY_IN_SECONDS ),
				);
				TDL_Settings::save( $fresh );
				// Re-link default chips / category cards to the (kept) WooCommerce categories.
				TDL_Installer::auto_map();
				$tab = 'layout';
				$msg = 'resetall';
				break;

			case 'undoreset':
				$backup = get_option( self::BACKUP, array() );
				if ( empty( $backup['settings'] ) || ! is_array( $backup['settings'] ) ) {
					$msg = 'nobackup';
					break;
				}
				TDL_Settings::save( $backup['settings'] );
				delete_option( self::BACKUP );
				$msg = 'undone';
				break;

			case 'automap':
				$msg = TDL_Installer::auto_map() ? 'mapped' : 'nomap';
				$tab = 'woo';
				break;

			case 'cache':
				TDL_Products::bump_cache();
				$msg = 'cache';
				break;

			case 'page':
				TDL_Installer::create_page();
				$msg = 'page';
				break;

			case 'export':
				nocache_headers();
				header( 'Content-Type: application/json; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename=tiadem-landing-' . gmdate( 'Ymd-His' ) . '.json' );
				echo wp_json_encode( TDL_Settings::get(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
				exit;

			case 'import':
				$raw  = isset( $_POST['json'] ) ? wp_unslash( $_POST['json'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized by schema.
				$data = json_decode( (string) $raw, true );
				if ( is_array( $data ) ) {
					TDL_Settings::save( TDL_Settings::sanitize( $data ) );
					$msg = 'imported';
				} else {
					$msg = 'badjson';
				}
				break;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'tab' => $tab, 'tdl_msg' => $msg ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
