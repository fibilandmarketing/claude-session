<?php
/**
 * Jalali (Shamsi) <-> Gregorian conversion.
 *
 * @package TiademTennisLanding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Jalali calendar helpers (algorithm by Roozbeh Pournader & Mohammad Toossi).
 */
class TDL_Jalali {

	const TZ = 'Asia/Tehran';

	/**
	 * Month names.
	 *
	 * @return string[]
	 */
	public static function months() {
		return array( 1 => 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
	}

	/**
	 * Gregorian to Jalali.
	 *
	 * @param int $gy Year.
	 * @param int $gm Month.
	 * @param int $gd Day.
	 * @return int[] [jy, jm, jd]
	 */
	public static function to_jalali( $gy, $gm, $gd ) {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
		$days %= 12053;
		$jy   += 4 * intdiv( $days, 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy   += intdiv( $days - 1, 365 );
			$days  = ( $days - 1 ) % 365;
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

	/**
	 * Jalali to Gregorian.
	 *
	 * @param int $jy Year.
	 * @param int $jm Month.
	 * @param int $jd Day.
	 * @return int[] [gy, gm, gd]
	 */
	public static function to_gregorian( $jy, $jm, $jd ) {
		$jy  += 1595;
		$days = -355668 + ( 365 * $jy ) + ( intdiv( $jy, 33 ) * 8 ) + intdiv( ( $jy % 33 ) + 3, 4 ) + $jd + ( ( $jm < 7 ) ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
		$gy   = 400 * intdiv( $days, 146097 );
		$days %= 146097;
		if ( $days > 36524 ) {
			$gy  += 100 * intdiv( --$days, 36524 );
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
		$gd    = $days + 1;
		$leap  = ( ( 0 === $gy % 4 ) && ( 0 !== $gy % 100 ) ) || ( 0 === $gy % 400 );
		$sal_a = array( 0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		for ( $gm = 0; $gm < 13 && $gd > $sal_a[ $gm ]; $gm++ ) {
			$gd -= $sal_a[ $gm ];
		}
		return array( $gy, $gm, $gd );
	}

	/**
	 * Jalali parts (Tehran time) to a UNIX timestamp.
	 *
	 * @param int $jy Year.
	 * @param int $jm Month.
	 * @param int $jd Day.
	 * @param int $h  Hour.
	 * @param int $i  Minute.
	 * @return int
	 */
	public static function to_timestamp( $jy, $jm, $jd, $h = 0, $i = 0 ) {
		list( $gy, $gm, $gd ) = self::to_gregorian( (int) $jy, (int) $jm, (int) $jd );
		try {
			$dt = new DateTime( sprintf( '%04d-%02d-%02d %02d:%02d:00', $gy, $gm, $gd, (int) $h, (int) $i ), new DateTimeZone( self::TZ ) );
			return $dt->getTimestamp();
		} catch ( Exception $e ) {
			return 0;
		}
	}

	/**
	 * Timestamp to Jalali parts in Tehran time.
	 *
	 * @param int $ts Timestamp.
	 * @return array y,m,d,h,i
	 */
	public static function parts( $ts ) {
		$dt = new DateTime( '@' . (int) $ts );
		$dt->setTimezone( new DateTimeZone( self::TZ ) );
		list( $jy, $jm, $jd ) = self::to_jalali( (int) $dt->format( 'Y' ), (int) $dt->format( 'n' ), (int) $dt->format( 'j' ) );
		return array(
			'y' => $jy,
			'm' => $jm,
			'd' => $jd,
			'h' => (int) $dt->format( 'G' ),
			'i' => (int) $dt->format( 'i' ),
		);
	}

	/**
	 * Human readable Jalali date-time.
	 *
	 * @param int $ts Timestamp.
	 * @return string
	 */
	public static function format( $ts ) {
		if ( ! $ts ) {
			return '';
		}
		$p      = self::parts( $ts );
		$months = self::months();
		return tdl_fa( $p['d'] ) . ' ' . $months[ $p['m'] ] . ' ' . tdl_fa( $p['y'] ) . ' — ساعت ' . tdl_fa( sprintf( '%02d:%02d', $p['h'], $p['i'] ) );
	}
}
