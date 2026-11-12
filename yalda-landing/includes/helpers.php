<?php
/**
 * Small helpers: Persian digits, money format, Jalali dates.
 */

defined( 'ABSPATH' ) || exit;

/** Latin digits -> Persian digits. */
function yl_fa( $s ) {
	return strtr( (string) $s, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
}

/** Persian/Arabic digits -> Latin digits. */
function yl_en( $s ) {
	return strtr( (string) $s, array(
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	) );
}

/** 1234567 -> ۱٬۲۳۴٬۵۶۷ */
function yl_money( $n ) {
	return yl_fa( number_format( round( (float) $n ), 0, '', '٬' ) );
}

/** Jalali -> Gregorian. Returns array( y, m, d ). */
function yl_j2g( $jy, $jm, $jd ) {
	$jy   = (int) $jy + 1595;
	$jm   = (int) $jm;
	$days = -355668 + ( 365 * $jy ) + ( intdiv( $jy, 33 ) * 8 ) + intdiv( ( $jy % 33 ) + 3, 4 ) + (int) $jd + ( ( $jm < 7 ) ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
	$gy   = 400 * intdiv( $days, 146097 );
	$days %= 146097;
	if ( $days > 36524 ) {
		$days--;
		$gy  += 100 * intdiv( $days, 36524 );
		$days %= 36524;
		if ( $days >= 365 ) {
			$days++;
		}
	}
	$gy  += 4 * intdiv( $days, 1461 );
	$days %= 1461;
	if ( $days > 365 ) {
		$gy  += intdiv( $days - 1, 365 );
		$days = ( $days - 1 ) % 365;
	}
	$gd     = $days + 1;
	$leap   = ( 0 === $gy % 4 && 0 !== $gy % 100 ) || 0 === $gy % 400;
	$months = array( 0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
	for ( $gm = 1; $gm <= 12 && $gd > $months[ $gm ]; $gm++ ) {
		$gd -= $months[ $gm ];
	}
	return array( $gy, $gm, $gd );
}

/** Gregorian -> Jalali. Returns array( y, m, d ). */
function yl_g2j( $gy, $gm, $gd ) {
	$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
	$gy    = (int) $gy;
	$gm    = (int) $gm;
	$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
	$days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + (int) $gd + $g_d_m[ $gm - 1 ];
	$jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
	$days %= 12053;
	$jy   += 4 * intdiv( $days, 1461 );
	$days %= 1461;
	if ( $days > 365 ) {
		$jy  += intdiv( $days - 1, 365 );
		$days = ( $days - 1 ) % 365;
	}
	if ( $days < 186 ) {
		$jm = 1 + intdiv( $days, 31 );
		$jd = 1 + ( $days % 31 );
	} else {
		$jm = 7 + intdiv( $days - 186, 30 );
		$jd = 1 + ( ( $days - 186 ) % 30 );
	}
	return array( $jy, $jm, $jd );
}

/** Persian month names. */
function yl_months() {
	return array( 1 => 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
}

/**
 * Jalali date + "HH:MM" in Tehran time (+03:30, no DST) -> unix timestamp.
 */
function yl_jalali_ts( $jy, $jm, $jd, $time = '00:00' ) {
	list( $gy, $gm, $gd ) = yl_j2g( (int) $jy, (int) $jm, (int) $jd );
	$hm = explode( ':', (string) $time );
	$h  = isset( $hm[0] ) ? (int) $hm[0] : 0;
	$mi = isset( $hm[1] ) ? (int) $hm[1] : 0;
	return gmmktime( $h, $mi, 0, $gm, $gd, $gy ) - ( 3 * HOUR_IN_SECONDS + 30 * MINUTE_IN_SECONDS );
}

/** Multiplier that turns a WooCommerce price into Toman. */
function yl_toman_factor() {
	if ( ! function_exists( 'get_woocommerce_currency' ) ) {
		return 1;
	}
	switch ( get_woocommerce_currency() ) {
		case 'IRR':
			return 0.1;
		case 'IRHR':
			return 100;
		case 'IRHT':
			return 1000;
		default:
			return 1;
	}
}

/** Is WooCommerce available? */
function yl_woo() {
	return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
}
