<?php
/**
 * Full-page template of the landing (replaces the theme's homepage template).
 */
defined( 'ABSPATH' ) || exit;

$nl   = NRN_Content::get();
$wrap = 'nrn';
if ( $nl->setting( 'hide_tbd' ) ) {
	$wrap .= ' nrn-hide-tbd';
}
if ( $nl->setting( 'hide_phb' ) ) {
	$wrap .= ' nrn-hide-phb';
}
?><!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0B0C0F">
<?php if ( ! current_theme_supports( 'title-tag' ) ) : ?>
<title><?php echo esc_html( wp_get_document_title() ); ?></title>
<?php endif; ?>
<?php wp_head(); ?>
</head>
<body <?php body_class( 'nrn-body' ); ?>>
<?php wp_body_open(); ?>
<div class="<?php echo esc_attr( $wrap ); ?> nrn-d" data-layout="d" data-dw="<?php echo esc_attr( (int) $nl->setting( 'scale_from' ) ); ?>">
<?php $nl->layout = 'd'; include NRN_DIR . 'templates/layout-desktop.php'; ?>
</div>
<script>/* tablets: scale the desktop design before the first paint (neuron.js keeps it updated) */
window.nrnFit=function(){var h=document.querySelector('.nrn-d');if(!h)return 1;var r=h.querySelector('[data-ref="root"]');if(!r)return 1;var dw=+h.getAttribute('data-dw')||0;h.classList.remove('nrn-scaled');h.style.height='';var vw=h.clientWidth;if(!dw||!vw||vw>=dw||getComputedStyle(h).display==='none')return 1;var s=vw/dw;h.style.setProperty('--nrn-dw',dw+'px');h.style.setProperty('--nrn-s',s);h.classList.add('nrn-scaled');h.style.height=Math.ceil(r.offsetHeight*s)+'px';return s};window.nrnFit();</script>
<div class="<?php echo esc_attr( $wrap ); ?> nrn-m" data-layout="m">
<?php $nl->layout = 'm'; include NRN_DIR . 'templates/layout-mobile.php'; ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
