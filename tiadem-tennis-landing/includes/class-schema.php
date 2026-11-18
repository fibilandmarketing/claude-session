<?php
/**
 * Admin field schema — drives the settings UI and sanitization.
 *
 * @package TiademTennisLanding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field schema.
 */
class TDL_Schema {

	/**
	 * Shortcut for a field definition.
	 *
	 * @param string $key   Key.
	 * @param string $type  Type.
	 * @param string $label Label.
	 * @param array  $extra Extra props.
	 * @return array
	 */
	private static function f( $key, $type, $label, $extra = array() ) {
		return array_merge(
			array(
				'key'   => $key,
				'type'  => $type,
				'label' => $label,
			),
			$extra
		);
	}

	/**
	 * Section labels for the order list.
	 *
	 * @return array
	 */
	public static function section_labels() {
		return array(
			'hero'     => 'هیرو (بنر اصلی)',
			'trust'    => 'مزایای خرید',
			'cats'     => 'دسته‌بندی‌ها (دنبال کدام مدل…)',
			'festival' => 'جشنواره و تخفیف‌ها',
			'economy'  => 'ردیف اکونومی',
			'master'   => 'ردیف مستر کوالیتی',
			'grid'     => 'همه محصولات + فیلتر',
			'reviews'  => 'نظر خریداران',
			'what'     => 'بدلیجات تنیسی چیست؟',
			'types'    => 'انواع بدلیجات تنیسی',
			'guide'    => 'راهنمای خرید و قفل‌ها',
			'care'     => 'نکات نگهداری',
			'faq'      => 'سؤالات پرتکرار',
			'related'  => 'دسته‌های مرتبط',
		);
	}

	/**
	 * Row (economy/master) fields.
	 *
	 * @param string $which economy|master.
	 * @return array
	 */
	private static function row_fields( $which ) {
		return array(
			self::f( 'enabled', 'toggle', 'نمایش این سکشن' ),
			self::f( 'title', 'text', 'عنوان سکشن' ),
			self::f( 'view_all', 'text', 'متن دکمه «مشاهده همه»', array( 'help' => 'با کلیک، جدول همه محصولات روی همین کیفیت فیلتر می‌شود.' ) ),
			self::f( 'side_en', 'text', 'برچسب انگلیسی کارت کناری' ),
			self::f( 'side_title', 'text', 'عنوان کارت کناری' ),
			self::f( 'side_desc', 'textarea', 'توضیح کارت کناری (دسکتاپ)' ),
			self::f( 'side_desc_mobile', 'textarea', 'توضیح کارت کناری (موبایل)' ),
			self::f( 'side_image', 'image', 'تصویر کارت کناری', array( 'help' => 'PNG یا WebP با پس‌زمینه شفاف بهترین نتیجه را دارد.' ) ),
			self::f( 'side_alt', 'text', 'متن جایگزین تصویر (alt)' ),
			self::f(
				'source',
				'select',
				'منبع محصولات',
				array(
					'options' => array(
						'auto'   => 'خودکار از دسته «' . ( 'economy' === $which ? 'اکونومی' : 'مستر کوالیتی' ) . '» (تب اتصال ووکامرس)',
						'manual' => 'انتخاب دستی محصولات',
					),
				)
			),
			self::f( 'products', 'products', 'محصولات دستی', array( 'help' => 'فقط وقتی «انتخاب دستی» فعال است استفاده می‌شود؛ ترتیب انتخاب حفظ می‌شود.' ) ),
			self::f( 'limit', 'number', 'حداکثر تعداد محصول', array( 'min' => 1, 'max' => 30 ) ),
			self::f( 'orderby', 'select', 'مرتب‌سازی', array( 'options' => self::orderby_options() ) ),
		);
	}

	/**
	 * Order-by options.
	 *
	 * @return array
	 */
	public static function orderby_options() {
		return array(
			'popularity' => 'پرفروش‌ترین',
			'date'       => 'جدیدترین',
			'rating'     => 'بیشترین امتیاز',
			'price'      => 'ارزان‌ترین',
			'price-desc' => 'گران‌ترین',
			'menu_order' => 'ترتیب دستی ووکامرس',
			'rand'       => 'تصادفی',
		);
	}

	/**
	 * All tabs and fields.
	 *
	 * @return array
	 */
	public static function tabs() {
		$link_help = 'اگر دسته ووکامرس انتخاب شود، لینک به آرشیو همان دسته می‌رود؛ وگرنه لینک دلخواه (مثل #grid) استفاده می‌شود.';

		return array(
			'layout'   => array(
				'title'  => 'چیدمان صفحه',
				'icon'   => 'dashicons-layout',
				'desc'   => 'ترتیب سکشن‌ها را با کشیدن و رها کردن تغییر دهید و هر سکشن را روشن/خاموش کنید. هدر، نوار اعلان و فوتر همیشه در جای خود هستند.',
				'fields' => array( self::f( 'order', 'order', 'ترتیب سکشن‌ها' ) ),
			),
			'general'  => array(
				'title'  => 'عمومی و برند',
				'icon'   => 'dashicons-admin-appearance',
				'fields' => array(
					self::f( 'logo', 'image', 'لوگو', array( 'help' => 'در هدر، منوی موبایل و فوتر نمایش داده می‌شود.' ) ),
					self::f( 'logo_alt', 'text', 'متن جایگزین لوگو' ),
					self::f( 'color_ink', 'color', 'رنگ متن اصلی' ),
					self::f( 'color_accent', 'color', 'رنگ تأکیدی (آبی)' ),
					self::f( 'color_btn', 'color', 'رنگ دکمه‌های تیره' ),
					self::f( 'currency', 'text', 'واحد پول نمایشی', array( 'help' => 'خالی = از تنظیمات ووکامرس (مثلاً «تومان»).' ) ),
					self::f( 'phone', 'text', 'شماره تماس', array( 'help' => 'در منوی موبایل نمایش داده می‌شود؛ خالی = مخفی.' ) ),
					self::f( 'phone_label', 'text', 'عنوان بخش تماس' ),
					self::f( 'social_label', 'text', 'عنوان شبکه‌های اجتماعی' ),
					self::f( 'instagram', 'url', 'لینک اینستاگرام' ),
					self::f( 'telegram', 'url', 'لینک تلگرام' ),
					self::f( 'bale', 'url', 'لینک بله' ),
					self::f( 'meta_description', 'textarea', 'توضیحات متا (SEO)', array( 'help' => 'فقط وقتی افزونه سئو (Yoast / Rank Math) فعال نباشد چاپ می‌شود.' ) ),
				),
			),
			'woo'      => array(
				'title'  => 'اتصال ووکامرس',
				'icon'   => 'dashicons-cart',
				'desc'   => 'مشخص کنید هر بخش لندینگ محصولاتش را از کدام دسته‌های ووکامرس بخواند. دکمه «تشخیص خودکار» دسته‌ها را از روی نامشان پیدا می‌کند.',
				'fields' => array(
					self::f( 'scope_cats', 'terms', 'دسته‌(های) محصولات تنیسی', array( 'help' => 'محدوده کلی لندینگ (مثلاً دسته «تنیسی»). خالی = همه محصولات فروشگاه.' ) ),
					self::f( 'eco_cat', 'term', 'دسته «اکونومی»' ),
					self::f( 'master_cat', 'term', 'دسته «مستر کوالیتی»' ),
					self::f( 'eco_label', 'text', 'برچسب کیفیت اکونومی' ),
					self::f( 'master_label', 'text', 'برچسب کیفیت مستر' ),
					self::f(
						'clasp_mode',
						'select',
						'منبع نوع قفل روی کارت‌ها',
						array(
							'options' => array(
								'category'  => 'دسته‌بندی ووکامرس',
								'attribute' => 'ویژگی (Attribute) محصول',
								'none'      => 'نمایش داده نشود',
							),
						)
					),
					self::f( 'clasp_book_cat', 'term', 'دسته «قفل کتابی»' ),
					self::f( 'clasp_cuban_cat', 'term', 'دسته «قفل کوبایی»' ),
					self::f( 'clasp_book_label', 'text', 'برچسب قفل کتابی' ),
					self::f( 'clasp_cuban_label', 'text', 'برچسب قفل کوبایی' ),
					self::f( 'clasp_attr', 'attribute', 'ویژگی نوع قفل', array( 'help' => 'در حالت «ویژگی»، مقدار این ویژگی روی کارت نمایش داده می‌شود.' ) ),
					self::f( 'clasp_prefix', 'text', 'پیشوند برچسب ویژگی', array( 'help' => 'مثلاً «قفل » تا مقدار «کوبایی» به «قفل کوبایی» تبدیل شود.' ) ),
					self::f( 'low_stock', 'number', 'آستانه «فقط X عدد باقی‌مانده»', array( 'min' => 0, 'max' => 1000 ) ),
					self::f( 'hide_out_of_stock', 'toggle', 'محصولات ناموجود نمایش داده نشوند' ),
					self::f(
						'variable_mode',
						'select',
						'رفتار دکمه سبد برای محصولات متغیر',
						array(
							'options' => array(
								'popup' => 'پاپ‌آپ انتخاب سریع (سایز/رنگ) و افزودن AJAX',
								'link'  => 'رفتن به صفحه محصول',
							),
						)
					),
				),
			),
			'announce' => array(
				'title'  => 'نوار اعلان',
				'icon'   => 'dashicons-megaphone',
				'fields' => array(
					self::f( 'enabled', 'toggle', 'نمایش نوار اعلان' ),
					self::f( 'text', 'text', 'متن دسکتاپ' ),
					self::f( 'text_mobile', 'text', 'متن موبایل (کوتاه‌تر)' ),
					self::f( 'link', 'url', 'لینک (اختیاری)' ),
				),
			),
			'header'   => array(
				'title'  => 'هدر و منو',
				'icon'   => 'dashicons-menu-alt',
				'fields' => array(
					self::f(
						'nav',
						'repeater',
						'منوی دسکتاپ',
						array(
							'item_label' => 't',
							'fields'     => array(
								self::f( 't', 'text', 'عنوان' ),
								self::f( 'href', 'url', 'لینک' ),
							),
						)
					),
					self::f( 'show_search', 'toggle', 'دکمه جستجو در هدر' ),
					self::f( 'search_placeholder', 'text', 'متن راهنمای جستجو' ),
					self::f( 'cart_label', 'text', 'متن دکمه سبد' ),
					self::f( 'drawer_cats_title', 'text', 'عنوان دسته‌ها در منوی موبایل' ),
					self::f(
						'drawer_links',
						'repeater',
						'لینک‌های منوی موبایل',
						array(
							'item_label' => 't',
							'fields'     => array(
								self::f( 't', 'text', 'عنوان' ),
								self::f( 'href', 'url', 'لینک' ),
								self::f( 'badge', 'text', 'برچسب (اختیاری)' ),
								self::f( 'icon', 'icon', 'آیکن' ),
							),
						)
					),
				),
			),
			'hero'     => array(
				'title'  => 'هیرو',
				'icon'   => 'dashicons-format-image',
				'fields' => array(
					self::f( 'enabled', 'toggle', 'نمایش' ),
					self::f( 'title', 'text', 'تیتر اصلی (H1)' ),
					self::f( 'lead', 'textarea', 'متن معرفی (دسکتاپ)' ),
					self::f( 'lead_mobile', 'textarea', 'متن معرفی (موبایل)' ),
					self::f( 'cta_text', 'text', 'متن دکمه' ),
					self::f( 'cta_link', 'url', 'لینک دکمه' ),
					self::f( 'image', 'image', 'گیف / انیمیشن روی ویترین', array( 'help' => 'GIF یا WebP متحرک. فایل اصلی بدون هیچ تغییر و فشرده‌سازی، با کیفیت کامل نمایش داده می‌شود و از همان لحظه اول پخش می‌شود. برای وضوح بهتر در نمایشگرهای رتینا، عرض حداقل ۱۲۰۰ پیکسل پیشنهاد می‌شود.' ) ),
					self::f( 'poster', 'image', 'فریم اول (نمایش تا لحظه لود گیف)', array( 'help' => 'اختیاری؛ تا وقتی گیف دانلود شود زیر آن دیده می‌شود تا ویترین خالی نماند و بعد خودکار مخفی می‌شود. خالی = بدون جایگزین موقت.' ) ),
					self::f( 'image_alt', 'text', 'متن جایگزین تصویر' ),
					self::f( 'pedestal', 'toggle', 'نمایش پایه شیشه‌ای ویترین' ),
				),
			),
			'trust'    => array(
				'title'  => 'مزایای خرید',
				'icon'   => 'dashicons-awards',
				'fields' => array(
					self::f( 'enabled', 'toggle', 'نمایش' ),
					self::f(
						'items',
						'repeater',
						'آیتم‌ها',
						array(
							'item_label' => 't',
							'fields'     => array(
								self::f( 't', 'text', 'عنوان' ),
								self::f( 's', 'text', 'زیرعنوان' ),
								self::f( 'icon', 'icon', 'آیکن' ),
							),
						)
					),
				),
			),
			'cats'     => array(
				'title'  => 'دسته‌بندی‌ها',
				'icon'   => 'dashicons-category',
				'fields' => array(
					self::f( 'enabled', 'toggle', 'نمایش' ),
					self::f( 'title', 'text', 'عنوان' ),
					self::f( 'subtitle', 'text', 'زیرعنوان' ),
					self::f(
						'items',
						'repeater',
						'کارت‌های دسته',
						array(
							'item_label' => 'name',
							'help'       => 'اگر دسته ووکامرس انتخاب شود و همان دسته در فیلترهای «همه محصولات» باشد، کلیک روی کارت جدول را فیلتر می‌کند؛ در غیر این صورت به آرشیو دسته می‌رود.',
							'fields'     => array(
								self::f( 'name', 'text', 'نام' ),
								self::f( 'image', 'image', 'تصویر' ),
								self::f( 'term', 'term', 'دسته ووکامرس' ),
								self::f( 'url', 'url', 'لینک دلخواه' ),
							),
						)
					),
				),
			),
			'festival' => array(
				'title'  => 'جشنواره',
				'icon'   => 'dashicons-clock',
				'fields' => array(
					self::f( 'enabled', 'toggle', 'نمایش' ),
					self::f( 'label', 'text', 'برچسب بالای عنوان' ),
					self::f( 'title', 'textarea', 'عنوان', array( 'help' => 'هر خط جدید در دسکتاپ به خط بعد می‌رود.' ) ),
					self::f( 'desc', 'textarea', 'توضیح', array( 'help' => '{max} = بیشترین درصد تخفیف بین محصولات جشنواره (خودکار).' ) ),
					self::f( 'end_ts', 'jdatetime', 'پایان جشنواره (شمسی، به وقت تهران)', array( 'help' => 'شمارش معکوس تا این زمان. خالی = بدون شمارش معکوس.' ) ),
					self::f( 'hide_expired', 'toggle', 'پس از پایان زمان، سکشن مخفی شود' ),
					self::f(
						'source',
						'select',
						'منبع محصولات',
						array(
							'options' => array(
								'auto'   => 'خودکار: محصولات دارای قیمت فروش ویژه (حراج) ووکامرس',
								'manual' => 'انتخاب دستی محصولات',
							),
						)
					),
					self::f( 'products', 'products', 'محصولات دستی' ),
					self::f( 'limit', 'number', 'حداکثر تعداد', array( 'min' => 1, 'max' => 30 ) ),
					self::f( 'show_card_timer', 'toggle', 'تایمر روی هر کارت (از تاریخ پایان حراج محصول)' ),
					self::f( 'btn_text', 'text', 'متن دکمه «همه تخفیف‌ها»', array( 'help' => 'جدول همه محصولات را روی «تخفیف‌دار» فیلتر می‌کند.' ) ),
				),
			),
			'economy'  => array(
				'title'  => 'ردیف اکونومی',
				'icon'   => 'dashicons-tag',
				'fields' => self::row_fields( 'economy' ),
			),
			'master'   => array(
				'title'  => 'ردیف مستر کوالیتی',
				'icon'   => 'dashicons-star-filled',
				'fields' => self::row_fields( 'master' ),
			),
			'grid'     => array(
				'title'  => 'همه محصولات',
				'icon'   => 'dashicons-grid-view',
				'fields' => array(
					self::f( 'enabled', 'toggle', 'نمایش' ),
					self::f( 'title', 'text', 'عنوان' ),
					self::f( 'per_page', 'number', 'تعداد در هر بار نمایش', array( 'min' => 4, 'max' => 48 ) ),
					self::f( 'orderby', 'select', 'مرتب‌سازی', array( 'options' => self::orderby_options() ) ),
					self::f( 'more_text', 'text', 'متن دکمه نمایش بیشتر' ),
					self::f( 'empty_text', 'text', 'متن نبود محصول' ),
					self::f(
						'chips',
						'repeater',
						'دکمه‌های فیلتر',
						array(
							'item_label' => 'label',
							'help'       => 'فیلترهایی که دسته‌شان انتخاب نشده باشد نمایش داده نمی‌شوند.',
							'fields'     => array(
								self::f( 'label', 'text', 'عنوان' ),
								self::f( 'filter', 'filter', 'فیلتر' ),
							),
						)
					),
				),
			),
			'reviews'  => array(
				'title'  => 'نظر خریداران',
				'icon'   => 'dashicons-format-quote',
				'fields' => array(
					self::f( 'enabled', 'toggle', 'نمایش' ),
					self::f( 'title', 'text', 'عنوان' ),
					self::f(
						'source',
						'select',
						'کارت‌های نظر',
						array(
							'options' => array(
								'auto'   => 'خودکار از نظرات تأییدشده ووکامرس (در صورت نبود، دستی)',
								'manual' => 'فقط نظرات دستی',
							),
						)
					),
					self::f( 'min_rating', 'number', 'حداقل امتیاز نظرات خودکار', array( 'min' => 1, 'max' => 5 ) ),
					self::f( 'limit', 'number', 'تعداد نظرات خودکار', array( 'min' => 1, 'max' => 20 ) ),
					self::f(
						'avg_mode',
						'select',
						'میانگین امتیاز',
						array(
							'options' => array(
								'auto'   => 'خودکار از امتیاز واقعی محصولات تنیسی',
								'manual' => 'دستی',
							),
						)
					),
					self::f( 'avg_manual', 'text', 'میانگین دستی (مثلاً 4.8)' ),
					self::f( 'count_manual', 'number', 'تعداد نظرات (دستی)', array( 'min' => 0, 'max' => 1000000 ) ),
					self::f( 'count_text', 'text', 'متن تعداد', array( 'help' => '{count} = تعداد نظرات.' ) ),
					self::f(
						'items',
						'repeater',
						'نظرات دستی',
						array(
							'item_label' => 'name',
							'fields'     => array(
								self::f( 'text', 'textarea', 'متن نظر' ),
								self::f( 'name', 'text', 'نام و شهر' ),
								self::f( 'product', 'text', 'محصول خریداری‌شده' ),
							),
						)
					),
				),
			),
			'what'     => array(
				'title'  => 'تنیسی چیست؟',
				'icon'   => 'dashicons-info-outline',
				'fields' => array(
					self::f( 'enabled', 'toggle', 'نمایش' ),
					self::f( 'en', 'text', 'برچسب انگلیسی' ),
					self::f( 'title', 'text', 'عنوان' ),
					self::f( 'body', 'html', 'متن', array( 'help' => 'خط خالی = پاراگراف جدید. لینک و بولد مجاز است.' ) ),
					self::f( 'image', 'image', 'تصویر' ),
					self::f( 'alt', 'text', 'متن جایگزین تصویر' ),
				),
			),
			'types'    => array(
				'title'  => 'انواع تنیسی',
				'icon'   => 'dashicons-screenoptions',
				'fields' => array(
					self::f( 'enabled', 'toggle', 'نمایش' ),
					self::f( 'title', 'text', 'عنوان' ),
					self::f( 'desc', 'textarea', 'توضیح' ),
					self::f( 'link_text', 'text', 'متن لینک کارت‌ها' ),
					self::f(
						'items',
						'repeater',
						'کارت‌ها',
						array(
							'item_label' => 'name',
							'help'       => $link_help,
							'fields'     => array(
								self::f( 'name', 'text', 'عنوان' ),
								self::f( 'desc', 'textarea', 'توضیح' ),
								self::f( 'image', 'image', 'تصویر' ),
								self::f( 'term', 'term', 'دسته ووکامرس' ),
								self::f( 'url', 'url', 'لینک دلخواه' ),
							),
						)
					),
					self::f( 'shapes_title', 'text', 'عنوان فرم نگین' ),
					self::f(
						'shapes',
						'repeater',
						'فرم‌های نگین',
						array(
							'item_label' => 'label',
							'help'       => $link_help,
							'fields'     => array(
								self::f( 'label', 'text', 'عنوان' ),
								self::f( 'term', 'term', 'دسته ووکامرس' ),
								self::f( 'url', 'url', 'لینک دلخواه' ),
							),
						)
					),
				),
			),
			'guide'    => array(
				'title'  => 'راهنمای خرید',
				'icon'   => 'dashicons-book-alt',
				'fields' => array(
					self::f( 'enabled', 'toggle', 'نمایش' ),
					self::f( 'title', 'text', 'عنوان' ),
					self::f( 'desc', 'textarea', 'توضیح' ),
					self::f(
						'steps',
						'repeater',
						'مراحل',
						array(
							'item_label' => 't',
							'fields'     => array(
								self::f( 't', 'text', 'عنوان' ),
								self::f( 'd', 'textarea', 'توضیح' ),
							),
						)
					),
					self::f( 'clasp_title', 'text', 'عنوان مقایسه قفل‌ها' ),
					self::f( 'book_badge', 'text', 'قفل کتابی — برچسب' ),
					self::f( 'book_title', 'text', 'قفل کتابی — عنوان' ),
					self::f( 'book_desc', 'textarea', 'قفل کتابی — توضیح' ),
					self::f( 'book_image', 'image', 'قفل کتابی — تصویر' ),
					self::f( 'book_alt', 'text', 'قفل کتابی — alt' ),
					self::f( 'cuban_badge', 'text', 'قفل کوبایی — برچسب' ),
					self::f( 'cuban_title', 'text', 'قفل کوبایی — عنوان' ),
					self::f( 'cuban_desc', 'textarea', 'قفل کوبایی — توضیح' ),
					self::f( 'cuban_image', 'image', 'قفل کوبایی — تصویر' ),
					self::f( 'cuban_alt', 'text', 'قفل کوبایی — alt' ),
					self::f( 'table_h1', 'text', 'جدول — ستون ۱' ),
					self::f( 'table_h2', 'text', 'جدول — ستون ۲' ),
					self::f( 'table_h3', 'text', 'جدول — ستون ۳' ),
					self::f(
						'rows',
						'repeater',
						'ردیف‌های جدول',
						array(
							'item_label' => 'f',
							'fields'     => array(
								self::f( 'f', 'text', 'ویژگی' ),
								self::f( 'a', 'text', 'قفل کتابی' ),
								self::f( 'b', 'text', 'قفل کوبایی' ),
							),
						)
					),
				),
			),
			'care'     => array(
				'title'  => 'نکات نگهداری',
				'icon'   => 'dashicons-heart',
				'fields' => array(
					self::f( 'enabled', 'toggle', 'نمایش' ),
					self::f( 'title', 'text', 'عنوان' ),
					self::f( 'desc', 'textarea', 'توضیح' ),
					self::f(
						'items',
						'repeater',
						'نکته‌ها',
						array(
							'item_label' => 't',
							'fields'     => array(
								self::f( 't', 'text', 'عنوان' ),
								self::f( 'd', 'textarea', 'توضیح' ),
								self::f( 'icon', 'icon', 'آیکن' ),
							),
						)
					),
				),
			),
			'faq'      => array(
				'title'  => 'سؤالات پرتکرار',
				'icon'   => 'dashicons-editor-help',
				'fields' => array(
					self::f( 'enabled', 'toggle', 'نمایش' ),
					self::f( 'title', 'text', 'عنوان' ),
					self::f( 'schema', 'toggle', 'اسکیما FAQPage برای گوگل' ),
					self::f(
						'items',
						'repeater',
						'سؤال‌ها',
						array(
							'item_label' => 'q',
							'fields'     => array(
								self::f( 'q', 'text', 'سؤال' ),
								self::f( 'a', 'html', 'پاسخ' ),
								self::f( 'open', 'toggle', 'به‌صورت پیش‌فرض باز باشد' ),
							),
						)
					),
				),
			),
			'related'  => array(
				'title'  => 'دسته‌های مرتبط',
				'icon'   => 'dashicons-networking',
				'fields' => array(
					self::f( 'enabled', 'toggle', 'نمایش' ),
					self::f( 'title', 'text', 'عنوان' ),
					self::f(
						'items',
						'repeater',
						'کارت‌ها',
						array(
							'item_label' => 'name',
							'help'       => $link_help,
							'fields'     => array(
								self::f( 'name', 'text', 'عنوان' ),
								self::f( 'image', 'image', 'تصویر' ),
								self::f( 'alt', 'text', 'متن جایگزین تصویر' ),
								self::f( 'term', 'term', 'دسته ووکامرس' ),
								self::f( 'url', 'url', 'لینک دلخواه' ),
							),
						)
					),
				),
			),
			'footer'   => array(
				'title'  => 'فوتر و نوار موبایل',
				'icon'   => 'dashicons-align-wide',
				'fields' => array(
					self::f(
						'links',
						'repeater',
						'لینک‌های فوتر',
						array(
							'item_label' => 't',
							'fields'     => array(
								self::f( 't', 'text', 'عنوان' ),
								self::f( 'href', 'url', 'لینک' ),
							),
						)
					),
					self::f( 'domain', 'text', 'متن دامنه' ),
					self::f( 'bar_enabled', 'toggle', 'نوار خرید شناور موبایل' ),
					self::f( 'bar_label', 'text', 'نوار موبایل — برچسب' ),
					self::f(
						'bar_price_mode',
						'select',
						'نوار موبایل — قیمت',
						array(
							'options' => array(
								'auto'   => 'خودکار: کمترین قیمت محصولات تنیسی',
								'manual' => 'متن دستی',
							),
						)
					),
					self::f( 'bar_price_manual', 'text', 'نوار موبایل — قیمت دستی' ),
					self::f( 'bar_btn', 'text', 'نوار موبایل — متن دکمه' ),
					self::f( 'bar_link', 'url', 'نوار موبایل — لینک دکمه' ),
				),
			),
			'advanced' => array(
				'title'  => 'پیشرفته و سرعت',
				'icon'   => 'dashicons-performance',
				'fields' => array(
					self::f(
						'isolation',
						'select',
						'ایزوله‌سازی از قالب',
						array(
							'options' => array(
								'theme'  => 'استاندارد: CSS/JS قالب و استایل‌های بلاک/ووکامرس بارگذاری نشوند (پیشنهادی)',
								'strict' => 'سخت‌گیرانه: فقط فایل‌های ضروری (اسکریپت‌های آنالیتیکس افزونه‌ها هم حذف می‌شوند)',
								'none'   => 'بدون ایزوله‌سازی',
							),
						)
					),
					self::f( 'extra_dequeue', 'textarea', 'هندل‌های اضافی برای حذف', array( 'help' => 'هر هندل CSS/JS در یک خط.' ) ),
					self::f( 'theme_layout', 'toggle', 'استفاده از هدر و فوتر قالب', array( 'help' => 'به‌جای هدر/فوتر لندینگ، هدر و فوتر قالب سایت نمایش داده می‌شود (ایزوله‌سازی غیرفعال می‌شود).' ) ),
					self::f( 'animations', 'toggle', 'انیمیشن‌ها و افکت‌های درخشش' ),
					self::f( 'sparkles', 'number', 'تعداد ستاره‌های درخشان پس‌زمینه', array( 'min' => 0, 'max' => 40 ) ),
					self::f( 'schema_itemlist', 'toggle', 'اسکیما ItemList محصولات' ),
					self::f( 'cache_hours', 'number', 'مدت کش کوئری محصولات (ساعت)', array( 'min' => 0, 'max' => 168, 'help' => 'با ذخیره محصول، تغییر موجودی و حراج‌ها به‌صورت خودکار پاک می‌شود. ۰ = بدون کش.' ) ),
					self::f( 'custom_css', 'code', 'CSS سفارشی' ),
				),
			),
		);
	}
}
