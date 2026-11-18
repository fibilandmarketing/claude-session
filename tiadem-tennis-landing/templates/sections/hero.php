<?php
/**
 * Hero with glass pedestal.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;

$poster = $s['poster'] ? $s['poster'] : $s['image'];
$anim   = ( $s['image'] && $s['image'] !== $poster ) ? tdl_img_url( $s['image'] ) : '';
?>
<section id="top" class="vt-hero vt-sec">
	<div class="vt-hero-in">
		<div class="vt-hero-text vt-rise">
			<h1 class="vt-h1"><?php echo esc_html( $s['title'] ); ?></h1>
			<p class="vt-lead vt-hide-sm"><?php echo esc_html( $s['lead'] ); ?></p>
			<p class="vt-lead vt-only-sm"><?php echo esc_html( $s['lead_mobile'] ? $s['lead_mobile'] : $s['lead'] ); ?></p>
			<div class="vt-hero-ctas">
				<a href="<?php echo esc_url( $s['cta_link'] ); ?>" class="vt-btn vt-hero-cta"><?php echo esc_html( $s['cta_text'] ); ?><?php echo tdl_glyph( 'arrow-left', 18 ); // phpcs:ignore ?></a>
			</div>
		</div>
		<div class="vt-hero-vis" data-tdl-tilt>
			<div class="vt-ped-wrap<?php echo $s['pedestal'] ? ' vt-reflect-hero' : ' vt-no-ped'; ?>">
				<div class="vt-ped-light"></div>
				<span class="vt-spark" style="top:4%;right:18%;--z:34px;animation-delay:.4s"></span>
				<span class="vt-spark" style="top:22%;left:6%;--z:22px;animation-delay:1.6s"></span>
				<span class="vt-spark" style="top:12%;left:46%;--z:18px;animation-delay:2.7s"></span>
				<span class="vt-spark" style="top:40%;right:4%;--z:26px;animation-delay:3.3s"></span>
				<?php
				echo tdl_img( // phpcs:ignore
					$poster,
					array(
						'alt'           => $s['image_alt'],
						'class'         => 'vt-hero-gif',
						'loading'       => 'eager',
						'fetchpriority' => 'high',
						'size'          => 'large',
						'sizes'         => '(max-width: 700px) 92vw, 600px',
						'extra'         => $anim ? 'data-tdl-anim="' . esc_url( $anim ) . '"' : '',
					)
				);
				?>
				<?php if ( $s['pedestal'] ) : ?>
				<div class="vt-ped">
					<div class="vt-ped-glow"></div>
					<div class="vt-ped-top"></div>
					<div class="vt-ped-body"></div>
				</div>
				<div class="vt-ped-shadow"></div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
