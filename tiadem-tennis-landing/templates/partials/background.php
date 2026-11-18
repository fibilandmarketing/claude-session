<?php
/**
 * Decorative background: drifting blobs, sheen and sparkles.
 *
 * @package TiademTennisLanding
 */

defined( 'ABSPATH' ) || exit;

$count = (int) tdl_opt( 'advanced', 'sparkles', 18 );
$seed  = 7;
?>
<div class="vt-bg" aria-hidden="true">
	<div class="vt-sheen-layer"></div>
	<div class="vt-blob" style="--w:620px;top:-160px;left:-120px"></div>
	<div class="vt-blob" style="--w:520px;top:1500px;right:-180px;animation-delay:-6s"></div>
	<div class="vt-blob" style="--w:700px;top:3600px;left:-260px;animation-delay:-11s"></div>
	<div class="vt-blob" style="--w:560px;top:6200px;right:-200px;animation-delay:-3s"></div>
	<div class="vt-blob" style="--w:640px;top:8600px;left:-200px;animation-delay:-15s"></div>
	<?php
	for ( $i = 0; $i < $count; $i++ ) {
		$vals = array();
		for ( $k = 0; $k < 5; $k++ ) {
			$seed   = ( $seed * 9301 + 49297 ) % 233280;
			$vals[] = $seed / 233280;
		}
		printf(
			'<span class="vt-spark%6$s" style="top:%1$.2f%%;left:%2$.2f%%;--z:%3$dpx;animation-delay:%4$.2fs;animation-duration:%5$.2fs"></span>',
			2 + $vals[0] * 96,
			$vals[1] * 97,
			(int) round( 12 + $vals[2] * 16 ),
			-$vals[3] * 5,
			3.2 + $vals[4] * 3,
			$i % 2 ? ' vt-spark-d' : ''
		);
	}
	?>
</div>
