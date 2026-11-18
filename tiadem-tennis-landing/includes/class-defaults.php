<?php
/**
 * Default content — every text and image from the approved design.
 *
 * @package TiademTennisLanding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default settings.
 */
class TDL_Defaults {

	/**
	 * Sections that can be reordered (header, announcement and footer are fixed).
	 *
	 * @return string[]
	 */
	public static function sortable_sections() {
		return array( 'hero', 'trust', 'cats', 'festival', 'economy', 'master', 'grid', 'reviews', 'what', 'types', 'guide', 'care', 'faq', 'related' );
	}

	/**
	 * Full default settings array.
	 *
	 * @return array
	 */
	public static function get() {
		return array(
			'order'    => self::sortable_sections(),

			'general'  => array(
				'logo'             => 'asset:logo.png',
				'logo_alt'         => 'تیادم',
				'color_ink'        => '#142033',
				'color_accent'     => '#2F5F8A',
				'color_btn'        => '#172A44',
				'currency'         => '',
				'phone'            => '',
				'phone_label'      => 'مشاوره و سفارش تلفنی',
				'social_label'     => 'تیادم را دنبال کنید',
				'instagram'        => '',
				'telegram'         => '',
				'bale'             => '',
				'meta_description' => 'خرید بدلیجات تنیسی تیادم؛ دستبند، گردنبند، پابند، انگشتر و گوشواره تنیسی با آبکاری رادیوم درجه ۱ در دو کیفیت اکونومی و مستر کوالیتی، با قفل کتابی و کوبایی.',
			),

			'woo'      => array(
				'scope_cats'        => array(),
				'eco_cat'           => 0,
				'master_cat'        => 0,
				'eco_label'         => 'اکونومی',
				'master_label'      => 'مستر کوالیتی',
				'clasp_mode'        => 'category',
				'clasp_book_cat'    => 0,
				'clasp_cuban_cat'   => 0,
				'clasp_book_label'  => 'قفل کتابی',
				'clasp_cuban_label' => 'قفل کوبایی',
				'clasp_attr'        => '',
				'clasp_prefix'      => 'قفل ',
				'low_stock'         => 10,
				'hide_out_of_stock' => 1,
				'variable_mode'     => 'popup',
			),

			'announce' => array(
				'enabled'     => 1,
				'text'        => 'قفل کوبایی روی محصولات اکونومی؛ در ایران فقط در تیادم با این قیمت',
				'text_mobile' => 'قفل کوبایی روی اکونومی، فقط در تیادم',
				'link'        => '',
			),

			'header'   => array(
				'nav'                => array(
					array( 't' => 'جشنواره', 'href' => '#festival' ),
					array( 't' => 'اکونومی', 'href' => '#economy' ),
					array( 't' => 'مستر کوالیتی', 'href' => '#master' ),
					array( 't' => 'راهنمای خرید', 'href' => '#guide' ),
					array( 't' => 'سؤالات', 'href' => '#faq' ),
				),
				'show_search'        => 1,
				'cart_label'         => 'سبد',
				'search_placeholder' => 'دستبند تنیسی گرد',
				'drawer_cats_title'  => 'دسته‌های تنیسی',
				'drawer_links'       => array(
					array( 't' => 'جشنواره درخشش', 'href' => '#festival', 'badge' => 'تا ۱۶٪', 'icon' => 'sparkle' ),
					array( 't' => 'تنیسی اکونومی', 'href' => '#economy', 'badge' => '', 'icon' => 'diamond' ),
					array( 't' => 'تنیسی مستر کوالیتی', 'href' => '#master', 'badge' => '', 'icon' => 'star' ),
					array( 't' => 'قفل کتابی یا کوبایی؟', 'href' => '#clasps', 'badge' => '', 'icon' => 'lock' ),
					array( 't' => 'راهنمای خرید', 'href' => '#guide', 'badge' => '', 'icon' => 'book' ),
					array( 't' => 'سؤالات پرتکرار', 'href' => '#faq', 'badge' => '', 'icon' => 'question' ),
				),
			),

			'hero'     => array(
				'enabled'     => 1,
				'title'       => 'خرید بدلیجات تنیسی',
				'lead'        => 'یک ردیف نگین پیوسته که با هر حرکت، نور را برمی‌گرداند. دستبند، گردنبند، پابند، انگشتر و گوشواره تنیسی تیادم با آبکاری رادیوم درجه ۱، در دو کیفیت اکونومی و مستر کوالیتی؛ با قفل کتابی در پایین‌ترین قیمت بازار، یا قفل کوبایی که در ایران فقط تیادم با این قیمت عرضه می‌کند.',
				'lead_mobile' => 'یک ردیف نگین پیوسته که با هر حرکت می‌درخشد؛ با آبکاری رادیوم درجه ۱، در دو کیفیت اکونومی و مستر کوالیتی.',
				'cta_text'    => 'دیدن مدل‌ها و قیمت‌ها',
				'cta_link'    => '#grid',
				'image'       => 'asset:hero-anim.webp',
				'poster'      => 'asset:hero-poster.webp',
				'image_alt'   => 'دستبند تنیسی طلایی و نقره‌ای تیادم روی ویترین',
				'pedestal'    => 1,
			),

			'trust'    => array(
				'enabled' => 1,
				'items'   => array(
					array( 't' => 'آبکاری رادیوم درجه ۱', 's' => 'براق، روشن و یکدست', 'icon' => 'diamond' ),
					array( 't' => 'دو کیفیت، دو بودجه', 's' => 'اکونومی و مستر کوالیتی', 'icon' => 'layers' ),
					array( 't' => 'قفل کتابی و کوبایی', 's' => 'کوبایی روی اکونومی فقط در تیادم', 'icon' => 'lock' ),
					array( 't' => 'مشاوره رایگان در دایرکت', 's' => 'قبل از خرید سؤال کن', 'icon' => 'chat' ),
				),
			),

			'cats'     => array(
				'enabled'  => 1,
				'title'    => 'دنبال کدام مدل تنیسی هستی؟',
				'subtitle' => 'روی هر دسته بزن تا مدل‌هایش را ببینی',
				'items'    => array(
					array( 'name' => 'دستبند', 'image' => 'asset:bracelet-round', 'term' => 0, 'url' => '' ),
					array( 'name' => 'گردنبند', 'image' => 'asset:necklace-round', 'term' => 0, 'url' => '' ),
					array( 'name' => 'پابند', 'image' => 'asset:anklet-silver', 'term' => 0, 'url' => '' ),
					array( 'name' => 'انگشتر', 'image' => 'asset:ring-channel', 'term' => 0, 'url' => '' ),
					array( 'name' => 'گوشواره', 'image' => 'asset:earring-climber', 'term' => 0, 'url' => '' ),
					array( 'name' => 'ست', 'image' => 'asset:set-baguette', 'term' => 0, 'url' => '' ),
				),
			),

			'festival' => array(
				'enabled'         => 1,
				'label'           => 'جشنواره درخشش',
				'title'           => "تنیسی‌های\nتخفیف‌دار",
				'desc'            => 'تا {max} تخفیف روی مدل‌های منتخب؛ فقط تا پایان شمارش معکوس.',
				'end_ts'          => 0,
				'source'          => 'auto',
				'products'        => array(),
				'limit'           => 10,
				'btn_text'        => 'همه تخفیف‌ها',
				'hide_expired'    => 1,
				'show_card_timer' => 1,
			),

			'economy'  => array(
				'enabled'          => 1,
				'title'            => 'تنیسی اکونومی',
				'view_all'         => 'مشاهده همه',
				'side_en'          => 'Economy',
				'side_title'       => 'اکونومی',
				'side_desc'        => 'آبکاری رادیوم درجه ۱ با اقتصادی‌ترین قیمت. قفل کوبایی روی اکونومی، فقط در تیادم.',
				'side_desc_mobile' => 'رادیوم درجه ۱ با اقتصادی‌ترین قیمت؛ قفل کوبایی فقط در تیادم.',
				'side_image'       => 'asset:economy-side.webp',
				'side_alt'         => 'دستبند تنیسی طلایی و نقره‌ای اکونومی',
				'source'           => 'auto',
				'products'         => array(),
				'limit'            => 10,
				'orderby'          => 'popularity',
			),

			'master'   => array(
				'enabled'          => 1,
				'title'            => 'تنیسی مستر کوالیتی',
				'view_all'         => 'مشاهده همه',
				'side_en'          => 'Master quality',
				'side_title'       => 'مستر کوالیتی',
				'side_desc'        => 'درخشش بیشتر برای هدیه‌های خاص؛ با قفل کتابی یا کوبایی.',
				'side_desc_mobile' => 'درخشش بیشتر برای هدیه‌های خاص.',
				'side_image'       => 'asset:master-side.webp',
				'side_alt'         => 'دستبند تنیسی مستر کوالیتی طلایی و نقره‌ای',
				'source'           => 'auto',
				'products'         => array(),
				'limit'            => 10,
				'orderby'          => 'popularity',
			),

			'grid'     => array(
				'enabled'    => 1,
				'title'      => 'همه محصولات تنیسی',
				'per_page'   => 12,
				'orderby'    => 'menu_order',
				'more_text'  => 'نمایش محصولات بیشتر',
				'empty_text' => 'محصولی در این دسته پیدا نشد.',
				'chips'      => array(
					array( 'label' => 'همه', 'filter' => 'all' ),
					array( 'label' => 'دستبند', 'filter' => '' ),
					array( 'label' => 'گردنبند', 'filter' => '' ),
					array( 'label' => 'پابند', 'filter' => '' ),
					array( 'label' => 'انگشتر', 'filter' => '' ),
					array( 'label' => 'گوشواره', 'filter' => '' ),
					array( 'label' => 'ست', 'filter' => '' ),
					array( 'label' => 'اکونومی', 'filter' => 'eco' ),
					array( 'label' => 'مستر کوالیتی', 'filter' => 'master' ),
				),
			),

			'reviews'  => array(
				'enabled'      => 1,
				'title'        => 'نظر خریداران',
				'source'       => 'auto',
				'min_rating'   => 4,
				'limit'        => 6,
				'avg_mode'     => 'auto',
				'avg_manual'   => '4.8',
				'count_manual' => 0,
				'count_text'   => 'بر اساس {count} نظر ثبت‌شده',
				'items'        => array(
					array( 'text' => 'برقش دقیقاً مثل عکس‌هاست و قفل کوبایی‌اش خیلی محکم بسته می‌شود.', 'name' => 'سارا، تهران', 'product' => 'دستبند تنیسی گرد، مستر' ),
					array( 'text' => 'پابند را با گردنبند ست کردم؛ هر کس دید پرسید از کجا خریدم.', 'name' => 'نگار، شیراز', 'product' => 'پابند و گردنبند تنیسی' ),
					array( 'text' => 'قفل کوبایی را جای دیگر با این قیمت پیدا نکردم. بسته‌بندی هم شیک بود.', 'name' => 'مهسا، مشهد', 'product' => 'گردنبند باگت، اکونومی' ),
					array( 'text' => 'سه تا خریدم و با هم دست می‌کنم؛ سبک است و اذیت نمی‌کند.', 'name' => 'ریحانه، اصفهان', 'product' => 'دستبند ظریف، اکونومی' ),
				),
			),

			'what'     => array(
				'enabled' => 1,
				'en'      => 'What is tennis jewelry?',
				'title'   => 'بدلیجات تنیسی چیست؟',
				'body'    => "«تنیسی» به جواهری گفته می‌شود که در آن یک ردیف نگین هم‌اندازه، بدون فاصله و پشت سر هم روی یک زنجیر انعطاف‌پذیر نشسته‌اند؛ طوری که کل قطعه یک خط درخشان و یکدست به نظر برسد. قبل از این نام، به این مدل «دستبند ردیفی» یا «اترنیتی» می‌گفتند.\n\nنام «تنیس» از ماجرایی در اواخر دهه ۷۰ میلادی می‌آید: کریس اورت، قهرمان تنیس، وسط مسابقه‌ای در مسابقات آزاد آمریکا دستبند الماسش را گم کرد و بازی را تا پیدا شدن آن متوقف کرد. از آن روز این مدل با ورزش تنیس گره خورد و امروز یکی از پرطرفدارترین مدل‌های دستبند، گردنبند و پابند است.",
				'image'   => 'asset:bracelets-wrist',
				'alt'     => 'سه دستبند تنیسی نگین گرد روی مچ',
			),

			'types'    => array(
				'enabled'      => 1,
				'title'        => 'انواع بدلیجات تنیسی',
				'desc'         => 'هر قطعه را می‌شود تکی پوشید یا با بقیه ست کرد. روی هر مدل بزن تا به صفحه همان دسته بروی.',
				'link_text'    => 'مشاهده مدل‌ها ←',
				'items'        => array(
					array( 'name' => 'دستبند تنیسی', 'desc' => 'پرفروش‌ترین مدل؛ ظریف برای چندتایی، پهن برای تک‌پوشی.', 'image' => 'asset:bracelet-round', 'term' => 0, 'url' => '' ),
					array( 'name' => 'گردنبند تنیسی', 'desc' => 'یک خط نور دور گردن؛ با یقه باز و لباس مجلسی.', 'image' => 'asset:necklace-round', 'term' => 0, 'url' => '' ),
					array( 'name' => 'پابند تنیسی', 'desc' => 'سبک و ظریف برای روزهای گرم و کفش باز.', 'image' => 'asset:anklet-silver', 'term' => 0, 'url' => '' ),
					array( 'name' => 'انگشتر و حلقه تنیسی', 'desc' => 'ردیف نگین دور تا دور حلقه؛ همراه دستبند کانالی.', 'image' => 'asset:ring-channel', 'term' => 0, 'url' => '' ),
					array( 'name' => 'گوشواره تنیسی', 'desc' => 'کلایمبر و حلقه‌ای؛ کامل‌کننده ست تنیسی.', 'image' => 'asset:earring-climber', 'term' => 0, 'url' => '' ),
					array( 'name' => 'ست تنیسی', 'desc' => 'دو یا چند قطعه هماهنگ برای خودت یا هدیه.', 'image' => 'asset:set-baguette', 'term' => 0, 'url' => '' ),
				),
				'shapes_title' => 'بر اساس فرم نگین:',
				'shapes'       => array(
					array( 'label' => 'نگین گرد کلاسیک', 'term' => 0, 'url' => '' ),
					array( 'label' => 'نگین باگت', 'term' => 0, 'url' => '' ),
					array( 'label' => 'نگین پرنسس کانالی', 'term' => 0, 'url' => '' ),
					array( 'label' => 'نگین قلبی رنگی', 'term' => 0, 'url' => '' ),
				),
			),

			'guide'    => array(
				'enabled'     => 1,
				'title'       => 'راهنمای خرید بدلیجات تنیسی',
				'desc'        => 'شش تصمیم کوتاه تا انتخاب تنیسی مناسب تو؛ از مدل و فرم نگین تا کیفیت و نوع قفل.',
				'steps'       => array(
					array( 't' => 'قطعه را انتخاب کن', 'd' => 'دستبند برای هر روز، گردنبند برای مهمانی، پابند برای تابستان؛ یا همه را با هم ست کن.' ),
					array( 't' => 'فرم نگین', 'd' => 'گرد کلاسیک و همیشگی است، باگت مدرن و کشیده، پرنسس کانالی لوکس و قلبی رنگی شاد و جوان.' ),
					array( 't' => 'پهنای ردیف', 'd' => 'ردیف ظریف برای چندتایی دست‌کردن، ردیف پهن‌تر برای وقتی که می‌خواهی یک قطعه دیده شود.' ),
					array( 't' => 'کیفیت', 'd' => 'اکونومی برای استفاده روزانه با قیمت اقتصادی؛ مستر کوالیتی برای درخشش بیشتر و هدیه.' ),
					array( 't' => 'نوع قفل', 'd' => 'قفل کتابی با پایین‌ترین قیمت بازار، یا قفل کوبایی محکم‌تر و لوکس‌تر.' ),
					array( 't' => 'سایز', 'd' => 'دور مچ یا مچ پا را با متر نواری بگیر و حدود ۱ تا ۲ سانتی‌متر اضافه کن. [راهنمای سایز تیادم]' ),
				),
				'clasp_title' => 'قفل کتابی یا قفل کوبایی؟',
				'book_badge'  => 'پایین‌ترین قیمت بازار',
				'book_title'  => 'قفل کتابی',
				'book_desc'   => 'قفل جعبه‌ای کلاسیک با زبانه و ضامن تاشو که مثل جلد کتاب روی قفل بسته می‌شود. ساده باز و بسته می‌شود و اقتصادی‌ترین انتخاب تنیسی‌های تیادم است.',
				'book_image'  => 'asset:necklace-round',
				'book_alt'    => 'نمای نزدیک قفل کتابی گردنبند تنیسی',
				'cuban_badge' => 'فقط در تیادم با این قیمت',
				'cuban_title' => 'قفل کوبایی',
				'cuban_desc'  => 'قفل پهن و محکمی که معمولاً روی زنجیرهای کوبایی سنگین استفاده می‌شود؛ ظاهری یکپارچه و لوکس دارد و محکم‌تر قفل می‌شود. قفل کوبایی روی محصولات اکونومی را در ایران فقط تیادم با این قیمت عرضه می‌کند.',
				'cuban_image' => 'asset:bracelet-round',
				'cuban_alt'   => 'نمای نزدیک قفل کوبایی دستبند تنیسی',
				'table_h1'    => 'ویژگی',
				'table_h2'    => 'قفل کتابی',
				'table_h3'    => 'قفل کوبایی',
				'rows'        => array(
					array( 'f' => 'ظاهر', 'a' => 'کلاسیک و ظریف', 'b' => 'پهن، یکپارچه و لوکس' ),
					array( 'f' => 'استحکام', 'a' => 'ضامن تاشو برای اطمینان', 'b' => 'محکم‌تر قفل می‌شود' ),
					array( 'f' => 'قیمت', 'a' => 'پایین‌ترین قیمت بازار', 'b' => 'روی اکونومی، فقط در تیادم با این قیمت' ),
					array( 'f' => 'مناسب برای', 'a' => 'استفاده روزانه و چندتایی دست‌کردن', 'b' => 'تک‌پوشی و هدیه' ),
				),
			),

			'care'     => array(
				'enabled' => 1,
				'title'   => 'نکات نگهداری',
				'desc'    => 'چند عادت ساده که برق تنیسی‌ات را طولانی‌تر نگه می‌دارد.',
				'items'   => array(
					array( 't' => 'دور از آب و کلر', 'd' => 'قبل از دوش، استخر و ورزش درش بیاور.', 'icon' => 'drop' ),
					array( 't' => 'اول عطر، بعد جواهر', 'd' => 'عطر، لوسیون و اسپری مو را قبل از پوشیدن بزن.', 'icon' => 'perfume' ),
					array( 't' => 'تمیزکاری ملایم', 'd' => 'با دستمال نرم و خشک پاکش کن؛ بدون مواد ساینده.', 'icon' => 'brush' ),
					array( 't' => 'نگهداری جدا', 'd' => 'در کیسه یا جعبه جدا بگذار تا خط نیفتد و گره نخورد.', 'icon' => 'box' ),
					array( 't' => 'چک کردن قفل', 'd' => 'هر چند وقت یک بار بسته‌شدن قفل و ضامن را بررسی کن.', 'icon' => 'lock' ),
				),
			),

			'faq'      => array(
				'enabled' => 1,
				'title'   => 'سؤالات پرتکرار',
				'schema'  => 1,
				'items'   => array(
					array( 'q' => 'بدلیجات تنیسی تیادم از چه جنسی است؟', 'a' => 'همه مدل‌ها با آبکاری رادیوم درجه ۱ ساخته شده‌اند. رادیوم فلزی از خانواده پلاتین است که رنگی نقره‌ای، روشن و براق می‌دهد و سطح را در برابر خط و کدرشدن مقاوم‌تر می‌کند.', 'open' => 1 ),
					array( 'q' => 'فرق قفل کتابی و قفل کوبایی چیست؟', 'a' => 'قفل کتابی جعبه‌ای کلاسیک با ضامن تاشوست و اقتصادی‌ترین انتخاب است؛ تیادم آن را با پایین‌ترین قیمت بازار عرضه می‌کند. قفل کوبایی پهن‌تر و محکم‌تر است و ظاهری یکپارچه دارد؛ قفل کوبایی روی محصولات اکونومی را در ایران فقط تیادم با این قیمت دارد.', 'open' => 0 ),
					array( 'q' => 'اکونومی بخرم یا مستر کوالیتی؟', 'a' => 'هر دو با آبکاری رادیوم درجه ۱ ساخته می‌شوند و هر دو با قفل کتابی یا کوبایی موجودند. [تفاوت دقیق نگین و وزن اکونومی و مستر کوالیتی]', 'open' => 0 ),
					array( 'q' => 'رنگ می‌دهد یا حساسیت ایجاد می‌کند؟', 'a' => '[پاسخ تیادم درباره ثبات رنگ و حساسیت پوستی]', 'open' => 0 ),
					array( 'q' => 'سایز مناسب را چطور انتخاب کنم؟', 'a' => 'دور مچ یا مچ پا را با متر نواری اندازه بگیر و حدود ۱ تا ۲ سانتی‌متر اضافه کن. [سایزهای موجود هر مدل]', 'open' => 0 ),
					array( 'q' => 'می‌توانم چند قطعه را با هم ست کنم؟', 'a' => 'بله؛ همه قطعه‌های تنیسی با یک ردیف نگین و یک آبکاری ساخته شده‌اند و کنار هم یکدست دیده می‌شوند.', 'open' => 0 ),
					array( 'q' => 'ارسال، ضمانت و مرجوعی چطور است؟', 'a' => '[شرایط ارسال، ضمانت و مرجوعی تیادم]', 'open' => 0 ),
				),
			),

			'related'  => array(
				'enabled' => 1,
				'title'   => 'دسته‌های مرتبط',
				'items'   => array(
					array( 'name' => 'گوشواره‌ها', 'image' => 'asset:earring-heart', 'alt' => 'گوشواره حلقه‌ای قلب رنگی', 'term' => 0, 'url' => '' ),
					array( 'name' => 'انگشترها', 'image' => 'asset:ring-bangle', 'alt' => 'انگشتر و النگوی کانالی', 'term' => 0, 'url' => '' ),
					array( 'name' => 'ست‌های هدیه', 'image' => 'asset:necklace-baguette', 'alt' => 'ست گردنبند باگت', 'term' => 0, 'url' => '' ),
					array( 'name' => 'دستبندهای ظریف', 'image' => 'asset:bracelet-thin', 'alt' => 'دستبند ظریف طلایی و نقره‌ای', 'term' => 0, 'url' => '' ),
				),
			),

			'footer'   => array(
				'links'            => array(
					array( 't' => 'محصولات تنیسی', 'href' => '#grid' ),
					array( 't' => 'راهنمای خرید', 'href' => '#guide' ),
					array( 't' => 'سؤالات', 'href' => '#faq' ),
				),
				'domain'           => 'tiadem.ir',
				'bar_enabled'      => 1,
				'bar_label'        => 'تنیسی‌ها از',
				'bar_price_mode'   => 'auto',
				'bar_price_manual' => '۴۵۰٬۰۰۰ تومان',
				'bar_btn'          => 'خرید فوری',
				'bar_link'         => '#grid',
			),

			'advanced' => array(
				'isolation'       => 'theme',
				'extra_dequeue'   => '',
				'theme_layout'    => 0,
				'animations'      => 1,
				'sparkles'        => 18,
				'schema_itemlist' => 1,
				'cache_hours'     => 12,
				'custom_css'      => '',
			),
		);
	}
}
