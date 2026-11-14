<?php
/**
 * Admin schema: every tab, card and field of the settings screen + sanitizing.
 *
 * Field keys: k (key) t (type) l (label) h (help) w (width: full|half|third) ph (placeholder)
 * o (options) min max step fields (repeater) add (add-button label) title (repeater row title field)
 * show (condition "field=value"; "^field=value" looks in the parent row) sec (section override)
 */

defined( 'ABSPATH' ) || exit;

class YL_Schema {

	/** Sections that can be reordered / switched off. */
	public static function sections() {
		return array(
			'yaldaf' => 'یلدآف (محصولات تخفیف‌دار)',
			'build'  => 'فرم «پک خودتان را بسازید»',
			'recent' => 'آخرین پک‌های ساخته‌شده',
			'proof'  => 'نظر شرکت‌ها',
			'fal'    => 'فال حافظ',
			'seo'    => 'متن سئو',
			'faq'    => 'سوالات متداول',
		);
	}

	private static function title_card( $nav = true, $sub = true, $note = false ) {
		$f = array();
		if ( $nav ) {
			$f[] = array( 'k' => 'nav', 't' => 'text', 'l' => 'عنوان در منوی بالا', 'h' => 'خالی بگذارید تا این بخش در منو نیاید.', 'w' => 'half' );
		}
		$f[] = array( 'k' => 'title_alt', 't' => 'text', 'l' => 'عنوان بخش', 'h' => 'متن تیتر برای گوگل؛ اگر تصویر تیتر را حذف کنید همین متن نمایش داده می‌شود.', 'w' => $nav ? 'half' : 'full' );
		$f[] = array( 'k' => 'title_img', 't' => 'image', 'l' => 'تصویر تیتر (متن کاموایی)', 'h' => 'PNG یا WebP شفاف با عرض حدود ۱۲۰۰ پیکسل. خودکار به WebP سبک تبدیل می‌شود.', 'w' => 'full', 'def' => true );
		if ( $sub ) {
			$f[] = array( 'k' => 'sub', 't' => 'text', 'l' => 'زیرعنوان', 'w' => 'full' );
		}
		if ( $note ) {
			$f[] = array( 'k' => 'note', 't' => 'text', 'l' => 'برچسب زرد زیر عنوان (اختیاری)', 'h' => 'مثلاً «ارسال رایگان برای سفارش‌های بالای ۵۰ پک». خالی = نمایش داده نمی‌شود.', 'w' => 'full' );
		}
		return array( 'title' => 'عنوان بخش', 'icon' => 'heading', 'fields' => $f );
	}

	/** Product row fields shared by deals / catalog / recent. */
	private static function product_fields( $extra = array() ) {
		return array_merge( array(
			array( 'k' => 'src', 't' => 'seg', 'l' => 'منبع محصول', 'o' => array( 'woo' => 'از ووکامرس', 'manual' => 'ورود دستی' ), 'def' => 'woo', 'w' => 'full' ),
			array( 'k' => 'pid', 't' => 'product', 'l' => 'محصول ووکامرس', 'h' => 'نام، قیمت، تصویر و لینک از خود محصول خوانده می‌شود و همیشه به‌روز است.', 'show' => 'src=woo', 'w' => 'full' ),
			array( 'k' => 'name', 't' => 'text', 'l' => 'نام پک', 'show' => 'src=manual', 'w' => 'half' ),
			array( 'k' => 'price', 't' => 'money', 'l' => 'قیمت (تومان)', 'show' => 'src=manual', 'w' => 'half' ),
			array( 'k' => 'url', 't' => 'url', 'l' => 'لینک صفحه‌ی محصول', 'show' => 'src=manual', 'w' => 'full', 'ph' => 'https://' ),
			array( 'k' => 'img', 't' => 'image', 'l' => 'تصویر محصول', 'h' => 'مربعی، حداقل ۶۰۰×۶۰۰ پیکسل.', 'show' => 'src=manual', 'w' => 'full' ),
		), $extra );
	}

	public static function tabs() {
		$moods = self::mood_options();

		return array(
			array(
				'id'    => 'start',
				'sec'   => 'general',
				'group' => 'پایه',
				'title' => 'شروع و صفحه',
				'icon'  => 'home',
				'desc'  => 'صفحه‌ای که لندینگ روی آن نمایش داده می‌شود، اطلاعات برند و زمان شب یلدا.',
				'cards' => array(
					array(
						'title'  => 'صفحه‌ی لندینگ',
						'icon'   => 'page',
						'status' => true,
						'fields' => array(
							array( 'k' => 'page_id', 't' => 'page', 'l' => 'لندینگ روی این صفحه نمایش داده شود', 'h' => 'محتوای فعلی آن صفحه و قالب سایت کنار می‌روند و لندینگ به‌صورت تمام‌صفحه نمایش داده می‌شود.', 'w' => 'full' ),
						),
					),
					array(
						'title'  => 'برند و تماس',
						'icon'   => 'brand',
						'fields' => array(
							array( 'k' => 'brand', 't' => 'text', 'l' => 'نام برند', 'w' => 'half' ),
							array( 'k' => 'logo_alt', 't' => 'text', 'l' => 'متن جایگزین لوگو', 'w' => 'half' ),
							array( 'k' => 'logo', 't' => 'image', 'l' => 'لوگو', 'h' => 'در کادر سفید کوچک بالای صفحه می‌نشیند.', 'w' => 'full', 'def' => true ),
							array( 'k' => 'phone', 't' => 'tel', 'l' => 'شماره تماس (برای لینک تماس)', 'ph' => '03191090203', 'w' => 'half' ),
							array( 'k' => 'phone_show', 't' => 'text', 'l' => 'شماره به شکل نمایشی', 'ph' => '۰۳۱-۹۱۰۹۰۲۰۳', 'w' => 'half' ),
						),
					),
					array(
						'title'  => 'زمان شب یلدا (شمارش معکوس)',
						'icon'   => 'clock',
						'desc'   => 'همه‌ی شمارنده‌های صفحه («تا شب چله» و «… روز تا شب چله») تا این لحظه می‌شمارند. ساعت به وقت تهران است.',
						'fields' => array(
							array( 'k' => 'target', 't' => 'jalali', 'l' => 'تاریخ و ساعت', 'w' => 'full' ),
						),
					),
					array(
						'title'  => 'سئو',
						'icon'   => 'search',
						'fields' => array(
							array( 'k' => 'h1', 't' => 'text', 'l' => 'تیتر اصلی صفحه (H1)', 'h' => 'برای گوگل؛ در ظاهر صفحه دیده نمی‌شود چون تیتر هیرو تصویری است.', 'w' => 'full' ),
							array( 'k' => 'faq_schema', 't' => 'toggle', 'l' => 'اسکیمای سوالات متداول (FAQ) برای گوگل', 'w' => 'full' ),
						),
					),
				),
			),
			array(
				'id'    => 'look',
				'sec'   => 'general',
				'group' => 'پایه',
				'title' => 'چیدمان و ظاهر',
				'icon'  => 'layout',
				'desc'  => 'ترتیب بخش‌ها، روشن/خاموش کردن هر بخش، رنگ‌ها و جلوه‌ها.',
				'cards' => array(
					array(
						'title'  => 'ترتیب و نمایش بخش‌ها',
						'icon'   => 'layers',
						'desc'   => 'با کشیدن ⠿ ترتیب بخش‌ها را عوض کنید و با کلید هر ردیف آن را روشن یا خاموش کنید. هیرو همیشه اول و بخش پایانی همیشه آخر است.',
						'fields' => array(
							array( 'k' => 'order', 't' => 'order', 'l' => '', 'w' => 'full' ),
						),
					),
					array(
						'title'  => 'رنگ‌ها',
						'icon'   => 'palette',
						'fields' => array(
							array( 'k' => 'c_night', 't' => 'color', 'l' => 'پس‌زمینه (آسمان شب)', 'w' => 'half' ),
							array( 'k' => 'c_amb', 't' => 'color', 'l' => 'دکمه‌ها و قیمت‌ها', 'w' => 'half' ),
							array( 'k' => 'c_red', 't' => 'color', 'l' => 'برچسب تخفیف', 'w' => 'half' ),
							array( 'k' => 'c_cream', 't' => 'color', 'l' => 'کرم (کارت فرم و دکمه‌های روشن)', 'w' => 'half' ),
						),
					),
					array(
						'title'  => 'پس‌زمینه و جلوه‌ها',
						'icon'   => 'sparkle',
						'fields' => array(
							array( 'k' => 'bg', 't' => 'image', 'l' => 'بافت پس‌زمینه', 'h' => 'تصویر تکرارشونده‌ی بافت کاموا.', 'w' => 'full', 'def' => true ),
							array( 'k' => 'deco', 't' => 'toggle', 'l' => 'ماه، ابر و ستاره‌های کاموایی شناور', 'w' => 'third' ),
							array( 'k' => 'drift', 't' => 'toggle', 'l' => 'حرکت نرم المان‌ها با موس', 'w' => 'third' ),
							array( 'k' => 'reveal', 't' => 'toggle', 'l' => 'ظاهر شدن نرم بخش‌ها با اسکرول', 'w' => 'third' ),
						),
					),
					array(
						'title'  => 'سبکی و سرعت',
						'icon'   => 'bolt',
						'fields' => array(
							array( 'k' => 'isolate', 't' => 'toggle', 'l' => 'حذف فایل‌های قالب و ووکامرس از صفحه‌ی لندینگ', 'h' => 'صفحه سبک‌تر می‌شود و استایل قالب روی لندینگ اثر نمی‌گذارد. کدهای آمار و افزونه‌های سئو حفظ می‌شوند.', 'w' => 'full' ),
							array( 'k' => 'shield', 't' => 'toggle', 'l' => 'محافظت لینک‌های لندینگ در برابر اسکریپت‌های قالب و افزونه‌ها', 'h' => 'جلوی هنگ صفحه با کلیک روی لینک‌ها (پک‌ها، فوتر، تلفن) را می‌گیرد؛ اسکریپت‌های بیرونی مثل پیش‌لودر و بارگذاری ایجکسی دیگر کلیک لینک‌های لندینگ را دریافت نمی‌کنند و مرورگر خودش لینک را باز می‌کند. در صورت نیاز ابزار آمار به ردیابی کلیک لینک‌ها، می‌توانید خاموشش کنید.', 'w' => 'full' ),
						),
					),
					array(
						'title'  => 'ابزارک افزونه‌های دیگر',
						'icon'   => 'layers',
						'fields' => array(
							array( 'k' => 'block_plugins', 't' => 'tags', 'l' => 'این افزونه‌ها روی لندینگ غیرفعال شوند', 'o' => YL_Block::plugin_options(), 'empty' => 'افزونه‌ی فعال دیگری پیدا نشد.', 'h' => 'فقط در صفحه‌ی لندینگ؛ بقیه‌ی سایت دست نمی‌خورد. افزونه‌هایی که علامت ● دارند روی لندینگ چیزی (اسکریپت، استایل یا ابزارک) قرار می‌دهند. افزونه‌های آمار و چت (مثل موچت) را تیک نزنید تا بمانند.', 'w' => 'full' ),
							array( 'k' => 'block_words', 't' => 'textarea', 'l' => 'حذف کدها و ابزارک‌هایی که این کلمه‌ها را دارند', 'ph' => 'afracc', 'h' => 'هر کلمه در یک خط (دست‌کم ۴ حرف). هر اسکریپت، استایل یا iframe که این کلمه در آدرس یا کدش باشد حذف می‌شود و هر المانی که در id یا class آن باشد پنهان می‌شود؛ افزونه‌ای هم که نام پوشه‌اش این کلمه را داشته باشد غیرفعال می‌شود. برای کدهایی که مستقیم در هدر/فوتر سایت چسبانده شده‌اند هم کار می‌کند. کلمه‌ی خاص بنویسید (مثل نام دامنه‌ی ابزارک)، نه کلمه‌های عمومی مثل chat.', 'w' => 'full' ),
						),
					),
				),
			),
			array(
				'id'    => 'hero',
				'sec'   => 'hero',
				'group' => 'بخش‌های صفحه',
				'title' => 'هدر و هیرو',
				'icon'  => 'hero',
				'desc'  => 'نوار بالای صفحه و اولین چیزی که بازدیدکننده می‌بیند.',
				'cards' => array(
					array(
						'title'  => 'منوی بالا',
						'icon'   => 'menu',
						'sec'    => 'general',
						'fields' => array(
							array( 'k' => 'nav_on', 't' => 'toggle', 'l' => 'نمایش لینک بخش‌ها در منو (دسکتاپ)', 'h' => 'عنوان هر لینک در تب همان بخش تنظیم می‌شود.', 'w' => 'half' ),
							array( 'k' => 'nav_cta', 't' => 'text', 'l' => 'متن دکمه‌ی منو', 'w' => 'half' ),
						),
					),
					array(
						'title'  => 'تصاویر هیرو',
						'icon'   => 'image',
						'fields' => array(
							array( 'k' => 'title_img', 't' => 'image', 'l' => 'تیتر تصویری («یلدای پرستاره…»)', 'h' => 'عرض حدود ۱۲۰۰ پیکسل، پس‌زمینه‌ی شفاف.', 'w' => 'half', 'def' => true ),
							array( 'k' => 'still_img', 't' => 'image', 'l' => 'تصویر اصلی (دیوان حافظ و سینی)', 'h' => 'عرض حدود ۱۰۰۰ پیکسل، پس‌زمینه‌ی شفاف.', 'w' => 'half', 'def' => true ),
							array( 'k' => 'title_alt', 't' => 'text', 'l' => 'متن جایگزین تیتر تصویری', 'w' => 'half' ),
							array( 'k' => 'still_alt', 't' => 'text', 'l' => 'متن جایگزین تصویر اصلی', 'w' => 'half' ),
						),
					),
					array(
						'title'  => 'متن و دکمه',
						'icon'   => 'text',
						'fields' => array(
							array( 'k' => 'text', 't' => 'textarea', 'l' => 'متن کوتاه زیر تیتر', 'w' => 'full' ),
							array( 'k' => 'cta', 't' => 'text', 'l' => 'متن دکمه', 'w' => 'half' ),
							array( 'k' => 'sub', 't' => 'text', 'l' => 'متن کوچک زیر دکمه (فقط موبایل)', 'w' => 'half' ),
						),
					),
					array(
						'title'  => 'شمارنده‌ی هیرو',
						'icon'   => 'clock',
						'fields' => array(
							array( 'k' => 'cd_on', 't' => 'toggle', 'l' => 'نمایش شمارنده (روز / ساعت / دقیقه)', 'w' => 'half' ),
							array( 'k' => 'cd_label', 't' => 'text', 'l' => 'برچسب شمارنده', 'w' => 'half' ),
						),
					),
				),
			),
			array(
				'id'     => 'yaldaf',
				'sec'    => 'yaldaf',
				'group'  => 'بخش‌های صفحه',
				'title'  => 'یلدآف (تخفیف‌ها)',
				'icon'   => 'tag',
				'toggle' => true,
				'desc'   => 'ریل محصولات تخفیف‌دار با برچسب درصد و دکمه‌ی «سفارش برای تیم».',
				'cards'  => array(
					self::title_card( true, true, true ),
					array(
						'title'  => 'ساعت «پایان تخفیف این ساعت»',
						'icon'   => 'clock',
						'fields' => array(
							array( 'k' => 'clock_on', 't' => 'toggle', 'l' => 'نمایش ساعت', 'w' => 'half' ),
							array( 'k' => 'clock_label', 't' => 'text', 'l' => 'متن کنار ساعت', 'w' => 'half' ),
						),
					),
					array(
						'title'  => 'حرکت اسلایدر',
						'icon'   => 'swap',
						'fields' => array(
							array( 'k' => 'auto', 't' => 'toggle', 'l' => 'جلو رفتن خودکار محصولات', 'h' => 'با بردن موس روی اسلایدر، لمس آن یا زدن فلش‌ها مکث می‌کند و در انتها به اولین محصول برمی‌گردد.', 'w' => 'half' ),
							array( 'k' => 'auto_s', 't' => 'number', 'l' => 'هر چند ثانیه یک محصول', 'min' => 2, 'max' => 15, 'w' => 'half' ),
						),
					),
					array(
						'title'  => 'محصولات تخفیف‌دار',
						'icon'   => 'cart',
						'fields' => array(
							array( 'k' => 'source', 't' => 'seg', 'l' => 'محصولات از کجا بیایند؟', 'o' => array( 'list' => 'انتخاب دستی (فهرست زیر)', 'woo' => 'خودکار: محصولات حراج ووکامرس' ), 'w' => 'full' ),
							array( 'k' => 'woo_cat', 't' => 'pcat', 'l' => 'فقط از این دسته', 'show' => 'source=woo', 'w' => 'half' ),
							array( 'k' => 'woo_limit', 't' => 'number', 'l' => 'حداکثر تعداد', 'min' => 1, 'max' => 24, 'show' => 'source=woo', 'w' => 'half' ),
							array( 'k' => 'woo_note', 't' => 'note', 'l' => 'محصولاتی که در ووکامرس «قیمت فروش ویژه» دارند (یا افزونه‌ی تخفیف آن‌ها را تخفیف‌دار کرده) به‌ترتیب جدیدترین نمایش داده می‌شوند و درصد تخفیف خودکار محاسبه می‌شود.', 'show' => 'source=woo', 'w' => 'full' ),
							array( 'k' => 'woo_report', 't' => 'note', 'l' => YL_Data::woo_report(), 'show' => 'source=woo', 'w' => 'full' ),
							array(
								'k'      => 'items',
								't'      => 'repeater',
								'l'      => 'فهرست محصولات',
								'add'    => 'افزودن محصول',
								'title'  => 'name',
								'show'   => 'source=list',
								'w'      => 'full',
								'fields' => self::product_fields( array(
									array( 'k' => 'pct', 't' => 'number', 'l' => 'درصد تخفیف', 'min' => 0, 'max' => 90, 'step' => 0.5, 'h' => 'محصول ووکامرس: صفر = همان تخفیف ووکامرس (قیمت فروش ویژه)؛ عدد = این درصد روی قیمت عادی حساب و در سبد خرید هم اعمال می‌شود.', 'w' => 'full' ),
								) ),
							),
						),
					),
					array(
						'title'  => 'متن‌ها',
						'icon'   => 'text',
						'fields' => array(
							array( 'k' => 'btn', 't' => 'text', 'l' => 'متن دکمه‌ی کارت', 'w' => 'half' ),
							array( 'k' => 'badge', 't' => 'text', 'l' => 'متن بعد از عدد درصد', 'h' => 'مثلاً «٪ تخفیف» ← «۱۲٪ تخفیف»', 'w' => 'half' ),
						),
					),
				),
			),
			array(
				'id'     => 'build',
				'sec'    => 'build',
				'group'  => 'بخش‌های صفحه',
				'title'  => 'فرم پک‌ساز',
				'icon'   => 'form',
				'toggle' => true,
				'desc'   => 'فرم چندمرحله‌ای «هدیه‌ی سفارشی خود را ایجاد کنید». بعد از ثبت، درخواست در «درخواست‌ها» ذخیره و پک‌های مناسب از «کاتالوگ پک‌ها» پیشنهاد می‌شود.',
				'cards'  => array(
					self::title_card( true, true, false ),
					array(
						'title'  => 'کارت فرم',
						'icon'   => 'form',
						'fields' => array(
							array( 'k' => 'card_title', 't' => 'text', 'l' => 'عنوان کارت', 'w' => 'full' ),
							array( 'k' => 'deco_img', 't' => 'image', 'l' => 'تصویر تزئینی گوشه‌ی کارت (هندوانه)', 'w' => 'full', 'def' => true ),
							array( 'k' => 'next', 't' => 'text', 'l' => 'دکمه‌ی مرحله‌ی بعد', 'w' => 'third' ),
							array( 'k' => 'prev', 't' => 'text', 'l' => 'دکمه‌ی مرحله‌ی قبل', 'w' => 'third' ),
							array( 'k' => 'send', 't' => 'text', 'l' => 'دکمه‌ی ثبت نهایی', 'w' => 'third' ),
						),
					),
					array(
						'title'  => 'مراحل و سؤال‌ها',
						'icon'   => 'list',
						'desc'   => 'هر ردیف یک مرحله است و مرحله‌ی آخر (اطلاعات تماس) خودکار اضافه می‌شود. «نوع سؤال» تعیین می‌کند پاسخ چطور در پیشنهاد پک استفاده شود.',
						'fields' => array(
							array(
								'k'      => 'steps',
								't'      => 'repeater',
								'l'      => '',
								'add'    => 'افزودن مرحله',
								'title'  => 'label',
								'w'      => 'full',
								'fields' => array(
									array( 'k' => 'type', 't' => 'select', 'l' => 'نوع سؤال', 'o' => array( 'general' => 'عمومی (فقط ثبت در درخواست)', 'mood' => 'حال‌وهوا (مطابق برچسب پک‌ها)', 'budget' => 'بودجه (فیلتر قیمت پک‌ها)' ), 'w' => 'half' ),
									array( 'k' => 'label', 't' => 'text', 'l' => 'نام مرحله', 'h' => 'کنار «گام ۱ از ۵» نوشته می‌شود.', 'w' => 'half' ),
									array( 'k' => 'q', 't' => 'text', 'l' => 'متن سؤال', 'w' => 'full' ),
									array( 'k' => 'hint', 't' => 'text', 'l' => 'راهنمای کوچک کنار سؤال', 'w' => 'full' ),
									array( 'k' => 'multi', 't' => 'toggle', 'l' => 'امکان انتخاب چند گزینه', 'w' => 'half' ),
									array( 'k' => 'chip', 't' => 'toggle', 'l' => 'نمایش پاسخ در خلاصه‌ی نتیجه', 'w' => 'half' ),
									array(
										'k'       => 'opts',
										't'       => 'repeater',
										'l'       => 'گزینه‌ها',
										'add'     => 'افزودن گزینه',
										'title'   => 'label',
										'inline'  => true,
										'w'       => 'full',
										'fields'  => array(
											array( 'k' => 'k', 't' => 'key' ),
											array( 'k' => 'label', 't' => 'text', 'l' => 'متن گزینه', 'w' => 'grow' ),
											array( 'k' => 'min', 't' => 'money', 'l' => 'از (تومان)', 'show' => '^type=budget', 'w' => 'third' ),
											array( 'k' => 'max', 't' => 'money', 'l' => 'تا (تومان، ۰ = بی‌سقف)', 'show' => '^type=budget', 'w' => 'third' ),
										),
									),
								),
							),
						),
					),
					array(
						'title'  => 'مرحله‌ی آخر: اطلاعات تماس',
						'icon'   => 'user',
						'fields' => array(
							array( 'k' => 'info_label', 't' => 'text', 'l' => 'نام مرحله', 'w' => 'half' ),
							array( 'k' => 'f_name', 't' => 'text', 'l' => 'برچسب نام', 'w' => 'half' ),
							array( 'k' => 'f_tel', 't' => 'text', 'l' => 'برچسب شماره تماس', 'w' => 'half' ),
							array( 'k' => 'f_co', 't' => 'text', 'l' => 'برچسب نام سازمان', 'w' => 'half' ),
						),
					),
					array(
						'title'  => 'بعد از ثبت فرم',
						'icon'   => 'check',
						'fields' => array(
							array( 'k' => 'done_title', 't' => 'text', 'l' => 'عنوان پیام موفقیت', 'w' => 'half' ),
							array( 'k' => 'results', 't' => 'number', 'l' => 'تعداد پک پیشنهادی', 'min' => 1, 'max' => 6, 'w' => 'half' ),
							array( 'k' => 'done_text', 't' => 'textarea', 'l' => 'متن پیام موفقیت', 'w' => 'full' ),
							array( 'k' => 'edit', 't' => 'text', 'l' => 'لینک ویرایش پاسخ‌ها', 'w' => 'third' ),
							array( 'k' => 'pk_order', 't' => 'text', 'l' => 'دکمه‌ی سفارش پک', 'w' => 'third' ),
							array( 'k' => 'pk_swap', 't' => 'text', 'l' => 'دکمه‌ی تغییر پک', 'w' => 'third' ),
							array( 'k' => 'pk_more', 't' => 'text', 'l' => 'دکمه‌ی جزئیات پک', 'w' => 'third' ),
							array( 'k' => 'note', 't' => 'text', 'l' => 'برچسب زرد بالای پک‌ها (اختیاری)', 'w' => 'full' ),
						),
					),
					array(
						'title'  => 'نوار چسبان پایین صفحه (موبایل)',
						'icon'   => 'mobile',
						'desc'   => 'وقتی کاربر از هیرو رد شد، در موبایل پایین صفحه می‌چسبد.',
						'fields' => array(
							array( 'k' => 'bar_t', 't' => 'text', 'l' => 'عنوان (قبل از ثبت)', 'w' => 'third' ),
							array( 'k' => 'bar_s', 't' => 'text', 'l' => 'توضیح (قبل از ثبت)', 'w' => 'third' ),
							array( 'k' => 'bar_cta', 't' => 'text', 'l' => 'دکمه (قبل از ثبت)', 'w' => 'third' ),
							array( 'k' => 'bar_t2', 't' => 'text', 'l' => 'عنوان (بعد از ثبت)', 'w' => 'third' ),
							array( 'k' => 'bar_s2', 't' => 'text', 'l' => 'توضیح (بعد از ثبت)', 'w' => 'third' ),
							array( 'k' => 'bar_cta2', 't' => 'text', 'l' => 'دکمه (بعد از ثبت)', 'w' => 'third' ),
						),
					),
				),
			),
			array(
				'id'    => 'catalog',
				'sec'   => 'catalog',
				'group' => 'فروش',
				'title' => 'کاتالوگ پک‌ها',
				'icon'  => 'box',
				'desc'  => 'پک‌هایی که فرم پک‌ساز از بینشان پیشنهاد می‌دهد و در «تغییر پک» نمایش داده می‌شوند. پیشنهاد بر اساس بودجه (قیمت) و برچسب حال‌وهوا است.',
				'cards' => array(
					array(
						'title'  => 'پک‌ها',
						'icon'   => 'box',
						'bulk'   => true,
						'fields' => array(
							array(
								'k'      => 'items',
								't'      => 'repeater',
								'l'      => '',
								'add'    => 'افزودن پک',
								'title'  => 'name',
								'w'      => 'full',
								'fields' => self::product_fields( array(
									array( 'k' => 'tags', 't' => 'tags', 'l' => 'برچسب حال‌وهوا', 'o' => $moods, 'h' => 'همان گزینه‌های سؤال «حال‌وهوا» در تب فرم پک‌ساز. پک‌هایی که برچسب مشترک بیشتری با انتخاب کاربر دارند جلوتر می‌آیند.', 'w' => 'full' ),
								) ),
							),
						),
					),
				),
			),
			array(
				'id'    => 'pricing',
				'sec'   => 'pricing',
				'group' => 'فروش',
				'title' => 'قیمت و پلکان تخفیف',
				'icon'  => 'calc',
				'desc'  => 'پنجره‌ی «پک شما آماده است»: تعداد نفرات، تخفیف تعداد، پرداخت آنلاین، پیش‌فاکتور و درخواست تماس.',
				'cards' => array(
					array(
						'title'  => 'تعداد نفرات',
						'icon'   => 'users',
						'fields' => array(
							array( 'k' => 'people', 't' => 'number', 'l' => 'تعداد پیش‌فرض', 'min' => 1, 'w' => 'third' ),
							array( 'k' => 'min', 't' => 'number', 'l' => 'حداقل تعداد سفارش', 'min' => 1, 'w' => 'third' ),
							array( 'k' => 'max', 't' => 'number', 'l' => 'حداکثر تعداد', 'min' => 1, 'w' => 'third' ),
							array( 'k' => 'step', 't' => 'number', 'l' => 'گام دکمه‌های + و −', 'min' => 1, 'w' => 'half' ),
							array( 'k' => 'presets', 't' => 'text', 'l' => 'دکمه‌های تعداد آماده', 'h' => 'با کاما جدا کنید؛ مثلاً 20,50,100,250', 'w' => 'half' ),
						),
					),
					array(
						'title'  => 'پلکان تخفیف تعداد',
						'icon'   => 'stairs',
						'desc'   => 'از این تعداد به بالا، این درصد تخفیف.',
						'fields' => array(
							array(
								'k'      => 'tiers',
								't'      => 'repeater',
								'l'      => '',
								'add'    => 'افزودن پله',
								'title'  => 'q',
								'inline' => true,
								'w'      => 'full',
								'fields' => array(
									array( 'k' => 'q', 't' => 'number', 'l' => 'از تعداد', 'min' => 1, 'w' => 'half' ),
									array( 'k' => 'pct', 't' => 'number', 'l' => 'درصد تخفیف', 'min' => 0, 'max' => 90, 'step' => 0.5, 'w' => 'half' ),
								),
							),
							array( 'k' => 'cart_tiers', 't' => 'toggle', 'l' => 'اعمال همین پلکان در سبد خرید ووکامرس', 'h' => 'فقط برای سفارش‌هایی که با دکمه‌ی «ادامه و پرداخت آنلاین» همین لندینگ به سبد اضافه می‌شوند.', 'w' => 'full' ),
							array( 'k' => 'round', 't' => 'number', 'l' => 'گرد کردن قیمت تخفیف‌خورده‌ی یلدآف (تومان)', 'min' => 0, 'sec' => 'general', 'w' => 'half' ),
						),
					),
					array(
						'title'  => 'دکمه‌ها',
						'icon'   => 'cursor',
						'fields' => array(
							array( 'k' => 'title', 't' => 'text', 'l' => 'عنوان پنجره', 'w' => 'half' ),
							array( 'k' => 'q_label', 't' => 'text', 'l' => 'برچسب تعداد', 'w' => 'half' ),
							array( 'k' => 'pay', 't' => 'text', 'l' => 'دکمه‌ی پرداخت آنلاین', 'w' => 'third' ),
							array( 'k' => 'invoice', 't' => 'text', 'l' => 'دکمه‌ی پیش‌فاکتور', 'w' => 'third' ),
							array( 'k' => 'call', 't' => 'text', 'l' => 'دکمه‌ی تماس کارشناس', 'w' => 'third' ),
							array( 'k' => 'pay_url', 't' => 'url', 'l' => 'لینک پرداخت برای پک‌های دستی (اختیاری)', 'h' => 'پک‌های ووکامرس مستقیم با تعداد انتخابی به سبد خرید و صفحه‌ی پرداخت می‌روند. برای پک‌های دستی اگر خالی باشد صفحه‌ی خود پک باز می‌شود.', 'w' => 'full', 'ph' => 'https://' ),
							array( 'k' => 'note', 't' => 'text', 'l' => 'برچسب زرد داخل پنجره (اختیاری)', 'w' => 'full' ),
						),
					),
					array(
						'title'  => 'فرم پیش‌فاکتور و درخواست تماس',
						'icon'   => 'doc',
						'fields' => array(
							array( 'k' => 'inv_title', 't' => 'text', 'l' => 'عنوان فرم پیش‌فاکتور', 'w' => 'half' ),
							array( 'k' => 'inv_cta', 't' => 'text', 'l' => 'دکمه‌ی ارسال پیش‌فاکتور', 'w' => 'half' ),
							array( 'k' => 'inv_done', 't' => 'text', 'l' => 'پیام بعد از درخواست پیش‌فاکتور', 'w' => 'full' ),
							array( 'k' => 'call_title', 't' => 'text', 'l' => 'عنوان فرم تماس', 'w' => 'half' ),
							array( 'k' => 'call_cta', 't' => 'text', 'l' => 'دکمه‌ی ثبت تماس', 'w' => 'half' ),
							array( 'k' => 'call_lead', 't' => 'text', 'l' => 'متن فرم تماس', 'w' => 'full' ),
							array( 'k' => 'call_done', 't' => 'text', 'l' => 'پیام بعد از درخواست تماس', 'w' => 'full' ),
							array( 'k' => 'done_title', 't' => 'text', 'l' => 'عنوان پیام موفقیت', 'w' => 'full' ),
						),
					),
					array(
						'title'  => 'پنجره‌ی «تغییر پک»',
						'icon'   => 'swap',
						'fields' => array(
							array( 'k' => 'swap_title', 't' => 'text', 'l' => 'عنوان', 'w' => 'half' ),
							array( 'k' => 'swap_sub', 't' => 'text', 'l' => 'توضیح', 'w' => 'half' ),
						),
					),
				),
			),
			array(
				'id'     => 'recent',
				'sec'    => 'recent',
				'group'  => 'بخش‌های صفحه',
				'title'  => 'آخرین پک‌ها',
				'icon'   => 'history',
				'toggle' => true,
				'desc'   => 'نمونه سفارش‌های اخیر سازمان‌ها؛ دکمه‌ی هر کارت پنجره‌ی قیمت را با همان تعداد نفرات باز می‌کند.',
				'cards'  => array(
					self::title_card( true, true, true ),
					array(
						'title'  => 'کارت‌ها',
						'icon'   => 'box',
						'fields' => array(
							array(
								'k'      => 'items',
								't'      => 'repeater',
								'l'      => '',
								'add'    => 'افزودن کارت',
								'title'  => 'name',
								'w'      => 'full',
								'fields' => self::product_fields( array(
									array( 'k' => 'who', 't' => 'text', 'l' => 'نوع سازمان', 'ph' => 'شرکت فناوری', 'w' => 'third' ),
									array( 'k' => 'people', 't' => 'number', 'l' => 'تعداد نفرات', 'min' => 1, 'w' => 'third' ),
									array( 'k' => 'cta', 't' => 'text', 'l' => 'متن دکمه', 'w' => 'third' ),
								) ),
							),
						),
					),
				),
			),
			array(
				'id'     => 'proof',
				'sec'    => 'proof',
				'group'  => 'بخش‌های صفحه',
				'title'  => 'نظر شرکت‌ها',
				'icon'   => 'quote',
				'toggle' => true,
				'desc'   => 'لوگوی مشتریان سازمانی و نقل‌قول‌هایشان.',
				'cards'  => array(
					self::title_card( true, true, false ),
					array(
						'title'  => 'لوگوی مشتریان',
						'icon'   => 'image',
						'fields' => array(
							array(
								'k'      => 'logos',
								't'      => 'repeater',
								'l'      => '',
								'add'    => 'افزودن لوگو',
								'title'  => 'name',
								'w'      => 'full',
								'fields' => array(
									array( 'k' => 'name', 't' => 'text', 'l' => 'نام شرکت', 'h' => 'اگر لوگو انتخاب نشود همین نام نمایش داده می‌شود.', 'w' => 'full' ),
									array( 'k' => 'img', 't' => 'image', 'l' => 'لوگو', 'w' => 'full' ),
								),
							),
							array( 'k' => 'logo_speed', 't' => 'seg', 'l' => 'سرعت نوار لوگوها (به سمت چپ)', 'o' => array( 'slow' => 'خیلی آرام', 'normal' => 'آرام', 'fast' => 'معمولی' ), 'def' => 'normal', 'h' => 'با بردن موس روی نوار، حرکت متوقف می‌شود.', 'w' => 'full' ),
						),
					),
					array(
						'title'  => 'نظرات',
						'icon'   => 'quote',
						'fields' => array(
							array(
								'k'      => 'quotes',
								't'      => 'repeater',
								'l'      => '',
								'add'    => 'افزودن نظر',
								'title'  => 'by',
								'w'      => 'full',
								'fields' => array(
									array( 'k' => 'text', 't' => 'textarea', 'l' => 'متن نظر', 'w' => 'full' ),
									array( 'k' => 'by', 't' => 'text', 'l' => 'نام، سمت و شرکت', 'ph' => 'مریم احمدی، مدیر منابع انسانی، شرکت …', 'w' => 'full' ),
								),
							),
							array( 'k' => 'quote_speed', 't' => 'seg', 'l' => 'سرعت نوار نظرها (به سمت راست)', 'o' => array( 'slow' => 'خیلی آرام', 'normal' => 'آرام', 'fast' => 'معمولی' ), 'def' => 'slow', 'h' => 'نام پیش از اولین «،» پررنگ و بقیه (سمت و شرکت) زیرش نمایش داده می‌شود. با بردن موس روی نوار، حرکت متوقف می‌شود.', 'w' => 'full' ),
						),
					),
				),
			),
			array(
				'id'     => 'fal',
				'sec'    => 'fal',
				'group'  => 'بخش‌های صفحه',
				'title'  => 'فال حافظ',
				'icon'   => 'book',
				'toggle' => true,
				'desc'   => 'قناری کاموایی پاکت فال را می‌آورد و یک فال تصادفی از فهرست زیر نمایش داده می‌شود.',
				'cards'  => array(
					self::title_card( true, true, false ),
					array(
						'title'  => 'دکمه‌ها',
						'icon'   => 'cursor',
						'fields' => array(
							array( 'k' => 'cta', 't' => 'text', 'l' => 'دکمه‌ی گرفتن فال', 'w' => 'half' ),
							array( 'k' => 'busy', 't' => 'text', 'l' => 'متن دکمه در حین انیمیشن', 'w' => 'half' ),
							array( 'k' => 'go', 't' => 'text', 'l' => 'دکمه‌ی بعد از فال', 'w' => 'half' ),
							array( 'k' => 'again', 't' => 'text', 'l' => 'دکمه‌ی فال دوباره', 'w' => 'half' ),
						),
					),
					array(
						'title'  => 'فال‌ها',
						'icon'   => 'book',
						'fields' => array(
							array(
								'k'      => 'items',
								't'      => 'repeater',
								'l'      => '',
								'add'    => 'افزودن فال',
								'title'  => 'b1',
								'w'      => 'full',
								'fields' => array(
									array( 'k' => 'b1', 't' => 'text', 'l' => 'مصراع اول', 'w' => 'half' ),
									array( 'k' => 'b2', 't' => 'text', 'l' => 'مصراع دوم', 'w' => 'half' ),
									array( 'k' => 't', 't' => 'textarea', 'l' => 'تفسیر', 'w' => 'full' ),
								),
							),
						),
					),
				),
			),
			array(
				'id'     => 'seo',
				'sec'    => 'seo',
				'group'  => 'بخش‌های صفحه',
				'title'  => 'متن سئو',
				'icon'   => 'doc',
				'toggle' => true,
				'desc'   => 'متن توضیحی برای گوگل؛ ابتدا خلاصه دیده می‌شود و با «بیشتر بخوانید» باز می‌شود.',
				'cards'  => array(
					array(
						'title'  => 'متن',
						'icon'   => 'text',
						'fields' => array(
							array( 'k' => 'title', 't' => 'text', 'l' => 'عنوان', 'w' => 'full' ),
							array( 'k' => 'content', 't' => 'editor', 'l' => 'متن', 'h' => 'برای زیرعنوان‌ها از «سربرگ ۳» استفاده کنید تا طلایی نمایش داده شوند.', 'w' => 'full' ),
							array( 'k' => 'collapse', 't' => 'toggle', 'l' => 'جمع‌شده با دکمه‌ی «بیشتر بخوانید»', 'w' => 'full' ),
							array( 'k' => 'more', 't' => 'text', 'l' => 'متن دکمه‌ی باز کردن', 'w' => 'half' ),
							array( 'k' => 'less', 't' => 'text', 'l' => 'متن دکمه‌ی بستن', 'w' => 'half' ),
						),
					),
				),
			),
			array(
				'id'     => 'faq',
				'sec'    => 'faq',
				'group'  => 'بخش‌های صفحه',
				'title'  => 'سوالات متداول',
				'icon'   => 'help',
				'toggle' => true,
				'desc'   => 'پرسش و پاسخ‌ها به‌صورت آکاردئونی.',
				'cards'  => array(
					self::title_card( true, false, false ),
					array(
						'title'  => 'سوال‌ها',
						'icon'   => 'help',
						'fields' => array(
							array( 'k' => 'first_open', 't' => 'toggle', 'l' => 'اولین سوال باز باشد', 'w' => 'full' ),
							array(
								'k'      => 'items',
								't'      => 'repeater',
								'l'      => '',
								'add'    => 'افزودن سوال',
								'title'  => 'q',
								'w'      => 'full',
								'fields' => array(
									array( 'k' => 'q', 't' => 'text', 'l' => 'سوال', 'w' => 'full' ),
									array( 'k' => 'a', 't' => 'textarea', 'l' => 'پاسخ', 'h' => 'لینک و متن پررنگ (تگ a و b) مجاز است.', 'w' => 'full' ),
								),
							),
						),
					),
				),
			),
			array(
				'id'    => 'end',
				'sec'   => 'end',
				'group' => 'بخش‌های صفحه',
				'title' => 'پایان صفحه و فوتر',
				'icon'  => 'flag',
				'desc'  => 'دعوت پایانی با شمارش روزها و نوار پایین صفحه.',
				'cards' => array(
					array(
						'title'  => 'دعوت پایانی',
						'icon'   => 'flag',
						'fields' => array(
							array( 'k' => 'on', 't' => 'toggle', 'l' => 'نمایش بخش پایانی', 'w' => 'full' ),
							array( 'k' => 'title', 't' => 'text', 'l' => 'تیتر', 'h' => '{days} با تعداد روزهای باقی‌مانده جایگزین می‌شود.', 'w' => 'half' ),
							array( 'k' => 'over', 't' => 'text', 'l' => 'تیتر بعد از رسیدن یلدا', 'w' => 'half' ),
							array( 'k' => 'text', 't' => 'text', 'l' => 'متن', 'w' => 'half' ),
							array( 'k' => 'deadline', 't' => 'text', 'l' => 'مهلت ثبت سفارش (پررنگ)', 'ph' => 'مثلاً ۲۵ آذر', 'w' => 'half' ),
							array( 'k' => 'cta', 't' => 'text', 'l' => 'متن دکمه', 'w' => 'half' ),
						),
					),
					array(
						'title'  => 'فوتر',
						'icon'   => 'footer',
						'sec'    => 'footer',
						'fields' => array(
							array( 'k' => 'text', 't' => 'text', 'l' => 'متن فوتر', 'w' => 'full' ),
							array( 'k' => 'seal_img', 't' => 'image', 'l' => 'تصویر نماد اعتماد', 'w' => 'half', 'def' => true ),
							array( 'k' => 'seal_url', 't' => 'url', 'l' => 'لینک نماد اعتماد', 'w' => 'half', 'ph' => 'https://trustseal.enamad.ir/…' ),
							array( 'k' => 'seal_code', 't' => 'code', 'l' => 'یا کد کامل نماد اعتماد (اختیاری)', 'h' => 'اگر کد نماد را از سایت اینماد کپی کنید، به‌جای تصویر بالا همان نمایش داده می‌شود.', 'w' => 'full' ),
							array(
								'k'      => 'links',
								't'      => 'repeater',
								'l'      => 'لینک‌های فوتر',
								'add'    => 'افزودن لینک',
								'title'  => 'label',
								'inline' => true,
								'w'      => 'full',
								'fields' => array(
									array( 'k' => 'label', 't' => 'text', 'l' => 'متن', 'w' => 'half' ),
									array( 'k' => 'url', 't' => 'url', 'l' => 'آدرس', 'w' => 'half', 'ph' => 'https://' ),
								),
							),
						),
					),
				),
			),
			array(
				'id'    => 'leads',
				'sec'   => 'leads',
				'group' => 'فروش',
				'title' => 'درخواست‌ها و اعلان',
				'icon'  => 'inbox',
				'desc'  => 'همه‌ی فرم‌های لندینگ (پک‌ساز، پیش‌فاکتور و درخواست تماس) در «درخواست‌ها» ذخیره می‌شوند.',
				'cards' => array(
					array(
						'title'  => 'درخواست‌ها',
						'icon'   => 'inbox',
						'leads'  => true,
						'fields' => array(),
					),
					array(
						'title'  => 'اعلان درخواست جدید',
						'icon'   => 'bell',
						'fields' => array(
							array( 'k' => 'notify', 't' => 'toggle', 'l' => 'ارسال ایمیل برای هر درخواست جدید', 'w' => 'full' ),
							array( 'k' => 'email', 't' => 'email', 'l' => 'ایمیل دریافت‌کننده', 'h' => 'خالی = ایمیل مدیر سایت', 'w' => 'half' ),
							array( 'k' => 'webhook', 't' => 'url', 'l' => 'وب‌هوک (اختیاری، برای CRM یا ربات)', 'h' => 'اطلاعات هر درخواست به‌صورت JSON به این آدرس POST می‌شود.', 'w' => 'half', 'ph' => 'https://' ),
						),
					),
				),
			),
		);
	}

	/** Mood options from the saved builder steps (for catalog tags). */
	public static function mood_options() {
		$out = array();
		foreach ( (array) YL_Settings::get( 'build', 'steps', array() ) as $st ) {
			if ( isset( $st['type'] ) && 'mood' === $st['type'] ) {
				foreach ( (array) $st['opts'] as $o ) {
					if ( ! empty( $o['k'] ) ) {
						$out[ $o['k'] ] = $o['label'];
					}
				}
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ sanitize */

	/** Sanitize the posted form ($_POST['yl']) into a full settings array. */
	public static function sanitize( $in ) {
		$in  = is_array( $in ) ? $in : array();
		$out = YL_Settings::all();
		foreach ( self::tabs() as $tab ) {
			foreach ( $tab['cards'] as $card ) {
				$csec = isset( $card['sec'] ) ? $card['sec'] : $tab['sec'];
				foreach ( $card['fields'] as $f ) {
					$sec = isset( $f['sec'] ) ? $f['sec'] : $csec;
					if ( empty( $f['k'] ) || 'note' === $f['t'] ) {
						continue;
					}
					$raw = isset( $in[ $sec ] ) && is_array( $in[ $sec ] ) && array_key_exists( $f['k'], $in[ $sec ] ) ? $in[ $sec ][ $f['k'] ] : null;
					$out[ $sec ][ $f['k'] ] = self::clean( $f, $raw, isset( $out[ $sec ][ $f['k'] ] ) ? $out[ $sec ][ $f['k'] ] : null );
				}
			}
		}
		// Section on/off switches live in the order list.
		foreach ( array_keys( self::sections() ) as $sec ) {
			$out[ $sec ]['on'] = ! empty( $in[ $sec ]['on'] ) ? 1 : 0;
		}
		return $out;
	}

	private static function clean( $f, $v, $old ) {
		switch ( $f['t'] ) {
			case 'text':
			case 'tel':
				return null === $v ? '' : sanitize_text_field( wp_unslash( $v ) );
			case 'key':
				$k = null === $v ? '' : sanitize_key( wp_unslash( $v ) );
				return '' === $k ? 'k' . strtolower( wp_generate_password( 6, false ) ) : $k;
			case 'email':
				return null === $v ? '' : sanitize_email( wp_unslash( $v ) );
			case 'textarea':
				return null === $v ? '' : sanitize_textarea_field( wp_unslash( $v ) );
			case 'code':
				return null === $v ? '' : wp_kses( wp_unslash( $v ), array(
					'a'   => array( 'href' => array(), 'target' => array(), 'rel' => array(), 'referrerpolicy' => array(), 'id' => array() ),
					'img' => array( 'src' => array(), 'alt' => array(), 'width' => array(), 'height' => array(), 'style' => array(), 'referrerpolicy' => array(), 'code' => array(), 'id' => array(), 'loading' => array() ),
				) );
			case 'editor':
				return null === $v ? '' : wp_kses_post( wp_unslash( $v ) );
			case 'url':
				return null === $v ? '' : esc_url_raw( trim( wp_unslash( $v ) ) );
			case 'number':
			case 'money':
				$n = (float) str_replace( array( ',', '٬', '،', ' ' ), '', yl_en( (string) $v ) );
				if ( isset( $f['min'] ) ) {
					$n = max( (float) $f['min'], $n );
				}
				if ( isset( $f['max'] ) ) {
					$n = min( (float) $f['max'], $n );
				}
				return ( floor( $n ) == $n ) ? (int) $n : $n; // phpcs:ignore Universal.Operators.StrictComparisons
			case 'toggle':
				return ! empty( $v ) ? 1 : 0;
			case 'select':
			case 'seg':
				$v = (string) $v;
				if ( isset( $f['o'][ $v ] ) ) {
					return $v;
				}
				return isset( $f['def'] ) ? $f['def'] : (string) key( $f['o'] );
			case 'image':
				$v = is_string( $v ) ? trim( wp_unslash( $v ) ) : $v;
				if ( is_string( $v ) && 0 === strpos( $v, 'b:' ) ) {
					return YL_Images::bundled_exists( substr( $v, 2 ) ) ? $v : '';
				}
				return absint( $v ) ? absint( $v ) : '';
			case 'color':
				$c = sanitize_hex_color( (string) $v );
				return $c ? $c : ( $old ? $old : '#000000' );
			case 'product':
			case 'page':
			case 'pcat':
				return absint( $v );
			case 'jalali':
				$v = is_array( $v ) ? $v : array();
				$t = isset( $v['t'] ) && preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', (string) $v['t'] ) ? $v['t'] : '17:00';
				return array(
					'y' => min( 1500, max( 1390, (int) yl_en( isset( $v['y'] ) ? $v['y'] : 1405 ) ) ),
					'm' => min( 12, max( 1, (int) ( isset( $v['m'] ) ? $v['m'] : 9 ) ) ),
					'd' => min( 31, max( 1, (int) yl_en( isset( $v['d'] ) ? $v['d'] : 30 ) ) ),
					't' => $t,
				);
			case 'order':
				$all  = array_keys( self::sections() );
				$list = array_values( array_intersect( array_map( 'sanitize_key', (array) $v ), $all ) );
				foreach ( $all as $k ) {
					if ( ! in_array( $k, $list, true ) ) {
						$list[] = $k;
					}
				}
				return $list;
			case 'tags':
				return array_values( array_filter( array_map( 'sanitize_key', array_map( 'wp_unslash', (array) $v ) ) ) );
			case 'repeater':
				$rows = array();
				foreach ( (array) $v as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$r = array();
					foreach ( $f['fields'] as $sf ) {
						$r[ $sf['k'] ] = self::clean( $sf, array_key_exists( $sf['k'], $row ) ? $row[ $sf['k'] ] : null, null );
					}
					$rows[] = $r;
				}
				return $rows;
		}
		return $old;
	}
}
