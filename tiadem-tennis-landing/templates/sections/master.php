<?php
/**
 * Master quality row.
 *
 * @package TiademTennisLanding
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;

TDL_Render::part(
	'partials/quality-row',
	array(
		's'     => $s,
		'key'   => 'master',
		'id'    => 'master',
		'theme' => 'dark',
	)
);
