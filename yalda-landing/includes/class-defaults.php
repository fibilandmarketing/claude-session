<?php
/**
 * Default content = the approved design, so the landing works the moment the plugin is activated.
 */

defined( 'ABSPATH' ) || exit;

class YL_Defaults {

	/** Manual pack row helper. */
	private static function pack( $id, $name, $price, $tags = '', $extra = array() ) {
		return array_merge( array(
			'src'   => 'manual',
			'pid'   => 0,
			'name'  => $name,
			'img'   => 'b:packs/' . $id,
			'url'   => 'https://partakart.com/product/' . $id . '/',
			'price' => $price,
			'tags'  => '' === $tags ? array() : str_split( $tags ),
		), $extra );
	}

	public static function get() {
		$packs = array(
			self::pack( 'wooden-hexagon-serving-set', 'پک چوبی شش ضلعی', 783000, 'fa' ),
			self::pack( 'yalda-happy-gift-set-fruit-cups', 'پک هدیه شادی یلدا', 760000, 'f' ),
			self::pack( 'yalda-fun-game-gift-pack', 'پک بازی و شادی شب یلدا', 950000, 'fm' ),
			self::pack( 'yalda-poetic-gift-set', 'پک شاعرانه یلدا', 1910000, 'smfa' ),
			self::pack( 'yalda-night-joy-gift-set', 'پک هدیه شوق شب یلدا', 2060000, 's' ),
			self::pack( 'gift-set-lantern-yalda', 'پک هدیه فانوس شب یلدا', 2080000, 's' ),
			self::pack( 'traditional-yalda-gift-set', 'پک هدیه سنتی شب یلدا', 2184000, 's' ),
			self::pack( 'naghmeh-yalda-gift-pack', 'پک هدیه نغمه انار', 2660000, 'smfa' ),
			self::pack( 'yalda-pomegranate-gift-set', 'پک هدیه راز انار شب یلدا', 3220000, 'smfa' ),
			self::pack( 'yalda-mehr-mes-gift-set', 'پک هدیه مهر و مس شب یلدا', 3410000, 's' ),
			self::pack( 'yalda-long-night-secret-gift-set', 'پک راز شب بلند یلدا', 3560000, 's' ),
			self::pack( 'mesnar-copper-bowl-set', 'پک هدیه مسنار', 3630000, 's' ),
			self::pack( 'saffron-cardamom-gift-set', 'پک هدیه زعفران و هل', 3700000, 'smf' ),
			self::pack( 'copper-yalda-gift-set', 'پک هدیه چله مسی', 4720000, 's' ),
			self::pack( 'copper-gift-set-with-brass-spoon', 'پک هدیه مسی', 4869000, 's' ),
			self::pack( 'vase-and-flower-gift-pack', 'پک هدیه بته جقه', 4980000, 's' ),
			self::pack( 'yalda-grand-gift-set', 'پک هدیه چله‌ی باشکوه یلدا', 5824000, 'smfa' ),
			self::pack( 'yalda-fale-zaman-gift-set', 'پک هدیه فال زمان', 5840000, 's' ),
			self::pack( 'ghazal-giftset-copper-termeh-hafez', 'پک هدیه غزل خوانی', 6600000, 's' ),
			self::pack( 'ghazal-2-copper-hafez-gift-set', 'پک هدیه غزل خوانی ۲', 6740000, 's' ),
			self::pack( 'handicrafts-gift-pack-of-copper', 'پک شکلات خوری مس و پرداز و ترمه', 7150000, 's' ),
			self::pack( 'velvet-tapestry-and-tablecloth', 'پک جفت آجیل خوری ملیله کاری و رومیزی مخمل', 7670000, 's' ),
			self::pack( 'gift-set-fal-yalda', 'پک هدیه فال یلدا', 7760000, 'smfa' ),
			self::pack( 'handmade-melileh-set-royal-velvet', 'پک شیرینی خوری ملیله و رومیزی مخمل', 8100000, 's' ),
		);

		$deal = static function ( $id, $name, $price, $pct ) {
			$p = self::pack( $id, $name, $price );
			unset( $p['tags'] );
			$p['pct'] = $pct;
			return $p;
		};
		$recent = static function ( $id, $name, $price, $who, $people ) {
			$p = self::pack( $id, $name, $price );
			unset( $p['tags'] );
			$p['who']    = $who;
			$p['people'] = $people;
			$p['cta']    = 'همین پک را سفارش دهید';
			return $p;
		};

		return array(
			'general' => array(
				'page_id'    => 0,
				'brand'      => 'پارتاک‌آرت',
				'h1'         => 'هدیه‌ی سازمانی شب یلدا، پارتاک‌آرت',
				'logo'       => '',
				'logo_alt'   => 'لوگوی پارتاک‌آرت',
				'phone'      => '03191090203',
				'phone_show' => '۰۳۱-۹۱۰۹۰۲۰۳',
				'nav_on'     => 1,
				'nav_cta'    => 'انتخاب پک هدیه',
				'target'     => array( 'y' => 1405, 'm' => 9, 'd' => 30, 't' => '17:00' ),
				'order'      => array( 'yaldaf', 'build', 'recent', 'proof', 'fal', 'seo', 'faq' ),
				'deco'       => 1,
				'drift'      => 1,
				'reveal'     => 1,
				'isolate'    => 1,
				'shield'     => 1,
				'block_plugins' => array(),
				'block_words'   => 'afracc',
				'faq_schema' => 1,
				'bg'         => '',
				'c_night'    => '#0C113A',
				'c_amb'      => '#F7AC1E',
				'c_red'      => '#E2452A',
				'c_cream'    => '#F3EAD7',
				'round'      => 1000,
			),
			'hero'    => array(
				'title_img' => '',
				'title_alt' => 'یلدای پر ستاره پارتاک آرت؛ هدیه‌ی سازمانی شب یلدا به رسم قدردانی از تیم‌تان',
				'still_img' => '',
				'still_alt' => 'دیوان حافظ، ساعت، سینی آجیل و انار کاموایی',
				'text'      => 'در پنج گام کوتاه، پک هدیه‌ی مناسب بودجه‌تان را پیدا کنید و مشاوره بگیرید.',
				'cta'       => 'انتخاب پک هدیه',
				'sub'       => 'پرداخت آنلاین یا دریافت پیش‌فاکتور',
				'cd_on'     => 1,
				'cd_label'  => 'تا شب چله',
			),
			'yaldaf'  => array(
				'on'          => 1,
				'nav'         => 'یلدآف',
				'title_img'   => '',
				'title_alt'   => 'یلدآف پارتاک آرت',
				'sub'         => 'به رسم قدردانی، به بهانه‌ی یلدا',
				'clock_on'    => 1,
				'clock_label' => 'پایان تخفیف این ساعت',
				'note'        => '',
				'source'      => 'list',
				'woo_cat'     => 0,
				'woo_limit'   => 8,
				'btn'         => 'سفارش برای تیم',
				'badge'       => '٪ تخفیف',
				'items'       => array(
					$deal( 'yalda-pomegranate-gift-set', 'پک هدیه راز انار شب یلدا', 3220000, 12 ),
					$deal( 'yalda-long-night-secret-gift-set', 'پک راز شب بلند یلدا', 3560000, 8 ),
					$deal( 'yalda-poetic-gift-set', 'پک شاعرانه یلدا', 1910000, 15 ),
					$deal( 'traditional-yalda-gift-set', 'پک هدیه سنتی شب یلدا', 2184000, 10 ),
					$deal( 'yalda-night-joy-gift-set', 'پک هدیه شوق شب یلدا', 2060000, 12 ),
					$deal( 'gift-set-lantern-yalda', 'پک هدیه فانوس شب یلدا', 2080000, 8 ),
					$deal( 'wooden-hexagon-serving-set', 'پک چوبی شش ضلعی', 870000, 10 ),
					$deal( 'copper-yalda-gift-set', 'پک هدیه چله مسی', 4720000, 10 ),
				),
			),
			'build'   => array(
				'on'         => 1,
				'nav'        => 'انتخاب پک',
				'title_img'  => '',
				'title_alt'  => 'پک خودتان را بسازید',
				'sub'        => 'در پنج گام کوتاه، پک مناسب سازمان‌تان را پیدا کنید.',
				'deco_img'   => '',
				'card_title' => 'هدیه‌ی سفارشی خود را ایجاد کنید',
				'steps'      => array(
					array(
						'type'  => 'general',
						'multi' => 1,
						'chip'  => 0,
						'label' => 'انتخاب بازه‌ی سنی',
						'q'     => 'کسانی که هدیه می‌گیرند چند سال دارند؟',
						'hint'  => '(می‌توانید هم‌زمان چند گزینه را انتخاب کنید)',
						'opts'  => array(
							array( 'k' => 'u18', 'label' => 'زیر ۱۸ سال' ),
							array( 'k' => '18', 'label' => '۱۸ تا ۳۰ سال' ),
							array( 'k' => '30', 'label' => '۳۰ تا ۴۵ سال' ),
							array( 'k' => '45', 'label' => 'بالای ۴۵ سال' ),
						),
					),
					array(
						'type'  => 'mood',
						'multi' => 1,
						'chip'  => 1,
						'label' => 'انتخاب هدیه',
						'q'     => 'هدایا چه حال و هوایی داشته باشند؟',
						'hint'  => '',
						'opts'  => array(
							array( 'k' => 'd', 'label' => 'دیجیتال' ),
							array( 'k' => 's', 'label' => 'سنتی' ),
							array( 'k' => 'm', 'label' => 'ترکیبی' ),
							array( 'k' => 't', 'label' => 'تولیدی' ),
							array( 'k' => 'f', 'label' => 'همراه خوراکی' ),
							array( 'k' => 'a', 'label' => 'همراه آجیل' ),
						),
					),
					array(
						'type'  => 'budget',
						'multi' => 0,
						'chip'  => 1,
						'label' => 'میزان بودجه',
						'q'     => 'بودجه‌ی هر پک چقدر است؟',
						'hint'  => '(به تومان)',
						'opts'  => array(
							array( 'k' => 'b1', 'label' => 'تا یک میلیون', 'min' => 0, 'max' => 1000000 ),
							array( 'k' => 'b2', 'label' => '۱ تا ۳ میلیون', 'min' => 1000000, 'max' => 3000000 ),
							array( 'k' => 'b3', 'label' => '۳ تا ۵ میلیون', 'min' => 3000000, 'max' => 5000000 ),
							array( 'k' => 'b4', 'label' => 'بالای ۵ میلیون', 'min' => 5000000, 'max' => 0 ),
						),
					),
					array(
						'type'  => 'general',
						'multi' => 0,
						'chip'  => 1,
						'label' => 'بازه‌ی تحویل',
						'q'     => 'زمان تحویل چه موقع باشد؟',
						'hint'  => '',
						'opts'  => array(
							array( 'k' => 'w1', 'label' => 'هفته‌ی اول آذر' ),
							array( 'k' => 'w2', 'label' => 'هفته‌ی دوم آذر' ),
							array( 'k' => 'w3', 'label' => 'هفته‌ی سوم آذر' ),
							array( 'k' => 'm90', 'label' => 'دقیقه‌ی ۹۰!' ),
						),
					),
				),
				'info_label' => 'اطلاعات مشتری',
				'f_name'     => 'نام و نام خانوادگی',
				'f_tel'      => 'شماره تماس',
				'f_co'       => 'نام سازمان',
				'next'       => 'ادامه‌ی مرحله‌ی بعد',
				'prev'       => 'مرحله‌ی قبل',
				'send'       => 'ثبت درخواست و دریافت مشاوره',
				'done_title' => 'درخواست شما ثبت شد',
				'done_text'  => 'کارشناس ما برای مشاوره با شما تماس می‌گیرد. این پک‌ها با پاسخ‌های شما هماهنگ‌ترند.',
				'edit'       => 'ویرایش پاسخ‌ها',
				'results'    => 3,
				'note'       => '',
				'pk_order'   => 'سفارش این پک',
				'pk_swap'    => 'تغییر پک',
				'pk_more'    => 'جزئیات پک',
				'bar_t'      => 'هدیه‌ی سفارشی سازمان‌تان',
				'bar_s'      => 'پنج گام کوتاه تا پک مناسب',
				'bar_cta'    => 'شروع کنید',
				'bar_t2'     => 'پک‌های پیشنهادی شما',
				'bar_s2'     => 'بر اساس پاسخ‌هایی که دادید',
				'bar_cta2'   => 'دیدن پک‌ها',
			),
			'catalog' => array(
				'items' => $packs,
			),
			'pricing' => array(
				'people'      => 50,
				'min'         => 1,
				'max'         => 5000,
				'step'        => 5,
				'presets'     => '20,50,100,250',
				'tiers'       => array(
					array( 'q' => 10, 'pct' => 5 ),
					array( 'q' => 50, 'pct' => 10 ),
					array( 'q' => 100, 'pct' => 15 ),
					array( 'q' => 250, 'pct' => 20 ),
				),
				'cart_tiers'  => 1,
				'title'       => 'پک شما آماده است',
				'q_label'     => 'برای چند نفر؟',
				'note'        => '',
				'pay'         => 'ادامه و پرداخت آنلاین',
				'pay_url'     => '',
				'invoice'     => 'دریافت پیش‌فاکتور',
				'call'        => 'تماس کارشناس با من',
				'inv_title'   => 'دریافت پیش‌فاکتور',
				'inv_cta'     => 'ارسال پیش‌فاکتور',
				'inv_done'    => 'پیش‌فاکتور به‌زودی برای شما ارسال می‌شود.',
				'call_title'  => 'تماس کارشناس فروش سازمانی',
				'call_lead'   => 'شماره‌تان را بگذارید تا کارشناس با شما هماهنگ کند.',
				'call_cta'    => 'ثبت درخواست تماس',
				'call_done'   => 'کارشناس ما به‌زودی با شما تماس می‌گیرد.',
				'done_title'  => 'درخواست شما ثبت شد',
				'swap_title'  => 'یک پک دیگر انتخاب کنید',
				'swap_sub'    => 'پک‌های هم‌قیمت اول آمده‌اند.',
			),
			'recent'  => array(
				'on'        => 1,
				'nav'       => 'پک‌های اخیر',
				'title_img' => '',
				'title_alt' => 'آخرین پک‌های ساخته‌شده',
				'sub'       => 'ایده بگیرید و همان پک را برای تیم خودتان سفارش دهید.',
				'note'      => '',
				'items'     => array(
					$recent( 'yalda-grand-gift-set', 'پک هدیه چله‌ی باشکوه یلدا', 5824000, 'شرکت فناوری', 50 ),
					$recent( 'yalda-mehr-mes-gift-set', 'پک هدیه مهر و مس شب یلدا', 3410000, 'هلدینگ ساختمانی', 250 ),
					$recent( 'naghmeh-yalda-gift-pack', 'پک هدیه نغمه انار', 2660000, 'استودیوی طراحی', 20 ),
					$recent( 'gift-set-fal-yalda', 'پک هدیه فال یلدا', 7760000, 'مجموعه‌ی درمانی', 100 ),
				),
			),
			'proof'   => array(
				'on'        => 1,
				'nav'       => '',
				'title_img' => '',
				'title_alt' => 'نظر شرکت‌ها',
				'sub'       => '',
				'logos'     => array(
					array( 'img' => '', 'name' => '[لوگوی مشتری ۱]' ),
					array( 'img' => '', 'name' => '[لوگوی مشتری ۲]' ),
					array( 'img' => '', 'name' => '[لوگوی مشتری ۳]' ),
					array( 'img' => '', 'name' => '[لوگوی مشتری ۴]' ),
					array( 'img' => '', 'name' => '[لوگوی مشتری ۵]' ),
				),
				'quotes'    => array(
					array( 'text' => '[نظر واقعی مشتری درباره‌ی کیفیت و بسته‌بندی پک]', 'by' => '[نام و سمت]، [نام شرکت]' ),
					array( 'text' => '[نظر واقعی مشتری درباره‌ی تحویل به‌موقع پیش از یلدا]', 'by' => '[نام و سمت]، [نام شرکت]' ),
				),
				'logo_speed'  => 'normal',
				'quote_speed' => 'slow',
			),
			'fal'     => array(
				'on'        => 1,
				'nav'       => 'فال حافظ',
				'title_img' => '',
				'title_alt' => 'فال حافظ',
				'sub'       => 'نیت کنید و از قناری بخواهید فال‌تان را بیاورد.',
				'cta'       => 'قناری، فالم را بیاور',
				'busy'      => 'قناری دارد فال شما را می‌آورد',
				'go'        => 'انتخاب پک یلدا',
				'again'     => 'یک فال دیگر',
				'items'     => array(
					array( 'b1' => 'یوسف گم‌گشته بازآید به کنعان غم مخور', 'b2' => 'کلبه‌ی احزان شود روزی گلستان غم مخور', 't' => 'آنچه از دست رفته به شکلی تازه برمی‌گردد. صبور باشید؛ روزهای روشن‌تر در راه است.' ),
					array( 'b1' => 'روز هجران و شب فرقت یار آخر شد', 'b2' => 'زدم این فال و گذشت اختر و کار آخر شد', 't' => 'شب هرچقدر هم بلند باشد صبح دارد. دوره‌ی سخت رو به پایان است و کارها سامان می‌گیرد.' ),
					array( 'b1' => 'مژده ای دل که مسیحا نفسی می‌آید', 'b2' => 'که ز انفاس خوشش بوی کسی می‌آید', 't' => 'خبری خوش نزدیک است. کسی می‌رسد که حضورش حال و هوای این روزها را عوض می‌کند.' ),
					array( 'b1' => 'بر سر آنم که گر ز دست برآید', 'b2' => 'دست به کاری زنم که غصه سرآید', 't' => 'وقت تصمیم است. قدم اول را بردارید؛ همان کاری که مدت‌هاست به آن فکر می‌کنید.' ),
					array( 'b1' => 'ز کوی یار می‌آید نسیم باد نوروزی', 'b2' => 'از این باد ار مدد خواهی چراغ دل برافروزی', 't' => 'نسیم تازه‌ای در راه است. از این فرصت کمک بگیرید و چراغ امیدتان را روشن نگه دارید.' ),
					array( 'b1' => 'در ازل پرتو حسنت ز تجلی دم زد', 'b2' => 'عشق پیدا شد و آتش به همه عالم زد', 't' => 'عشق به کار و آدم‌ها سرمایه‌ی شماست. با دل گرم پیش بروید؛ اطرافیان‌تان هم گرم می‌شوند.' ),
				),
			),
			'seo'     => array(
				'on'       => 1,
				'title'    => 'هدیه‌ی سازمانی شب یلدا؛ قدردانی به زبان سنت',
				'content'  => '<p>شب یلدا فرصتی است که سازمان، پیش از پایان سال، از همکارانش تشکر کند. هدیه‌ی سازمانی شب یلدا اگر با فرهنگ این شب هم‌خوان باشد، از یک بسته‌ی تبلیغاتی فراتر می‌رود و در خانه‌ی کارمند، کنار سفره‌ی چله، جا پیدا می‌کند.</p>' . "\n"
					. '<h3>چه چیزی در پک یلدای کارکنان بگذاریم؟</h3>' . "\n"
					. '<p>ترکیب رایج یک پک هدیه‌ی شب چله سه بخش دارد: یک یادگاری ماندگار مانند دیوان حافظ، یک تکه‌ی تزئینی برای سفره مانند قندان، آجیل‌خوری یا رومیزی ترمه، و یک خوراکی شب‌نشینی مانند آجیل، تخمه یا شکلات.</p>' . "\n"
					. '<h3>بودجه‌ی هدیه‌ی سازمانی را چطور تعیین کنیم؟</h3>' . "\n"
					. '<p>از سقف بودجه‌ی هر نفر شروع کنید، نه از محصول. وقتی تعداد نفرات و بودجه‌ی هر پک مشخص باشد، انتخاب ساده‌تر می‌شود و جمع نهایی پیش از سفارش روشن است. برای تیم‌های بزرگ، پلکان تخفیف تعداد می‌تواند سهم بیشتری از بودجه را به کیفیت پک برگرداند.</p>' . "\n"
					. '<h3>چرا سفارش را زودتر ثبت کنیم؟</h3>' . "\n"
					. '<p>شب یلدای ۱۴۰۵ دوشنبه ۳۰ آذر است. آماده‌سازی و بسته‌بندی سفارش‌های پرتعداد زمان می‌برد، پس هرچه زودتر فهرست همکاران و آدرس تحویل را نهایی کنید، پک‌ها با خیال راحت‌تری پیش از شب چله به دست تیم می‌رسد.</p>',
				'collapse' => 1,
				'more'     => 'بیشتر بخوانید',
				'less'     => 'بستن',
			),
			'faq'     => array(
				'on'         => 1,
				'nav'        => 'سوالات',
				'title_img'  => '',
				'title_alt'  => 'سوالات متداول',
				'first_open' => 1,
				'items'      => array(
					array( 'q' => 'حداقل تعداد سفارش سازمانی چقدر است؟', 'a' => 'سفارش سازمانی از [حداقل تعداد سفارش] پک شروع می‌شود. پک را انتخاب کنید و تعداد را وارد کنید تا قیمت را ببینید.' ),
					array( 'q' => 'تخفیف تعداد چطور محاسبه می‌شود؟', 'a' => 'بعد از انتخاب پک، با تغییر تعداد نفرات درصد تخفیف و مبلغ نهایی را همان لحظه می‌بینید.' ),
					array( 'q' => 'پیش‌فاکتور رسمی می‌دهید؟', 'a' => 'بله. پس از انتخاب پک گزینه‌ی «دریافت پیش‌فاکتور» را بزنید و اطلاعات شرکت را وارد کنید.' ),
					array( 'q' => 'پک‌ها تا چه تاریخی تحویل می‌شوند؟', 'a' => 'سفارش‌هایی که تا [آخرین مهلت سفارش] ثبت شوند پیش از شب یلدا، یعنی دوشنبه ۳۰ آذر، تحویل می‌شوند.' ),
					array( 'q' => 'می‌توانید به چند شعبه یا آدرس ارسال کنید؟', 'a' => '[شرایط ارسال به چند آدرس و هزینه‌ی آن]' ),
					array( 'q' => 'امکان درج لوگو یا کارت تبریک اختصاصی هست؟', 'a' => '[شرایط چاپ لوگو و کارت تبریک سازمانی]' ),
				),
			),
			'end'     => array(
				'on'       => 1,
				'title'    => '{days} روز تا شب چله',
				'over'     => 'یلدا مبارک!',
				'text'     => 'مهلت ثبت سفارش سازمانی:',
				'deadline' => '[آخرین مهلت سفارش]',
				'cta'      => 'انتخاب پک هدیه',
			),
			'footer'  => array(
				'text'       => 'پارتاک‌آرت، خرید هدیه‌های خاص و به یاد ماندنی',
				'seal_img'   => '',
				'seal_alt'   => 'نماد اعتماد الکترونیکی',
				'seal_url'   => '',
				'seal_code'  => '',
				'links'      => array(
					array( 'label' => 'همه‌ی هدیه‌های شب یلدا', 'url' => 'https://partakart.com/product-category/hedie-monasebati/buy-a-yalda-night-gift/' ),
					array( 'label' => 'سفارش هدیه سازمانی', 'url' => 'https://partakart.com/ordering-corporate-gifts/' ),
				),
			),
			'leads'   => array(
				'notify'  => 1,
				'email'   => '',
				'webhook' => '',
			),
		);
	}

	/** Bundled default image for a field (section.field). */
	public static function image( $section, $field ) {
		$map = array(
			'general.logo'    => 'logo',
			'general.bg'      => 'knit-bg',
			'hero.title_img'  => 'hero-title',
			'hero.still_img'  => 'hero-still',
			'yaldaf.title_img' => 't-yaldaf',
			'build.title_img' => 't-build',
			'build.deco_img'  => 'watermelon',
			'recent.title_img' => 't-recent',
			'proof.title_img' => 't-proof',
			'fal.title_img'   => 't-fal',
			'faq.title_img'   => 't-faq',
			'footer.seal_img' => 'enamad',
		);
		$k = $section . '.' . $field;
		return isset( $map[ $k ] ) ? 'b:' . $map[ $k ] : '';
	}
}
