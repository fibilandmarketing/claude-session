<?php
/**
 * Leads from the landing forms: 5-step builder, pro-forma invoice and call-back requests.
 */

defined( 'ABSPATH' ) || exit;

class YL_Leads {

	const TYPE = 'yl_lead';

	public static function types() {
		return array(
			'builder' => 'مشاوره‌ی پک (فرم پنج‌مرحله‌ای)',
			'invoice' => 'درخواست پیش‌فاکتور',
			'call'    => 'درخواست تماس کارشناس',
		);
	}

	public static function statuses() {
		return array(
			'new'       => 'جدید',
			'contacted' => 'تماس گرفته شد',
			'done'      => 'نهایی شد',
			'lost'      => 'منصرف شد',
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_type' ) );
		add_action( 'wp_ajax_yl_lead', array( __CLASS__, 'submit' ) );
		add_action( 'wp_ajax_nopriv_yl_lead', array( __CLASS__, 'submit' ) );
		if ( is_admin() ) {
			add_filter( 'manage_' . self::TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
			add_action( 'manage_' . self::TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
			add_action( 'add_meta_boxes_' . self::TYPE, array( __CLASS__, 'boxes' ) );
			add_action( 'save_post_' . self::TYPE, array( __CLASS__, 'save_status' ) );
			add_action( 'admin_post_yl_export', array( __CLASS__, 'export' ) );
			add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) );
			add_action( 'pre_get_posts', array( __CLASS__, 'filter_query' ) );
			add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
			add_filter( 'bulk_actions-edit-' . self::TYPE, array( __CLASS__, 'bulk_actions' ) );
		}
	}

	public static function register_type() {
		register_post_type( self::TYPE, array(
			'labels'          => array(
				'name'               => 'درخواست‌های لندینگ',
				'singular_name'      => 'درخواست',
				'menu_name'          => 'درخواست‌ها',
				'all_items'          => 'درخواست‌ها',
				'edit_item'          => 'جزئیات درخواست',
				'search_items'       => 'جستجوی درخواست‌ها',
				'not_found'          => 'هنوز درخواستی ثبت نشده است.',
				'not_found_in_trash' => 'درخواستی در زباله‌دان نیست.',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'yalda-landing',
			'show_in_rest'    => false,
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
		) );
	}

	/* ------------------------------------------------------------------ submit */

	private static function fail( $msg, $code = 400 ) {
		wp_send_json( array( 'ok' => false, 'msg' => $msg ), $code );
	}

	private static function ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
	}

	public static function submit() {
		// phpcs:disable WordPress.Security.NonceVerification -- public form on a cacheable page; protected by honeypot + rate limit.
		$p = wp_unslash( $_POST );
		// phpcs:enable

		if ( ! empty( $p['website'] ) ) {
			wp_send_json( array( 'ok' => true ) ); // Honeypot: pretend success.
		}

		$rl  = 'yl_rl_' . md5( self::ip() );
		$hit = (int) get_transient( $rl );
		if ( $hit >= 8 ) {
			self::fail( 'تعداد درخواست‌ها زیاد است؛ چند دقیقه‌ی دیگر دوباره تلاش کنید.', 429 );
		}

		$type = isset( $p['type'] ) ? sanitize_key( $p['type'] ) : '';
		if ( ! isset( self::types()[ $type ] ) ) {
			self::fail( 'درخواست نامعتبر است.' );
		}
		$name = isset( $p['name'] ) ? mb_substr( sanitize_text_field( $p['name'] ), 0, 100 ) : '';
		$co   = isset( $p['co'] ) ? mb_substr( sanitize_text_field( $p['co'] ), 0, 150 ) : '';
		$tel  = preg_replace( '/\D+/', '', yl_en( isset( $p['tel'] ) ? (string) $p['tel'] : '' ) );
		if ( 0 === strpos( $tel, '98' ) && 12 === strlen( $tel ) ) {
			$tel = '0' . substr( $tel, 2 );
		}
		if ( '' === trim( $name ) ) {
			self::fail( 'نام و نام خانوادگی را وارد کنید.' );
		}
		if ( ! preg_match( '/^09\d{9}$/', $tel ) ) {
			self::fail( 'شماره موبایل را با ۰۹ و ۱۱ رقم وارد کنید.' );
		}
		if ( '' === trim( $co ) ) {
			self::fail( 'نام سازمان را وارد کنید.' );
		}

		$answers = array();
		if ( ! empty( $p['answers'] ) ) {
			$raw = json_decode( (string) $p['answers'], true );
			if ( is_array( $raw ) ) {
				foreach ( array_slice( $raw, 0, 12 ) as $a ) {
					if ( isset( $a['q'], $a['a'] ) ) {
						$answers[] = array(
							'q' => mb_substr( sanitize_text_field( (string) $a['q'] ), 0, 200 ),
							'a' => mb_substr( sanitize_text_field( is_array( $a['a'] ) ? implode( '، ', $a['a'] ) : (string) $a['a'] ), 0, 300 ),
						);
					}
				}
			}
		}

		$meta = array(
			'_yl_type'    => $type,
			'_yl_name'    => $name,
			'_yl_tel'     => $tel,
			'_yl_co'      => $co,
			'_yl_answers' => $answers,
			'_yl_pack'    => isset( $p['pack'] ) ? mb_substr( sanitize_text_field( $p['pack'] ), 0, 200 ) : '',
			'_yl_packs'   => isset( $p['packs'] ) ? mb_substr( sanitize_text_field( $p['packs'] ), 0, 600 ) : '',
			'_yl_qty'     => isset( $p['qty'] ) ? absint( yl_en( $p['qty'] ) ) : 0,
			'_yl_unit'    => isset( $p['unit'] ) ? (float) yl_en( $p['unit'] ) : 0,
			'_yl_total'   => isset( $p['total'] ) ? (float) yl_en( $p['total'] ) : 0,
			'_yl_off'     => isset( $p['off'] ) ? (float) yl_en( $p['off'] ) : 0,
			'_yl_status'  => 'new',
		);

		$id = wp_insert_post( array(
			'post_type'   => self::TYPE,
			'post_status' => 'publish',
			'post_title'  => $name . ' — ' . $co,
		), true );
		if ( is_wp_error( $id ) ) {
			self::fail( 'ثبت درخواست ناموفق بود؛ دوباره تلاش کنید.', 500 );
		}
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		set_transient( $rl, $hit + 1, 10 * MINUTE_IN_SECONDS );
		delete_transient( 'yl_new_leads' );

		self::notify( $id, $meta );
		wp_send_json( array( 'ok' => true ) );
	}

	private static function lines( $m ) {
		$t   = self::types();
		$out = array(
			'نوع درخواست' => $t[ $m['_yl_type'] ],
			'نام'         => $m['_yl_name'],
			'موبایل'      => $m['_yl_tel'],
			'سازمان'      => $m['_yl_co'],
		);
		if ( $m['_yl_pack'] ) {
			$out['پک'] = $m['_yl_pack'];
		}
		if ( $m['_yl_qty'] ) {
			$out['تعداد'] = yl_fa( $m['_yl_qty'] );
		}
		if ( $m['_yl_unit'] ) {
			$out['قیمت هر پک'] = yl_money( $m['_yl_unit'] ) . ' تومان';
		}
		if ( $m['_yl_off'] ) {
			$out['تخفیف تعداد'] = yl_fa( $m['_yl_off'] ) . '٪';
		}
		if ( $m['_yl_total'] ) {
			$out['مبلغ نهایی'] = yl_money( $m['_yl_total'] ) . ' تومان';
		}
		foreach ( (array) $m['_yl_answers'] as $a ) {
			$out[ $a['q'] ] = $a['a'];
		}
		if ( ! empty( $m['_yl_packs'] ) ) {
			$out['پک‌های پیشنهادشده'] = $m['_yl_packs'];
		}
		return $out;
	}

	private static function notify( $id, $meta ) {
		$s     = YL_Settings::section( 'leads' );
		$lines = self::lines( $meta );

		if ( ! empty( $s['notify'] ) ) {
			$to = ! empty( $s['email'] ) ? $s['email'] : get_option( 'admin_email' );
			$body = '';
			foreach ( $lines as $k => $v ) {
				$body .= $k . ': ' . $v . "\n";
			}
			$body .= "\n" . 'مشاهده در پیشخوان: ' . admin_url( 'post.php?post=' . $id . '&action=edit' );
			wp_mail( $to, 'درخواست جدید لندینگ یلدا: ' . $lines['نوع درخواست'] . ' — ' . $meta['_yl_name'], $body );
		}

		if ( ! empty( $s['webhook'] ) && wp_http_validate_url( $s['webhook'] ) ) {
			wp_remote_post( $s['webhook'], array(
				'timeout'  => 3,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode( array( 'id' => $id, 'fields' => $lines ) ),
			) );
		}
	}

	/* ------------------------------------------------------------------ admin list */

	public static function new_count() {
		$n = get_transient( 'yl_new_leads' );
		if ( false === $n ) {
			$q = new WP_Query( array(
				'post_type'      => self::TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_yl_status', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => 'new', // phpcs:ignore WordPress.DB.SlowDBQuery
			) );
			$n = (int) $q->found_posts;
			set_transient( 'yl_new_leads', $n, HOUR_IN_SECONDS );
		}
		return (int) $n;
	}

	public static function columns( $cols ) {
		return array(
			'cb'        => $cols['cb'],
			'title'     => 'نام و سازمان',
			'yl_type'   => 'نوع',
			'yl_tel'    => 'موبایل',
			'yl_detail' => 'جزئیات',
			'yl_status' => 'وضعیت',
			'date'      => 'تاریخ',
		);
	}

	public static function column( $col, $id ) {
		$m = static function ( $k ) use ( $id ) {
			return get_post_meta( $id, $k, true );
		};
		switch ( $col ) {
			case 'yl_type':
				$t = self::types();
				$k = $m( '_yl_type' );
				echo '<span class="yl-lt yl-lt-' . esc_attr( $k ) . '">' . esc_html( isset( $t[ $k ] ) ? $t[ $k ] : $k ) . '</span>';
				break;
			case 'yl_tel':
				$tel = $m( '_yl_tel' );
				echo '<a href="tel:' . esc_attr( $tel ) . '" dir="ltr">' . esc_html( $tel ) . '</a>';
				break;
			case 'yl_detail':
				$bits = array();
				if ( $m( '_yl_pack' ) ) {
					$bits[] = esc_html( $m( '_yl_pack' ) );
				}
				if ( $m( '_yl_qty' ) ) {
					$bits[] = esc_html( yl_fa( $m( '_yl_qty' ) ) . ' عدد' );
				}
				if ( $m( '_yl_total' ) ) {
					$bits[] = '<b>' . esc_html( yl_money( $m( '_yl_total' ) ) ) . ' تومان</b>';
				}
				foreach ( (array) $m( '_yl_answers' ) as $a ) {
					$bits[] = esc_html( $a['a'] );
				}
				echo wp_kses( implode( ' · ', $bits ), array( 'b' => array() ) );
				break;
			case 'yl_status':
				$s = self::statuses();
				$k = $m( '_yl_status' ) ? $m( '_yl_status' ) : 'new';
				echo '<span class="yl-ls yl-ls-' . esc_attr( $k ) . '">' . esc_html( isset( $s[ $k ] ) ? $s[ $k ] : $k ) . '</span>';
				break;
		}
	}

	public static function row_actions( $actions, $post ) {
		if ( self::TYPE === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );
			if ( isset( $actions['edit'] ) ) {
				$actions['edit'] = '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">مشاهده و تغییر وضعیت</a>';
			}
		}
		return $actions;
	}

	public static function bulk_actions( $actions ) {
		unset( $actions['edit'] );
		return $actions;
	}

	public static function filters( $post_type ) {
		if ( self::TYPE !== $post_type ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$cur_t = isset( $_GET['yl_type'] ) ? sanitize_key( $_GET['yl_type'] ) : '';
		$cur_s = isset( $_GET['yl_status'] ) ? sanitize_key( $_GET['yl_status'] ) : '';
		// phpcs:enable
		echo '<select name="yl_type"><option value="">همه‌ی انواع</option>';
		foreach ( self::types() as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur_t, $k, false ) . '>' . esc_html( $v ) . '</option>';
		}
		echo '</select><select name="yl_status"><option value="">همه‌ی وضعیت‌ها</option>';
		foreach ( self::statuses() as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur_s, $k, false ) . '>' . esc_html( $v ) . '</option>';
		}
		echo '</select>';
	}

	public static function filter_query( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || self::TYPE !== $q->get( 'post_type' ) ) {
			return;
		}
		$mq = array();
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! empty( $_GET['yl_type'] ) ) {
			$mq[] = array( 'key' => '_yl_type', 'value' => sanitize_key( $_GET['yl_type'] ) );
		}
		if ( ! empty( $_GET['yl_status'] ) ) {
			$mq[] = array( 'key' => '_yl_status', 'value' => sanitize_key( $_GET['yl_status'] ) );
		}
		// phpcs:enable
		if ( $mq ) {
			$q->set( 'meta_query', $mq );
		}
	}

	public static function boxes( $post ) {
		remove_meta_box( 'submitdiv', self::TYPE, 'side' );
		add_meta_box( 'yl_lead_detail', 'جزئیات درخواست', array( __CLASS__, 'box_detail' ), self::TYPE, 'normal', 'high' );
		add_meta_box( 'yl_lead_status', 'وضعیت پیگیری', array( __CLASS__, 'box_status' ), self::TYPE, 'side', 'high' );
	}

	private static function meta_of( $id ) {
		$keys = array( '_yl_type', '_yl_name', '_yl_tel', '_yl_co', '_yl_answers', '_yl_pack', '_yl_packs', '_yl_qty', '_yl_unit', '_yl_total', '_yl_off', '_yl_status' );
		$m    = array();
		foreach ( $keys as $k ) {
			$m[ $k ] = get_post_meta( $id, $k, true );
		}
		if ( ! isset( self::types()[ $m['_yl_type'] ] ) ) {
			$m['_yl_type'] = 'builder';
		}
		return $m;
	}

	public static function box_detail( $post ) {
		echo '<table class="widefat striped yl-lead-table"><tbody>';
		foreach ( self::lines( self::meta_of( $post->ID ) ) as $k => $v ) {
			echo '<tr><th>' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
		}
		echo '<tr><th>زمان ثبت</th><td>' . esc_html( get_the_date( 'Y/m/d H:i', $post ) ) . '</td></tr>';
		echo '</tbody></table>';
	}

	public static function box_status( $post ) {
		wp_nonce_field( 'yl_lead_status', 'yl_lead_nonce' );
		$cur = get_post_meta( $post->ID, '_yl_status', true );
		echo '<select name="yl_status" style="width:100%">';
		foreach ( self::statuses() as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $v ) . '</option>';
		}
		echo '</select><p><button type="submit" class="button button-primary" style="width:100%">ذخیره‌ی وضعیت</button></p>';
		echo '<p><a class="submitdelete" style="color:#b32d2e" href="' . esc_url( get_delete_post_link( $post->ID ) ) . '">انتقال به زباله‌دان</a></p>';
	}

	public static function save_status( $id ) {
		if ( ! isset( $_POST['yl_lead_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yl_lead_nonce'] ) ), 'yl_lead_status' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		$s = isset( $_POST['yl_status'] ) ? sanitize_key( $_POST['yl_status'] ) : 'new';
		if ( isset( self::statuses()[ $s ] ) ) {
			update_post_meta( $id, '_yl_status', $s );
			delete_transient( 'yl_new_leads' );
		}
	}

	public static function export() {
		if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'yl_export' ) ) {
			wp_die( 'دسترسی ندارید.' );
		}
		$ids = get_posts( array(
			'post_type'      => self::TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=yalda-leads-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'تاریخ', 'نوع', 'نام', 'موبایل', 'سازمان', 'پک', 'تعداد', 'قیمت هر پک', 'تخفیف تعداد', 'مبلغ نهایی', 'پاسخ‌ها', 'پک‌های پیشنهادشده', 'وضعیت' ) );
		$types = self::types();
		$st    = self::statuses();
		foreach ( $ids as $id ) {
			$m   = self::meta_of( $id );
			$ans = array();
			foreach ( (array) $m['_yl_answers'] as $a ) {
				$ans[] = $a['q'] . ' ' . $a['a'];
			}
			fputcsv( $out, array(
				get_the_date( 'Y-m-d H:i', $id ),
				$types[ $m['_yl_type'] ],
				$m['_yl_name'],
				$m['_yl_tel'],
				$m['_yl_co'],
				$m['_yl_pack'],
				$m['_yl_qty'],
				$m['_yl_unit'],
				$m['_yl_off'],
				$m['_yl_total'],
				implode( ' | ', $ans ),
				$m['_yl_packs'],
				isset( $st[ $m['_yl_status'] ] ) ? $st[ $m['_yl_status'] ] : $m['_yl_status'],
			) );
		}
		fclose( $out );
		exit;
	}
}
