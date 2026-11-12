<?php
/**
 * Standalone landing template.
 *
 * @package yalda-landing
 */

defined( 'ABSPATH' ) || exit;

$S   = YL_Settings::all();
$g   = $S['general'];
$F   = 'YL_Frontend';
$cta = YL_Settings::on( 'build' ) ? '#build' : ( YL_Settings::on( 'yaldaf' ) ? '#yaldaf' : '#faq' );

$deals   = YL_Settings::on( 'yaldaf' ) ? YL_Data::deals() : array();
$recent  = YL_Settings::on( 'recent' ) ? YL_Data::recent() : array();
$catalog = YL_Settings::on( 'build' ) ? YL_Data::catalog() : array();

$end_ts = YL_Settings::target_ts();
$left   = max( 0, $end_ts - time() );
$days   = (int) floor( $left / DAY_IN_SECONDS );
$hours  = (int) floor( ( $left % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
$mins   = (int) floor( ( $left % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

$bg     = YL_Images::get( $F::img_value( 'general', 'bg' ), array( 1200 ) );
$style  = '--night:' . $g['c_night'] . ';--amb:' . $g['c_amb'] . ';--red:' . $g['c_red'] . ';--cream:' . $g['c_cream'] . ';';
$style .= $bg ? '--yl-bg:url(' . esc_url_raw( $bg['src'] ) . ');' : '--yl-bg:none;';

$icon_tel   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3h4l2 5-3 2a12 12 0 0 0 6 6l2-3 5 2v4a2 2 0 0 1-2 2C10.7 21 3 13.3 3 5a2 2 0 0 1 2-2Z"/></svg>';
$icon_plus  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>';
$card_sizes = '(max-width:760px) 68vw, 252px';

/* ---------- data for the script ---------- */
$build = $S['build'];
$steps = array();
foreach ( (array) $build['steps'] as $st ) {
	$opts = array();
	foreach ( (array) ( isset( $st['opts'] ) ? $st['opts'] : array() ) as $o ) {
		if ( ! isset( $o['label'] ) || '' === trim( (string) $o['label'] ) ) {
			continue;
		}
		$opts[] = array(
			'k'     => (string) $o['k'],
			'label' => (string) $o['label'],
			'min'   => isset( $o['min'] ) ? (float) $o['min'] : 0,
			'max'   => isset( $o['max'] ) ? (float) $o['max'] : 0,
		);
	}
	if ( ! $opts ) {
		continue;
	}
	$steps[] = array(
		'type'  => isset( $st['type'] ) ? $st['type'] : 'general',
		'multi' => ! empty( $st['multi'] ),
		'chip'  => ! empty( $st['chip'] ),
		'label' => (string) $st['label'],
		'q'     => (string) $st['q'],
		'hint'  => (string) $st['hint'],
		'opts'  => $opts,
	);
}
$p     = $S['pricing'];
$tiers = array();
foreach ( (array) $p['tiers'] as $t ) {
	$tiers[] = array( (int) $t['q'], (float) $t['pct'] );
}
usort( $tiers, static function ( $a, $b ) {
	return $a[0] - $b[0];
} );
$presets = array_values( array_filter( array_map( 'absint', explode( ',', str_replace( '،', ',', yl_en( (string) $p['presets'] ) ) ) ) ) );

$js_packs = array();
foreach ( $catalog as $it ) {
	$js_packs[] = YL_Data::js_item( $it ) + array( 'tags' => $it['tags'] );
}
$js_deals = array();
foreach ( $deals as $it ) {
	$js_deals[] = YL_Data::js_item( $it, $it['now'] ) + array( 'deal' => $it['deal'] > 0 );
}
$js_recent = array();
foreach ( $recent as $it ) {
	$js_recent[] = YL_Data::js_item( $it ) + array( 'people' => $it['people'] );
}
$fals = array();
foreach ( (array) $S['fal']['items'] as $f ) {
	if ( ! empty( $f['b1'] ) ) {
		$fals[] = array( 'b1' => $f['b1'], 'b2' => $f['b2'], 't' => $f['t'] );
	}
}

$config = array(
	'ajax'    => admin_url( 'admin-ajax.php' ),
	'cart'    => yl_woo() ? home_url( '/' ) : '',
	'payUrl'  => (string) $p['pay_url'],
	'end'     => $end_ts * 1000,
	'drift'   => (bool) $g['drift'],
	'reveal'  => (bool) $g['reveal'],
	'people'  => max( 1, (int) $p['people'] ),
	'min'     => max( 1, (int) $p['min'] ),
	'max'     => max( 1, (int) $p['max'] ),
	'step'    => max( 1, (int) $p['step'] ),
	'presets' => $presets,
	'tiers'   => $tiers,
	'results' => max( 1, (int) $build['results'] ),
	'steps'   => $steps,
	'packs'   => $js_packs,
	'deals'   => $js_deals,
	'recent'  => $js_recent,
	'fals'    => $fals,
	'proofMs' => (int) round( 1000 * min( 30, max( 3, (float) $S['proof']['interval'] ) ) ),
	't'       => array(
		'info'      => $build['info_label'],
		'fName'     => $build['f_name'],
		'fTel'      => $build['f_tel'],
		'fCo'       => $build['f_co'],
		'next'      => $build['next'],
		'prev'      => $build['prev'],
		'send'      => $build['send'],
		'pkOrder'   => $build['pk_order'],
		'pkSwap'    => $build['pk_swap'],
		'pkMore'    => $build['pk_more'],
		'barT'      => $build['bar_t'],
		'barS'      => $build['bar_s'],
		'barCta'    => $build['bar_cta'],
		'barT2'     => $build['bar_t2'],
		'barS2'     => $build['bar_s2'],
		'barCta2'   => $build['bar_cta2'],
		'mTitle'    => $p['title'],
		'qLabel'    => $p['q_label'],
		'note'      => $p['note'],
		'pay'       => $p['pay'],
		'invoice'   => $p['invoice'],
		'call'      => $p['call'],
		'invTitle'  => $p['inv_title'],
		'invCta'    => $p['inv_cta'],
		'invDone'   => $p['inv_done'],
		'callTitle' => $p['call_title'],
		'callLead'  => $p['call_lead'],
		'callCta'   => $p['call_cta'],
		'callDone'  => $p['call_done'],
		'doneTitle' => $p['done_title'],
		'swapTitle' => $p['swap_title'],
		'swapSub'   => $p['swap_sub'],
		'falCta'    => $S['fal']['cta'],
		'falBusy'   => $S['fal']['busy'],
		'more'      => $S['seo']['more'],
		'less'      => $S['seo']['less'],
		'endTitle'  => $S['end']['title'],
		'endOver'   => $S['end']['over'],
	),
);
?><!doctype html>
<html <?php language_attributes(); ?><?php echo is_rtl() ? '' : ' dir="rtl"'; ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="<?php echo esc_attr( $g['c_night'] ); ?>">
<?php if ( ! current_theme_supports( 'title-tag' ) ) : ?>
<title><?php echo esc_html( wp_get_document_title() ); ?></title>
<?php endif; ?>
<?php wp_head(); ?>
</head>
<body <?php body_class( 'yl-body' ); ?>>
<?php wp_body_open(); ?>
<div class="yl" id="yl" dir="rtl" style="<?php echo esc_attr( $style ); ?>">

<header class="yl-top">
	<div class="yl-bar yl-lq">
		<a class="yl-logo" href="#hero-t" aria-label="<?php echo esc_attr( $g['brand'] ); ?>"><?php echo $F::img( 'general', 'logo', array( 120 ), array( 'alt' => $g['logo_alt'], 'lazy' => false ) ); // phpcs:ignore ?></a>
		<?php if ( $g['nav_on'] ) : ?>
		<nav class="yl-nav" aria-label="بخش‌های صفحه">
			<?php foreach ( $F::nav() as $key => $label ) : ?>
				<a href="#<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>
		<?php if ( $g['phone'] ) : ?>
		<a class="yl-tel" href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', yl_en( $g['phone'] ) ) ); ?>" dir="ltr" aria-label="<?php echo esc_attr( 'تماس با فروش: ' . $g['phone_show'] ); ?>"><?php echo $icon_tel; // phpcs:ignore ?><?php echo esc_html( $g['phone_show'] ); ?></a>
		<?php endif; ?>
		<?php if ( $g['nav_cta'] ) : ?>
		<a class="yl-cta" href="<?php echo esc_attr( $cta ); ?>"><?php echo esc_html( $g['nav_cta'] ); ?></a>
		<?php endif; ?>
	</div>
</header>

<main>
<?php $h = $S['hero']; ?>
<section class="yl-hero" aria-labelledby="hero-t">
	<?php $F::deco( 'hero' ); ?>
	<div class="yl-hero-in">
		<div class="yl-hc">
			<h1 id="hero-t" class="yl-sr"><?php echo esc_html( $g['h1'] ); ?></h1>
			<?php echo $F::img( 'hero', 'title_img', array( 720, 1200 ), array( 'alt' => $h['title_alt'], 'class' => 'yl-hw', 'sizes' => '(max-width:760px) 92vw, 570px', 'high' => true ) ); // phpcs:ignore ?>
			<div class="yl-ha">
				<?php if ( $h['text'] ) : ?><p><?php echo esc_html( $h['text'] ); ?></p><?php endif; ?>
				<?php if ( $h['cta'] ) : ?><a id="yl-hero-cta" class="yl-cta" href="<?php echo esc_attr( $cta ); ?>"><?php echo esc_html( $h['cta'] ); ?></a><?php endif; ?>
				<?php if ( $h['sub'] ) : ?><p class="yl-tl"><?php echo esc_html( $h['sub'] ); ?></p><?php endif; ?>
				<?php if ( $h['cd_on'] ) : ?>
				<div class="yl-cd yl-lq" role="timer" aria-label="<?php echo esc_attr( 'زمان باقی‌مانده: ' . $h['cd_label'] ); ?>">
					<span><?php echo esc_html( $h['cd_label'] ); ?></span>
					<div class="yl-cdb"><b data-cd="d"><?php echo esc_html( yl_fa( $days ) ); ?></b><small>روز</small></div>
					<div class="yl-cdb"><b data-cd="h"><?php echo esc_html( yl_fa( $hours ) ); ?></b><small>ساعت</small></div>
					<div class="yl-cdb"><b data-cd="m"><?php echo esc_html( yl_fa( $mins ) ); ?></b><small>دقیقه</small></div>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<div class="yl-hs-w"><?php echo $F::img( 'hero', 'still_img', array( 600, 1000 ), array( 'alt' => $h['still_alt'], 'class' => 'yl-hs', 'sizes' => '(max-width:760px) 72vw, (max-width:1000px) 420px, 520px', 'lazy' => false ) ); // phpcs:ignore ?></div>
	</div>
</section>

<?php
foreach ( (array) $g['order'] as $sec ) :
	if ( ! YL_Settings::on( $sec ) ) {
		continue;
	}
	switch ( $sec ) :

		case 'yaldaf':
			$y = $S['yaldaf'];
			if ( ! $deals ) {
				break;
			}
			?>
<section id="yaldaf" class="yl-sec" aria-labelledby="yf-t">
	<?php $F::deco( 'yaldaf' ); ?>
	<div class="yl-wrap">
		<div class="yl-sh yl-rv">
			<?php $F::heading( 'yaldaf', 'yf-t' ); ?>
			<?php if ( $y['sub'] ) : ?><p><?php echo esc_html( $y['sub'] ); ?></p><?php endif; ?>
			<?php if ( $y['clock_on'] ) : ?>
			<div class="yl-clk yl-neu" role="timer" aria-label="<?php echo esc_attr( $y['clock_label'] ); ?>"><span><?php echo esc_html( $y['clock_label'] ); ?></span><b dir="ltr" data-clock>۰۰:۰۰</b></div>
			<?php endif; ?>
			<?php $F::note( $y['note'] ); ?>
		</div>
		<div id="yl-track" class="yl-rail" tabindex="0" aria-label="هدیه‌های تخفیف‌دار">
			<?php foreach ( $deals as $i => $d ) : ?>
			<article class="yl-deal yl-rv" style="--i:<?php echo (int) min( $i, 4 ); ?>">
				<a class="yl-nich" href="<?php echo esc_url( $d['url'] ? $d['url'] : '#' ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( 'مشاهده‌ی ' . $d['name'] ); ?>"><span class="yl-arch"><?php echo YL_Images::tag( $d['img'], array( 'alt' => $d['name'], 'sizes' => $card_sizes ) ); // phpcs:ignore ?></span><?php if ( $d['pct'] > 0 ) : ?><span class="yl-off"><?php echo esc_html( yl_fa( $d['pct'] ) . $y['badge'] ); ?></span><?php endif; ?></a>
				<h3><?php echo esc_html( $d['name'] ); ?></h3>
				<div class="yl-pr"><?php if ( $d['was'] > $d['now'] ) : ?><s><?php echo esc_html( yl_money( $d['was'] ) ); ?></s><?php endif; ?><?php echo esc_html( yl_money( $d['now'] ) ); ?> تومان</div>
				<button class="yl-soft" type="button" data-deal="<?php echo (int) $i; ?>"><?php echo esc_html( $y['btn'] ); ?></button>
			</article>
			<?php endforeach; ?>
		</div>
		<div class="yl-arw">
			<button type="button" aria-label="هدیه‌ی قبلی" data-slide="1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></button>
			<button type="button" aria-label="هدیه‌ی بعدی" data-slide="-1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg></button>
		</div>
	</div>
</section>
			<?php
			break;

		case 'build':
			$b = $build;
			if ( ! $steps ) {
				break;
			}
			$total = count( $steps ) + 1;
			$st0   = $steps[0];
			?>
<section id="build" class="yl-sec" aria-labelledby="bd-t">
	<?php $F::deco( 'build' ); ?>
	<div class="yl-wrap">
		<div class="yl-sh yl-rv">
			<?php $F::heading( 'build', 'bd-t' ); ?>
			<?php if ( $b['sub'] ) : ?><p data-gf-only><?php echo esc_html( $b['sub'] ); ?></p><?php endif; ?>
		</div>
		<div class="yl-gf" id="yl-gf">
			<?php echo $F::img( 'build', 'deco_img', array( 320 ), array( 'alt' => '', 'class' => 'yl-gf-wm', 'extra' => ' aria-hidden="true"' ) ); // phpcs:ignore ?>
			<div id="yl-gf-card" class="yl-gf-c">
				<h3 class="yl-gf-t"><?php echo esc_html( $b['card_title'] ); ?></h3>
				<div class="yl-gf-p" role="progressbar" aria-label="پیشرفت فرم" aria-valuemin="1" aria-valuemax="<?php echo (int) $total; ?>" aria-valuenow="1"><i style="width:<?php echo esc_attr( round( 100 / $total, 2 ) ); ?>%"></i></div>
				<div id="yl-gf-body">
					<p class="yl-gf-s"><?php echo esc_html( 'گام ' . yl_fa( 1 ) . ' از ' . yl_fa( $total ) . ': ' . $st0['label'] ); ?></p>
					<p id="yl-gf-q" class="yl-gf-q" tabindex="-1"><?php echo esc_html( $st0['q'] ); ?> <?php if ( $st0['hint'] ) : ?><small><?php echo esc_html( $st0['hint'] ); ?></small> <?php endif; ?><span class="yl-req" aria-hidden="true">*</span></p>
					<div class="yl-gf-os" role="<?php echo $st0['multi'] ? 'group' : 'radiogroup'; ?>" aria-labelledby="yl-gf-q">
						<?php foreach ( $st0['opts'] as $o ) : ?>
						<label class="yl-gf-o"><input type="<?php echo $st0['multi'] ? 'checkbox' : 'radio'; ?>" name="yl-s0" value="<?php echo esc_attr( $o['k'] ); ?>"><span><?php echo esc_html( $o['label'] ); ?></span></label>
						<?php endforeach; ?>
					</div>
					<div class="yl-gf-n"><button class="yl-cta" type="button" data-gf="next"><?php echo esc_html( $b['next'] ); ?></button></div>
				</div>
			</div>
		</div>
		<div id="yl-gr" class="yl-gr-h yl-neu" hidden>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
			<h3 role="status"><?php echo esc_html( $b['done_title'] ); ?></h3>
			<p><?php echo esc_html( $b['done_text'] ); ?></p>
			<div class="yl-gr-c" id="yl-gr-c"></div>
			<button class="yl-gr-e" type="button" data-gf="edit"><?php echo esc_html( $b['edit'] ); ?></button>
		</div>
		<div id="yl-pk-wrap" hidden>
			<?php if ( $b['note'] ) : ?><p class="yl-pk-n"><?php $F::note( $b['note'], false ); ?></p><?php endif; ?>
			<div class="yl-pks" id="yl-pks"></div>
		</div>
	</div>
</section>
			<?php
			break;

		case 'recent':
			$r = $S['recent'];
			if ( ! $recent ) {
				break;
			}
			?>
<section id="recent" class="yl-sec" aria-labelledby="rc-t">
	<?php $F::deco( 'recent' ); ?>
	<div class="yl-wrap">
		<div class="yl-sh yl-rv">
			<?php $F::heading( 'recent', 'rc-t' ); ?>
			<?php if ( $r['sub'] ) : ?><p><?php echo esc_html( $r['sub'] ); ?></p><?php endif; ?>
			<?php $F::note( $r['note'], false ); ?>
		</div>
		<div class="yl-rcg">
			<?php foreach ( $recent as $i => $it ) : ?>
			<article class="yl-rr yl-neu yl-rv" style="--i:<?php echo (int) ( $i % 2 ); ?>">
				<div class="yl-th"><?php echo YL_Images::tag( $it['img'], array( 'alt' => $it['name'] ) ); // phpcs:ignore ?></div>
				<div class="yl-nm"><b><?php echo esc_html( $it['name'] ); ?></b><span><?php echo esc_html( trim( $it['who'] . '، ' . yl_fa( $it['people'] ) . ' نفر', '، ' ) ); ?></span><span class="yl-amt"><?php echo esc_html( yl_money( $it['price'] ) ); ?> تومان</span></div>
				<button class="yl-soft" type="button" data-recent="<?php echo (int) $i; ?>"><?php echo esc_html( $it['cta'] ); ?></button>
			</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
			<?php
			break;

		case 'proof':
			$pf     = $S['proof'];
			$logos  = array_values( array_filter( (array) $pf['logos'], static function ( $lg ) {
				return ! empty( $lg['img'] ) || '' !== trim( (string) ( isset( $lg['name'] ) ? $lg['name'] : '' ) );
			} ) );
			$quotes = array_values( array_filter( (array) $pf['quotes'], static function ( $q ) {
				return '' !== trim( (string) ( isset( $q['text'] ) ? $q['text'] : '' ) );
			} ) );
			if ( ! $logos && ! $quotes ) {
				break;
			}
			$speeds = array( 'slow' => 26, 'normal' => 42, 'fast' => 64 );
			$speed  = isset( $speeds[ $pf['logo_speed'] ] ) ? $speeds[ $pf['logo_speed'] ] : 42;
			$nq     = count( $quotes );
			?>
<section id="proof" class="yl-sec yl-pf" aria-labelledby="pf-t">
	<?php $F::deco( 'proof' ); ?>
	<div class="yl-wrap">
		<div class="yl-sh yl-rv"><?php $F::heading( 'proof', 'pf-t' ); ?><?php if ( $pf['sub'] ) : ?><p><?php echo esc_html( $pf['sub'] ); ?></p><?php endif; ?></div>
	</div>
	<?php if ( $logos ) : ?>
	<div class="yl-mq yl-rv" role="region" aria-label="لوگوی مشتریان سازمانی" data-speed="<?php echo (int) $speed; ?>">
		<div class="yl-mq-t">
			<ul class="yl-mq-g">
				<?php foreach ( $logos as $lg ) : ?>
					<?php $li = ! empty( $lg['img'] ) ? YL_Images::get( $lg['img'], array( 300 ) ) : null; ?>
				<li class="yl-mq-i<?php echo $li ? '' : ' is-txt'; ?>"><?php echo $li ? YL_Images::tag( $li, array( 'alt' => $lg['name'], 'sizes' => '150px' ) ) : '<span>' . esc_html( $lg['name'] ) . '</span>'; // phpcs:ignore ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
	<?php endif; ?>
	<?php if ( $quotes ) : ?>
	<div class="yl-wrap">
		<div class="yl-qz yl-rv" role="region" aria-roledescription="اسلایدر" aria-label="نظر مشتریان سازمانی">
			<div class="yl-qz-c yl-neu">
				<svg class="yl-qz-mk" viewBox="0 0 48 36" aria-hidden="true"><path fill="currentColor" d="M20 0v6c-6 1.6-9 5.2-9.4 10.6H20V36H0V18C0 7.4 6.8 1.4 20 0Zm28 0v6c-6 1.6-9 5.2-9.4 10.6H48V36H28V18C28 7.4 34.8 1.4 48 0Z"/></svg>
				<div class="yl-qz-vp" aria-live="off">
					<?php
					foreach ( $quotes as $i => $q ) :
						$by    = trim( (string) $q['by'] );
						$parts = preg_split( '/\s*[،,]\s*/u', $by, 2 );
						$name  = isset( $parts[0] ) ? $parts[0] : '';
						$role  = isset( $parts[1] ) ? $parts[1] : '';
						$init  = '' !== $name ? mb_substr( preg_replace( '/^\[/u', '', $name ), 0, 1 ) : '';
						?>
					<figure class="yl-qz-s<?php echo 0 === $i ? ' is-on' : ''; ?>" role="group" aria-roledescription="نظر" aria-label="<?php echo esc_attr( yl_fa( $i + 1 ) . ' از ' . yl_fa( $nq ) ); ?>"<?php echo 0 === $i ? '' : ' aria-hidden="true"'; ?>>
						<blockquote><p><?php echo esc_html( $q['text'] ); ?></p></blockquote>
						<?php if ( '' !== $by ) : ?>
						<figcaption><?php if ( '' !== $init ) : ?><span class="yl-qz-av" aria-hidden="true"><?php echo esc_html( $init ); ?></span><?php endif; ?><span class="yl-qz-by"><b><?php echo esc_html( $name ); ?></b><?php if ( '' !== $role ) : ?><small><?php echo esc_html( $role ); ?></small><?php endif; ?></span></figcaption>
						<?php endif; ?>
					</figure>
					<?php endforeach; ?>
				</div>
			</div>
			<?php if ( $nq > 1 ) : ?>
			<div class="yl-qz-n">
				<button class="yl-qz-a" type="button" data-qz="-1" aria-label="نظر قبلی"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></button>
				<div class="yl-qz-d">
					<?php for ( $i = 0; $i < $nq; $i++ ) : ?>
					<button type="button" data-qz-go="<?php echo (int) $i; ?>" aria-label="<?php echo esc_attr( 'نظر ' . yl_fa( $i + 1 ) ); ?>"<?php echo 0 === $i ? ' class="is-on" aria-current="true"' : ''; ?>><i></i></button>
					<?php endfor; ?>
				</div>
				<button class="yl-qz-a" type="button" data-qz="1" aria-label="نظر بعدی"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg></button>
				<button class="yl-qz-p" type="button" data-qz-pause aria-pressed="false" aria-label="توقف جابه‌جایی خودکار"><svg class="i-pa" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg><svg class="i-pl" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.4-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z"/></svg></button>
			</div>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>
</section>
			<?php
			break;

		case 'fal':
			$fl = $S['fal'];
			if ( ! $fals ) {
				break;
			}
			$I = static function ( $name, $cls ) {
				return YL_Images::tag( YL_Images::get( 'b:' . $name ), array( 'alt' => '', 'class' => $cls ) );
			};
			?>
<section id="fal" class="yl-sec" aria-labelledby="fl-t">
	<?php $F::deco( 'fal' ); ?>
	<div class="yl-wrap">
		<div class="yl-sh yl-rv"><?php $F::heading( 'fal', 'fl-t' ); ?><?php if ( $fl['sub'] ) : ?><p><?php echo esc_html( $fl['sub'] ); ?></p><?php endif; ?></div>
		<div id="yl-fal-play">
			<div class="yl-fst" data-ph="idle" aria-hidden="true">
				<?php echo $I( 'fal-box', 'yl-fbox' ); // phpcs:ignore ?>
				<div class="yl-bd"><div class="yl-bd-h"><?php echo $I( 'bird-1', 'b1' ) . $I( 'bird-2', 'b2' ) . $I( 'bird-3', 'b3' ); // phpcs:ignore ?></div></div>
				<div class="yl-fe"><?php echo $I( 'env-sealed', 'e1' ) . $I( 'env-open', 'e2' ); // phpcs:ignore ?></div>
			</div>
			<div class="yl-fgo"><button class="yl-cta" type="button" data-fal="go"><?php echo esc_html( $fl['cta'] ); ?></button></div>
		</div>
		<div aria-live="polite">
			<div class="yl-fres" id="yl-fres" hidden>
				<div class="yl-fsh">
					<?php echo $I( 'fal-sheet', 'yl-fsh-i' ) . $I( 'bird-1', 'yl-fsh-b' ); // phpcs:ignore ?>
					<div class="yl-fsh-t"><p class="yl-bt"><span data-fal-b1></span><br><span data-fal-b2></span></p><p class="yl-tf" data-fal-t></p></div>
				</div>
				<div class="yl-fgo"><a class="yl-cta" href="<?php echo esc_attr( $cta ); ?>"><?php echo esc_html( $fl['go'] ); ?></a><button class="yl-soft" type="button" data-fal="go"><?php echo esc_html( $fl['again'] ); ?></button></div>
			</div>
		</div>
	</div>
</section>
			<?php
			break;

		case 'seo':
			$so = $S['seo'];
			if ( '' === trim( wp_strip_all_tags( $so['content'] ) ) && '' === trim( $so['title'] ) ) {
				break;
			}
			?>
<section id="seo" class="yl-sec">
	<div class="yl-wrap">
		<article class="yl-seo">
			<?php if ( $so['title'] ) : ?><h2 class="yl-hd"><?php echo esc_html( $so['title'] ); ?></h2><?php endif; ?>
			<div class="<?php echo $so['collapse'] ? 'yl-cl' : 'yl-cl-off'; ?>" id="yl-seo-more"><?php echo wp_kses_post( $so['content'] ); ?></div>
			<?php if ( $so['collapse'] ) : ?>
			<div class="yl-mr"><button class="yl-soft" type="button" aria-expanded="false" aria-controls="yl-seo-more" data-more><?php echo esc_html( $so['more'] ); ?></button></div>
			<?php endif; ?>
		</article>
	</div>
</section>
			<?php
			break;

		case 'faq':
			$fq = $S['faq'];
			if ( empty( $fq['items'] ) ) {
				break;
			}
			?>
<section id="faq" class="yl-sec" aria-labelledby="fq-t">
	<?php $F::deco( 'faq' ); ?>
	<div class="yl-wrap">
		<div class="yl-sh yl-rv"><?php $F::heading( 'faq', 'fq-t' ); ?></div>
		<div class="yl-fq">
			<?php
			foreach ( array_values( (array) $fq['items'] ) as $i => $f ) :
				$open = $fq['first_open'] && 0 === $i;
				?>
			<div class="yl-fi yl-neu<?php echo $open ? ' is-open' : ''; ?>">
				<h3><button type="button" id="yl-fq-<?php echo (int) $i; ?>" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="yl-fa-<?php echo (int) $i; ?>"><?php echo esc_html( $f['q'] ); ?><?php echo $icon_plus; // phpcs:ignore ?></button></h3>
				<div class="yl-fb" id="yl-fa-<?php echo (int) $i; ?>" role="region" aria-labelledby="yl-fq-<?php echo (int) $i; ?>"><div><p><?php echo wp_kses( nl2br( $f['a'] ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ), 'b' => array(), 'strong' => array(), 'em' => array(), 'br' => array() ) ); ?></p></div></div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
			<?php
			break;
	endswitch;
endforeach;

$e = $S['end'];
if ( $e['on'] ) :
	$title = $left > 0 ? str_replace( '{days}', yl_fa( $days ), $e['title'] ) : $e['over'];
	?>
<section class="yl-end" aria-labelledby="en-t">
	<?php $F::deco( 'end' ); ?>
	<div class="yl-wrap">
		<h2 id="en-t" class="yl-hd" data-end-title><?php echo esc_html( $title ); ?></h2>
		<?php if ( $e['text'] || $e['deadline'] ) : ?><p><?php echo esc_html( $e['text'] ); ?> <b style="white-space:nowrap"><?php echo esc_html( $e['deadline'] ); ?></b></p><?php endif; ?>
		<?php if ( $e['cta'] ) : ?><a class="yl-cta" href="<?php echo esc_attr( $cta ); ?>"><?php echo esc_html( $e['cta'] ); ?></a><?php endif; ?>
	</div>
</section>
<?php endif; ?>
</main>

<?php $ft = $S['footer']; ?>
<footer class="yl-ft">
	<div class="yl-wrap yl-ft-in">
		<?php if ( $ft['text'] ) : ?><span><?php echo esc_html( $ft['text'] ); ?></span><?php endif; ?>
		<?php if ( $g['phone'] ) : ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', yl_en( $g['phone'] ) ) ); ?>" dir="ltr"><?php echo esc_html( $g['phone_show'] ); ?></a><?php endif; ?>
		<?php
		if ( ! empty( $ft['seal_code'] ) ) {
			echo '<span class="yl-seal">' . wp_kses( $ft['seal_code'], array( // phpcs:ignore
				'a'   => array( 'href' => array(), 'target' => array(), 'rel' => array(), 'referrerpolicy' => array(), 'id' => array() ),
				'img' => array( 'src' => array(), 'alt' => array(), 'width' => array(), 'height' => array(), 'style' => array(), 'referrerpolicy' => array(), 'code' => array(), 'id' => array(), 'loading' => array() ),
			) ) . '</span>';
		} else {
			$seal = $F::img( 'footer', 'seal_img', array( 150 ), array( 'alt' => $ft['seal_alt'], 'class' => 'yl-seal-i' ) );
			if ( $seal ) {
				echo $ft['seal_url'] ? '<a class="yl-seal" href="' . esc_url( $ft['seal_url'] ) . '" target="_blank" rel="noopener" referrerpolicy="origin">' . $seal . '</a>' : '<span class="yl-seal">' . $seal . '</span>'; // phpcs:ignore
			}
		}
		?>
		<?php if ( ! empty( $ft['links'] ) ) : ?>
		<span class="yl-ft-l"><?php foreach ( (array) $ft['links'] as $l ) : ?><a href="<?php echo esc_url( $l['url'] ); ?>"><?php echo esc_html( $l['label'] ); ?></a><?php endforeach; ?></span>
		<?php endif; ?>
	</div>
</footer>

<?php if ( YL_Settings::on( 'build' ) && $steps ) : ?>
<div class="yl-mb yl-lq" id="yl-mb">
	<div><b data-bar-t><?php echo esc_html( $build['bar_t'] ); ?></b><span data-bar-s><?php echo esc_html( $build['bar_s'] ); ?></span></div>
	<button class="yl-cta" type="button" data-go-build><?php echo esc_html( $build['bar_cta'] ); ?></button>
</div>
<?php endif; ?>

<div id="yl-layer"></div>
</div>
<script type="application/json" id="yl-data"><?php echo wp_json_encode( $config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
<?php
if ( $g['faq_schema'] && YL_Settings::on( 'faq' ) && ! empty( $S['faq']['items'] ) ) {
	$ld = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array() );
	foreach ( (array) $S['faq']['items'] as $f ) {
		$ld['mainEntity'][] = array(
			'@type'          => 'Question',
			'name'           => wp_strip_all_tags( $f['q'] ),
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $f['a'] ) ),
		);
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . '</script>' . "\n";
}
wp_footer();
?>
</body>
</html>
