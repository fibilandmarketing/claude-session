<?php
/**
 * Hero with glass pedestal.
 *
 * The animated image (GIF / animated WebP) is always served as the original,
 * untouched file at full quality and starts playing immediately. A light
 * first-frame poster sits underneath only until the animation has loaded.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;

list( $anim_url, $anim_w, $anim_h ) = tdl_img_original( $s['image'] );
$poster_url = ( $s['poster'] && $s['poster'] !== $s['image'] ) ? tdl_img_original( $s['poster'] )[0] : '';
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
				<?php if ( $anim_url ) : ?>
				<div class="vt-hero-media" data-tdl-hero>
					<?php if ( $poster_url ) : ?>
						<img class="vt-hero-poster" src="<?php echo esc_url( $poster_url ); ?>" alt="" aria-hidden="true" loading="eager" decoding="async"<?php echo $anim_w ? ' width="' . (int) $anim_w . '" height="' . (int) $anim_h . '"' : ''; ?>>
					<?php endif; ?>
					<img class="vt-hero-gif" src="<?php echo esc_url( $anim_url ); ?>" alt="<?php echo esc_attr( $s['image_alt'] ); ?>" loading="eager" fetchpriority="high"<?php echo $anim_w ? ' width="' . (int) $anim_w . '" height="' . (int) $anim_h . '"' : ''; ?> onload="this.parentNode.classList.add('is-ready')">
				</div>
				<?php endif; ?>
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
