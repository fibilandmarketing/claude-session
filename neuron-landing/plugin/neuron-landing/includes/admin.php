<?php
/**
 * Admin panel: one tab per section of the page, every text / image / link of
 * the design editable, plus list editors and global settings.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_menu_page( 'لندینگ نورون', 'لندینگ نورون', 'manage_options', 'neuron-landing', 'nrn_admin_page', 'dashicons-location-alt', 3 );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'toplevel_page_neuron-landing' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	$ver = NRN_VERSION . '.' . filemtime( NRN_DIR . 'assets/js/admin.js' );
	wp_enqueue_style( 'nrn-admin', NRN_URL . 'assets/css/admin.css', array(), $ver );
	wp_enqueue_script( 'nrn-admin', NRN_URL . 'assets/js/admin.js', array( 'jquery' ), $ver, true );
	wp_localize_script( 'nrn-admin', 'NRN_ADMIN', array(
		'pick'   => 'انتخاب تصویر',
		'use'    => 'استفاده از این تصویر',
		'sure'   => 'همهٔ مقادیر این بخش به حالت طرح اصلی برمی‌گردد. ادامه می‌دهید؟',
		'sureAll'=> 'همهٔ تغییرات کل صفحه پاک می‌شود و صفحه دقیقاً مثل طرح اصلی می‌شود. ادامه می‌دهید؟',
		'leave'  => 'تغییرات ذخیره نشده‌اند.',
	) );
} );

/* ------------------------------------------------------------------ save */

add_action( 'admin_post_nrn_save', 'nrn_admin_save' );
function nrn_admin_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.', 403 );
	}
	check_admin_referer( 'nrn_save' );
	$tab = isset( $_POST['nrn_tab'] ) ? sanitize_key( wp_unslash( $_POST['nrn_tab'] ) ) : 'settings';

	// Import of a backup file replaces everything.
	if ( ! empty( $_FILES['nrn_import']['tmp_name'] ) && is_uploaded_file( $_FILES['nrn_import']['tmp_name'] ) ) { // phpcs:ignore
		$json = json_decode( (string) file_get_contents( $_FILES['nrn_import']['tmp_name'] ), true ); // phpcs:ignore
		if ( is_array( $json ) && isset( $json['neuron_landing'] ) && is_array( $json['neuron_landing'] ) ) {
			update_option( NRN_OPTION, nrn_sanitize_all( $json['neuron_landing'] ), false );
			nrn_after_save();
			wp_safe_redirect( admin_url( 'admin.php?page=neuron-landing&tab=settings&nrn_msg=imported' ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=neuron-landing&tab=settings&nrn_msg=badfile' ) );
		exit;
	}

	if ( ! empty( $_POST['nrn_cache_clear'] ) ) {
		nrn_cache_purge();
		wp_safe_redirect( admin_url( 'admin.php?page=neuron-landing&tab=settings&nrn_msg=cache' ) );
		exit;
	}

	if ( ! empty( $_POST['nrn_reset_all'] ) ) {
		update_option( NRN_OPTION, array( 'settings' => NRN_Content::get()->data['settings'] ?? array() ), false );
		nrn_after_save();
		wp_safe_redirect( admin_url( 'admin.php?page=neuron-landing&tab=settings&nrn_msg=reset' ) );
		exit;
	}

	$payload = isset( $_POST['nrn_payload'] ) ? json_decode( wp_unslash( $_POST['nrn_payload'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised field by field below
	if ( ! is_array( $payload ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=neuron-landing&tab=' . $tab . '&nrn_msg=nodata' ) );
		exit;
	}
	update_option( NRN_OPTION, nrn_sanitize_all( $payload ), false );
	nrn_after_save();
	wp_safe_redirect( admin_url( 'admin.php?page=neuron-landing&tab=' . $tab . '&nrn_msg=saved' ) );
	exit;
}

function nrn_after_save() {
	NRN_Content::reset_cache();
	nrn_bump_rev();
	nrn_cache_purge();
	// purge the common page caches so visitors see the change right away
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
	}
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all();
	}
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache();
	}
	do_action( 'litespeed_purge_all' );
	if ( class_exists( 'autoptimizeCache' ) ) {
		autoptimizeCache::clearall();
	}
	wp_cache_flush();
}

/**
 * Keeps only values that differ from the design, sanitised by type, so the
 * stored option stays small and design updates still flow through.
 */
function nrn_sanitize_all( array $in ) {
	$c   = NRN_Content::get();
	$out = array();

	// fields
	if ( isset( $in['fields'] ) && is_array( $in['fields'] ) ) {
		foreach ( $in['fields'] as $key => $val ) {
			$key = (string) $key;
			$def = $c->field_def( $key );
			if ( ! $def ) {
				continue;
			}
			if ( 'img' === $def['type'] ) {
				$v = array(
					'url' => isset( $val['url'] ) ? esc_url_raw( $val['url'] ) : '',
					'alt' => isset( $val['alt'] ) ? sanitize_text_field( $val['alt'] ) : '',
					'id'  => isset( $val['id'] ) ? absint( $val['id'] ) : 0,
				);
				$dalt = isset( $def['alt'] ) ? $def['alt'] : '';
				if ( '' !== $v['url'] || $v['alt'] !== $dalt ) {
					$out['fields'][ $key ] = $v;
				}
				continue;
			}
			$val = is_string( $val ) ? str_replace( "\r", '', $val ) : '';
			if ( 'url' === $def['type'] ) {
				$val = nrn_clean_url( $val );
				// unchanged design link: keep each layout's own (desktop / mobile anchors differ)
				if ( in_array( $val, array_values( (array) ( $def['defaults'] ?? array() ) ), true ) ) {
					continue;
				}
			} else {
				$val = sanitize_textarea_field( $val );
			}
			if ( $val !== $def['default'] ) {
				$out['fields'][ $key ] = $val;
			}
		}
	}

	// section switches
	if ( isset( $in['sections'] ) && is_array( $in['sections'] ) ) {
		foreach ( NRN_Content::groups() as $gid => $g ) {
			if ( ! empty( $g['toggle'] ) && isset( $in['sections'][ $gid ] ) && ! $in['sections'][ $gid ] ) {
				$out['sections'][ $gid ] = 0;
			}
		}
	}

	// lists
	$list_fields = array(
		'faq'    => array( 'q' => 'text', 'a' => 'textarea', 'open' => 'bool' ),
		'orgs'   => array( 'name' => 'text', 'logo' => 'url', 'alt' => 'text' ),
		'videos' => array( 'label' => 'text', 'cover' => 'url', 'alt' => 'text', 'title' => 'text', 'sub' => 'text', 'aria' => 'text', 'video' => 'url' ),
		'badges' => array( 'label' => 'text', 'img' => 'url', 'alt' => 'text', 'link' => 'url', 'code' => 'code' ),
		'bnav'   => array( 'label' => 'text', 'icon' => 'text', 'url' => 'url', 'main' => 'bool' ),
	);
	foreach ( $list_fields as $list => $spec ) {
		if ( ! isset( $in[ $list ] ) || ! is_array( $in[ $list ] ) ) {
			continue;
		}
		$items = array();
		foreach ( $in[ $list ] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$items[] = nrn_clean_row( $row, $spec );
		}
		if ( 'bnav' === $list ) {
			foreach ( $items as &$it ) {
				$it['icon'] = isset( nrn_bnav_icons()[ $it['icon'] ] ) ? $it['icon'] : 'home';
			}
			unset( $it );
		}
		if ( $items !== $c->lists[ $list ] ) {
			$out[ $list ] = $items;
		}
	}

	// magazine
	if ( isset( $in['mag'] ) && is_array( $in['mag'] ) ) {
		$m   = $in['mag'];
		$mag = array(
			'source' => in_array( $m['source'] ?? '', array( 'latest', 'category', 'manual' ), true ) ? $m['source'] : 'latest',
			'cat'    => absint( $m['cat'] ?? 0 ),
			'more'   => sanitize_text_field( $m['more'] ?? '' ),
			'items'  => array(),
		);
		foreach ( (array) ( $m['items'] ?? array() ) as $row ) {
			if ( is_array( $row ) ) {
				$mag['items'][] = nrn_clean_row( $row, array( 'label' => 'text', 'img' => 'url', 'alt' => 'text', 'phb' => 'text', 'title' => 'text', 'url' => 'url' ) );
			}
		}
		if ( $mag !== $c->lists['mag'] ) {
			$out['mag'] = $mag;
		}
	}

	// provinces
	if ( isset( $in['provinces'] ) && is_array( $in['provinces'] ) ) {
		$names = array();
		foreach ( $c->lists['provinces'] as $p ) {
			$names[ $p['slug'] ] = $p['name'];
		}
		$by = array();
		foreach ( $in['provinces'] as $row ) {
			$slug = sanitize_key( $row['slug'] ?? '' );
			if ( ! isset( $names[ $slug ] ) ) {
				continue;
			}
			$by[ $slug ] = array(
				'slug' => $slug,
				'name' => '' !== trim( (string) ( $row['name'] ?? '' ) ) ? sanitize_text_field( $row['name'] ) : $names[ $slug ],
				'n'    => max( 0, (int) ( $row['n'] ?? 0 ) ),
				'url'  => nrn_clean_url( (string) ( $row['url'] ?? '' ) ),
			);
		}
		// keep the design's order so an untouched table is recognised as default
		$provs = array();
		foreach ( $c->lists['provinces'] as $p ) {
			$provs[] = isset( $by[ $p['slug'] ] ) ? $by[ $p['slug'] ] : $p;
		}
		if ( $provs !== $c->lists['provinces'] ) {
			$out['provinces'] = $provs;
		}
	}

	// map texts
	if ( isset( $in['map'] ) && is_array( $in['map'] ) ) {
		$map = array();
		foreach ( array( 'def', 'url', 'count_suffix', 'none_text', 'cta_has', 'cta_none', 'none_url' ) as $k ) {
			if ( isset( $in['map'][ $k ] ) ) {
				$v = (string) $in['map'][ $k ];
				$map[ $k ] = in_array( $k, array( 'url', 'none_url' ), true ) ? nrn_clean_url( $v ) : ( 'def' === $k ? sanitize_key( $v ) : sanitize_text_field( $v ) );
			}
		}
		$out['map'] = $map;
	}

	// settings
	// all-products carousel
	if ( isset( $in['products'] ) && is_array( $in['products'] ) ) {
		$pi   = $in['products'];
		$prod = array(
			'source'   => 'manual' === ( $pi['source'] ?? '' ) ? 'manual' : 'woo',
			'auto_new' => ! empty( $pi['auto_new'] ),
			'limit'    => max( 0, min( 60, (int) ( $pi['limit'] ?? 12 ) ) ),
			'woo'      => array(),
			'items'    => array(),
		);
		foreach ( (array) ( $pi['woo'] ?? array() ) as $r ) {
			if ( ! empty( $r['id'] ) ) {
				$prod['woo'][] = array(
					'id'   => absint( $r['id'] ),
					'show' => ! empty( $r['show'] ),
					'tag'  => sanitize_text_field( (string) ( $r['tag'] ?? '' ) ),
					'fit'  => 'cover' === ( $r['fit'] ?? '' ) ? 'cover' : 'product',
				);
			}
		}
		foreach ( (array) ( $pi['items'] ?? array() ) as $row ) {
			if ( is_array( $row ) ) {
				$r          = nrn_clean_row( $row, array( 'name' => 'text', 'tag' => 'text', 'price' => 'text', 'unit' => 'text', 'img' => 'url', 'alt' => 'text', 'fit' => 'text', 'phb' => 'text', 'url' => 'url' ) );
				$r['fit']   = 'cover' === $r['fit'] ? 'cover' : 'product';
				$prod['items'][] = $r;
			}
		}
		if ( $prod !== $c->lists['products'] ) {
			$out['products'] = $prod;
		}
	}

	$out['v']  = 2;
	$out['sv'] = ( require NRN_DIR . 'includes/migrate-keys.php' )['version'];
	$s = isset( $in['settings'] ) && is_array( $in['settings'] ) ? $in['settings'] : array();
	$out['settings'] = array(
		'enabled'    => empty( $s['enabled'] ) ? 0 : 1,
		'strip'      => in_array( $s['strip'] ?? 'theme', array( 'theme', 'all', 'none' ), true ) ? $s['strip'] : 'theme',
		'hide_tbd'   => empty( $s['hide_tbd'] ) ? 0 : 1,
		'hide_phb'   => empty( $s['hide_phb'] ) ? 0 : 1,
		'breakpoint' => max( 320, min( 1400, (int) ( $s['breakpoint'] ?? 768 ) ) ),
		'scale_from' => max( 0, min( 1600, (int) ( $s['scale_from'] ?? 1280 ) ) ),
		'cache'      => empty( $s['cache'] ) ? 0 : 1,
		'cache_ttl'  => max( 1, min( 168, (int) ( $s['cache_ttl'] ?? 10 ) ) ),
	);
	return $out;
}

function nrn_clean_url( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v || '#' === $v[0] ) {
		return sanitize_text_field( $v ); // in-page anchors
	}
	return esc_url_raw( $v, array( 'http', 'https', 'tel', 'mailto', 'sms', 'whatsapp', 'tg' ) );
}

function nrn_clean_row( array $row, array $spec ) {
	$o = array();
	foreach ( $spec as $k => $type ) {
		$v = isset( $row[ $k ] ) ? $row[ $k ] : '';
		switch ( $type ) {
			case 'bool':
				$o[ $k ] = ! empty( $v );
				break;
			case 'url':
				$v = (string) $v;
				// keep bundled design assets as relative paths
				$o[ $k ] = preg_match( '#^img/[\w.-]+$#', $v ) ? $v : nrn_clean_url( $v );
				break;
			case 'textarea':
				$o[ $k ] = sanitize_textarea_field( (string) $v );
				break;
			case 'select':
				echo '<label>' . esc_html( $label ) . '<select data-f="' . esc_attr( $k ) . '">';
				foreach ( $fs[2] as $ov => $ol ) {
					echo '<option value="' . esc_attr( $ov ) . '"' . selected( (string) $v, (string) $ov, false ) . '>' . esc_html( $ol ) . '</option>';
				}
				echo '</select></label>';
				break;
			case 'code':
				// trust-seal snippets (eNamad, Samandehi) need their own attributes: administrators may paste them as is
				$o[ $k ] = current_user_can( 'unfiltered_html' ) ? trim( (string) $v ) : wp_kses_post( trim( (string) $v ) );
				break;
			default:
				$o[ $k ] = sanitize_text_field( (string) $v );
		}
	}
	return $o;
}

/* ---------------------------------------------------------------- export */

add_action( 'admin_post_nrn_export', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.', 403 );
	}
	check_admin_referer( 'nrn_export' );
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=neuron-landing-' . gmdate( 'Y-m-d' ) . '.json' );
	echo wp_json_encode( array( 'neuron_landing' => get_option( NRN_OPTION, array() ), 'version' => NRN_VERSION ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	exit;
} );

/* ---------------------------------------------------------------- render */

function nrn_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	NRN_Content::reset_cache();
	$c      = NRN_Content::get();
	$groups = NRN_Content::groups();
	$tab    = isset( $_GET['tab'] ) && isset( $groups[ sanitize_key( $_GET['tab'] ) ] ) ? sanitize_key( $_GET['tab'] ) : 'settings'; // phpcs:ignore
	$msg    = isset( $_GET['nrn_msg'] ) ? sanitize_key( $_GET['nrn_msg'] ) : ''; // phpcs:ignore
	$msgs   = array(
		'saved'    => array( 'success', 'تغییرات ذخیره شد.' ),
		'reset'    => array( 'success', 'همهٔ محتوا به طرح اصلی برگشت.' ),
		'imported' => array( 'success', 'پشتیبان بارگذاری شد.' ),
		'badfile'  => array( 'error', 'فایل پشتیبان معتبر نیست.' ),
		'nodata'   => array( 'error', 'اطلاعاتی دریافت نشد؛ دوباره تلاش کنید.' ),
		'cache'    => array( 'success', 'کش صفحه پاک شد و چند ثانیه دیگر دوباره ساخته می‌شود.' ),
	);
	$preview = add_query_arg( 'nrn_preview', '1', home_url( '/' ) );
	?>
	<div class="wrap nrn-admin" dir="rtl">
		<div class="nrn-head">
			<div>
				<h1>لندینگ نورون</h1>
				<p class="nrn-sub">همهٔ متن‌ها، تصاویر و لینک‌های صفحهٔ اصلی. هر فیلد مقدار طرح اصلی را به‌عنوان پیش‌فرض دارد؛ اگر خالی یا دست‌نخورده بماند، همان طرح نمایش داده می‌شود.</p>
			</div>
			<div class="nrn-head-actions">
				<a class="button" href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-visibility"></span> پیش‌نمایش صفحه</a>
				<button type="submit" form="nrn-form" class="button button-primary nrn-save"><span class="dashicons dashicons-saved"></span> ذخیرهٔ تغییرات</button>
			</div>
		</div>
		<?php if ( isset( $msgs[ $msg ] ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $msgs[ $msg ][0] ); ?> is-dismissible"><p><?php echo esc_html( $msgs[ $msg ][1] ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! $c->setting( 'enabled' ) ) : ?>
			<div class="notice notice-warning"><p>لندینگ هنوز روی صفحهٔ اصلی فعال نیست. از تب «تنظیمات کلی» فعالش کنید؛ تا آن موقع فقط مدیران با «پیش‌نمایش صفحه» آن را می‌بینند.</p></div>
		<?php endif; ?>

		<form id="nrn-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="nrn_save">
			<input type="hidden" name="nrn_tab" value="<?php echo esc_attr( $tab ); ?>" id="nrn-tab-input">
			<input type="hidden" name="nrn_payload" id="nrn-payload" value="">
			<?php wp_nonce_field( 'nrn_save' ); ?>
			<div class="nrn-layout">
				<nav class="nrn-tabs" aria-label="بخش‌های صفحه">
					<?php foreach ( $groups as $gid => $g ) : ?>
						<a href="#<?php echo esc_attr( $gid ); ?>" data-tab="<?php echo esc_attr( $gid ); ?>" class="nrn-tab<?php echo $gid === $tab ? ' is-active' : ''; ?><?php echo ( ! empty( $g['toggle'] ) && ! $c->on( $gid ) ) ? ' is-off' : ''; ?>">
							<span class="dashicons dashicons-<?php echo esc_attr( $g['icon'] ); ?>"></span>
							<span class="nrn-tab-t"><?php echo esc_html( $g['title'] ); ?></span>
							<?php if ( ! empty( $g['toggle'] ) ) : ?><span class="nrn-off-dot" title="خاموش"></span><?php endif; ?>
						</a>
					<?php endforeach; ?>
				</nav>
				<div class="nrn-panels">
					<?php
					foreach ( $groups as $gid => $g ) {
						echo '<section class="nrn-panel' . ( $gid === $tab ? ' is-active' : '' ) . '" data-panel="' . esc_attr( $gid ) . '">';
						echo '<div class="nrn-panel-head"><div><h2>' . esc_html( $g['title'] ) . '</h2>';
						if ( ! empty( $g['desc'] ) ) {
							echo '<p class="description">' . esc_html( $g['desc'] ) . '</p>';
						}
						echo '</div>';
						if ( 'settings' !== $gid ) {
							echo '<button type="button" class="button-link nrn-reset-group"><span class="dashicons dashicons-image-rotate"></span> برگرداندن این بخش به طرح اصلی</button>';
						}
						echo '</div>';
						if ( ! empty( $g['toggle'] ) ) {
							nrn_toggle_row( 'sections', $gid, $c->on( $gid ), 'نمایش این بخش در صفحه', 'اگر خاموش شود، این بخش از نسخهٔ دسکتاپ و موبایل حذف می‌شود و مسیر ماشین خودکار با بخش‌های باقی‌مانده تنظیم می‌شود.' );
						}
						if ( 'settings' === $gid ) {
							nrn_render_settings( $c, $preview );
						} else {
							echo '<input type="search" class="nrn-filter" placeholder="جست‌وجو در این بخش…">';
							nrn_render_fields( $gid, $c );
						}
						echo '</section>';
					}
					?>
				</div>
			</div>
		</form>
		<?php nrn_render_list_templates(); ?>
	</div>
	<?php
}

function nrn_toggle_row( $scope, $key, $on, $label, $desc = '' ) {
	?>
	<div class="nrn-card nrn-toggle-card">
		<label class="nrn-switch">
			<input type="checkbox" data-scope="<?php echo esc_attr( $scope ); ?>" data-k="<?php echo esc_attr( $key ); ?>" <?php checked( $on ); ?>>
			<span class="nrn-switch-ui"></span>
			<span><b><?php echo esc_html( $label ); ?></b><?php if ( $desc ) : ?><small><?php echo esc_html( $desc ); ?></small><?php endif; ?></span>
		</label>
	</div>
	<?php
}

function nrn_layout_badge( $layouts ) {
	if ( 'd' === $layouts ) {
		return '<span class="nrn-badge nrn-b-d" title="این مورد فقط در نسخهٔ دسکتاپ هست"><span class="dashicons dashicons-desktop"></span> فقط دسکتاپ</span>';
	}
	if ( 'm' === $layouts ) {
		return '<span class="nrn-badge nrn-b-m" title="این مورد فقط در نسخهٔ موبایل هست"><span class="dashicons dashicons-smartphone"></span> فقط موبایل</span>';
	}
	return '';
}

/** One row: a text (with its link beside it), a lone link, or an image. */
function nrn_field_row( $key, $f, NRN_Content $c, $gid ) {
	if ( 'url' === $f['type'] && ! empty( $f['attached'] ) ) {
		return; // shown beside its text
	}
	$badge = nrn_layout_badge( $f['layouts'] );
	echo '<div class="nrn-fr nrn-t-' . esc_attr( $f['type'] ) . '">';
	if ( 'img' === $f['type'] ) {
		$raw  = $c->raw( $key );
		$url  = is_array( $raw ) && ! empty( $raw['url'] ) ? $raw['url'] : '';
		$alt  = is_array( $raw ) && isset( $raw['alt'] ) ? $raw['alt'] : ( $f['alt'] ?? '' );
		$id   = is_array( $raw ) && ! empty( $raw['id'] ) ? (int) $raw['id'] : 0;
		$dsrc = NRN_Content::asset( $f['default'] );
		echo '<div class="nrn-fr-label">تصویر ' . $badge . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="nrn-img nrn-imgf" data-key="' . esc_attr( $key ) . '" data-default-src="' . esc_url( $dsrc ) . '" data-default-alt="' . esc_attr( $f['alt'] ?? '' ) . '">';
		echo '<img class="nrn-img-prev" src="' . esc_url( $url ? $url : $dsrc ) . '" alt="">';
		echo '<div class="nrn-img-ctl"><input type="hidden" class="nrn-v-url" value="' . esc_attr( $url ) . '"><input type="hidden" class="nrn-v-id" value="' . esc_attr( $id ) . '">';
		echo '<button type="button" class="button nrn-pick">تغییر تصویر</button>';
		echo '<input type="text" class="nrn-v-alt" value="' . esc_attr( $alt ) . '" placeholder="متن جایگزین (برای گوگل و نابینایان)" title="متن جایگزین تصویر"></div></div>';
	} else {
		$label = $f['role'];
		if ( 'url' === $f['type'] ) {
			$label = 'لینک' . ( ! empty( $f['label'] ) ? ' «' . mb_substr( $f['label'], 0, 40 ) . '»' : '' );
		}
		echo '<div class="nrn-fr-label">' . esc_html( $label ) . ' ' . $badge . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="nrn-fr-in">';
		nrn_input( $key, $f, $c );
		if ( ! empty( $f['link'] ) ) {
			$lk = $f['link'];
			$lf = $c->field_def( $lk );
			if ( $lf ) {
				echo '<span class="nrn-link-in" title="لینک این دکمه"><span class="dashicons dashicons-admin-links"></span>';
				nrn_input( $lk, $lf, $c );
				echo '</span>';
			}
		}
		echo '</div>';
		if ( ! empty( $f['tags'] ) ) {
			echo '<small class="nrn-tip">بخشی که داخل [ ] است با رنگ یا وزن متفاوت نمایش داده می‌شود.</small>';
		}
	}
	echo '<button type="button" class="nrn-reset-field" title="برگرداندن به طرح اصلی"><span class="dashicons dashicons-image-rotate"></span></button>';
	echo '</div>';
}

function nrn_input( $key, $f, NRN_Content $c ) {
	if ( 'url' === $f['type'] ) {
		$val = $c->link( $key, false !== strpos( $f['layouts'], 'd' ) ? 'd' : 'm' );
		$def = isset( $f['defaults']['d'] ) ? $f['defaults']['d'] : $f['default'];
		echo '<input type="text" class="nrn-in nrn-url" dir="ltr" data-key="' . esc_attr( $key ) . '" data-default="' . esc_attr( $def ) . '" value="' . esc_attr( $val ) . '" placeholder="https://… یا #بخش یا tel:">';
		return;
	}
	$val = $c->text( $key );
	if ( ! empty( $f['long'] ) || false !== strpos( $val, "\n" ) ) {
		$rows = max( 2, min( 6, (int) ceil( mb_strlen( $val ) / 70 ) + substr_count( $val, "\n" ) ) );
		echo '<textarea class="nrn-in" dir="auto" rows="' . (int) $rows . '" data-key="' . esc_attr( $key ) . '" data-default="' . esc_attr( $f['default'] ) . '">' . esc_textarea( $val ) . '</textarea>';
	} else {
		echo '<input type="text" class="nrn-in" dir="auto" data-key="' . esc_attr( $key ) . '" data-default="' . esc_attr( $f['default'] ) . '" value="' . esc_attr( $val ) . '">';
	}
}

/** A section: its intro fields, then one box per card, then the design notes (closed). */
function nrn_render_fields( $gid, NRN_Content $c ) {
	$fields = $c->fields_of( $gid );
	if ( ! $fields ) {
		nrn_render_lists_for( $gid, $c );
		return;
	}
	$cards = $c->cards_of( $gid );
	$by    = array( '' => array(), '_notes' => array() );
	foreach ( array_keys( $cards ) as $ck ) {
		$by[ $ck ] = array();
	}
	foreach ( $fields as $key => $f ) {
		$ck = isset( $f['card'] ) ? $f['card'] : '';
		if ( ! isset( $by[ $ck ] ) ) {
			$ck = '';
		}
		$by[ $ck ][ $key ] = $f;
	}
	// a "card" with a single item (a chip, a badge) reads better inside the section's main box
	$visible = function ( $list ) {
		return array_filter( $list, function ( $f ) {
			return ! ( 'url' === $f['type'] && ! empty( $f['attached'] ) );
		} );
	};
	foreach ( $cards as $ck => $title ) {
		if ( count( $visible( $by[ $ck ] ) ) <= 1 ) {
			$by[''] = array_merge( $by[''], $by[ $ck ] );
			unset( $cards[ $ck ] );
		}
	}
	$box = function ( $title, $list, $open, $cls = '' ) use ( $c, $gid ) {
		$list = array_filter( $list, function ( $f ) {
			return ! ( 'url' === $f['type'] && ! empty( $f['attached'] ) );
		} );
		if ( ! $list ) {
			return;
		}
		echo '<details class="nrn-box ' . esc_attr( $cls ) . '"' . ( $open ? ' open' : '' ) . '><summary><span class="nrn-box-t">' . esc_html( $title ) . '</span><span class="nrn-box-n">' . count( $list ) . ' مورد</span></summary><div class="nrn-box-body">';
		foreach ( $list as $key => $f ) {
			nrn_field_row( $key, $f, $c, $gid );
		}
		echo '</div></details>';
	};
	$box( $cards ? 'تیتر و متن بالای بخش' : 'محتوای این بخش', $by[''], true, 'nrn-box-intro' );
	nrn_render_lists_for( $gid, $c );
	foreach ( $cards as $ck => $title ) {
		$box( 'کارت' === $title ? 'تصویر و نمادهای بخش' : 'کارت: ' . $title, $by[ $ck ], false );
	}
	$box( 'برچسب‌های «جایگاه» و نشان‌های «نیازمند تأیید» طرح', $by['_notes'], false, 'nrn-box-notes' );
}

/* ------------------------------------------------------------ list editors */

function nrn_list_spec( $list ) {
	$specs = array(
		'faq'    => array(
			'title'  => 'سؤال‌ها',
			'add'    => 'افزودن سؤال',
			'fields' => array(
				'q'    => array( 'text', 'سؤال' ),
				'a'    => array( 'textarea', 'پاسخ' ),
				'open' => array( 'bool', 'هنگام بارگذاری باز باشد' ),
			),
		),
		'orgs'   => array(
			'title'  => 'لوگوهای «مورد اعتماد سازمان‌ها» (نوار متحرک)',
			'add'    => 'افزودن سازمان',
			'desc'   => 'اگر لوگو خالی باشد، نشان ستاره (★) مثل «نشان دانش‌بنیان» نمایش داده می‌شود.',
			'fields' => array(
				'name' => array( 'text', 'نام' ),
				'logo' => array( 'img', 'لوگو' ),
				'alt'  => array( 'text', 'متن جایگزین لوگو' ),
			),
		),
		'videos' => array(
			'title'  => 'کارت‌های ویدئو',
			'add'    => 'افزودن ویدئو',
			'desc'   => 'لینک ویدئو می‌تواند فایل mp4، لینک آپارات یا یوتیوب باشد؛ با کلیک روی کارت، ویدئو در پنجرهٔ روی صفحه پخش می‌شود.',
			'fields' => array(
				'cover' => array( 'img', 'کاور' ),
				'alt'   => array( 'text', 'متن جایگزین کاور' ),
				'label' => array( 'text', 'برچسب جایگاه (روی کاور)' ),
				'title' => array( 'text', 'عنوان' ),
				'sub'   => array( 'text', 'توضیح زیر عنوان (فقط دسکتاپ)' ),
				'video' => array( 'url', 'لینک ویدئو' ),
				'aria'  => array( 'text', 'برچسب دسترس‌پذیری دکمهٔ پخش' ),
			),
		),
		'bnav'   => array(
			'title'  => 'دکمه‌های نوار',
			'add'    => 'افزودن دکمه',
			'desc'   => 'بهتر است ۴ یا ۵ دکمه باشد. برای باز شدن منوی کشویی، آیکن «منو» یا لینک #menu را انتخاب کنید.',
			'fields' => array(
				'label' => array( 'text', 'عنوان' ),
				'icon'  => array( 'select', 'آیکن', array_map( function ( $i ) { return $i[0]; }, nrn_bnav_icons() ) ),
				'url'   => array( 'url', 'لینک' ),
				'main'  => array( 'bool', 'دکمهٔ اصلی (دایرهٔ نارنجی وسط)' ),
			),
		),
		'products' => array(
			'title'  => 'کارت‌های دستی محصولات',
			'add'    => 'افزودن کارت',
			'desc'   => 'وقتی منبع روی «کارت‌های دستی» باشد یا ووکامرس فعال نباشد، این کارت‌ها نمایش داده می‌شوند.',
			'fields' => array(
				'name'  => array( 'text', 'نام محصول' ),
				'tag'   => array( 'text', 'برچسب (دسته)' ),
				'price' => array( 'text', 'قیمت' ),
				'unit'  => array( 'text', 'واحد قیمت' ),
				'img'   => array( 'img', 'تصویر' ),
				'alt'   => array( 'text', 'متن جایگزین تصویر' ),
				'fit'   => array( 'select', 'نوع تصویر', array( 'product' => 'عکس محصول (زمینهٔ سفید، وسط کادر)', 'cover' => 'عکس تمام‌کادر' ) ),
				'phb'   => array( 'text', 'برچسب جایگاه روی تصویر' ),
				'url'   => array( 'url', 'لینک' ),
			),
		),
		'badges' => array(
			'title'  => 'نمادهای اعتماد فوتر (اینماد، ساماندهی، دانش‌بنیان)',
			'add'    => 'افزودن نماد',
			'desc'   => 'برای اینماد و ساماندهی، کد HTML که سایت آن‌ها می‌دهد را در «کد نماد» بچسبانید؛ در این صورت تصویر و برچسب نادیده گرفته می‌شود. بدون کد، تصویر (با لینک دلخواه) نمایش داده می‌شود. برچسب را خالی کنید تا روی کادر متنی نباشد.',
			'fields' => array(
				'label' => array( 'text', 'برچسب روی کادر' ),
				'img'   => array( 'img', 'تصویر' ),
				'alt'   => array( 'text', 'متن جایگزین تصویر' ),
				'link'  => array( 'url', 'لینک (اختیاری)' ),
				'code'  => array( 'code', 'کد نماد (HTML)' ),
			),
		),
		'mag'    => array(
			'title'  => 'کارت‌های دستی (برای حالت «دستی» یا وقتی نوشته‌ای پیدا نشود)',
			'add'    => 'افزودن کارت',
			'fields' => array(
				'img'   => array( 'img', 'تصویر' ),
				'alt'   => array( 'text', 'متن جایگزین' ),
				'phb'   => array( 'text', 'برچسب جایگاه (روی تصویر، دسکتاپ)' ),
				'label' => array( 'text', 'دسته' ),
				'title' => array( 'text', 'عنوان' ),
				'url'   => array( 'url', 'لینک' ),
			),
		),
	);
	return $specs[ $list ];
}

function nrn_render_lists_for( $gid, NRN_Content $c ) {
	switch ( $gid ) {
		case 'why':
			nrn_render_list( 'orgs', $c->items( 'orgs' ) );
			break;
		case 'videos':
			nrn_render_list( 'videos', $c->items( 'videos' ) );
			break;
		case 'faq':
			nrn_render_list( 'faq', $c->items( 'faq' ) );
			break;
		case 'mag':
			nrn_render_mag( $c );
			break;
		case 'footer':
			nrn_render_list( 'badges', $c->items( 'badges' ) );
			break;
		case 'products':
			nrn_render_products( $c );
			break;
		case 'bnav':
			nrn_render_list( 'bnav', $c->items( 'bnav' ) );
			break;
		case 'city':
			nrn_render_map( $c );
			break;
	}
}

function nrn_render_list( $list, $items, $wrap = true ) {
	$spec = nrn_list_spec( $list );
	if ( $wrap ) {
		echo '<div class="nrn-card"><div class="nrn-card-head"><h3>' . esc_html( $spec['title'] ) . '</h3></div>';
		if ( ! empty( $spec['desc'] ) ) {
			echo '<p class="description">' . esc_html( $spec['desc'] ) . '</p>';
		}
	}
	echo '<div class="nrn-list" data-list="' . esc_attr( $list ) . '">';
	foreach ( $items as $it ) {
		nrn_render_row( $list, $spec, $it );
	}
	echo '</div><button type="button" class="button nrn-add" data-list="' . esc_attr( $list ) . '"><span class="dashicons dashicons-plus-alt2"></span> ' . esc_html( $spec['add'] ) . '</button>';
	if ( $wrap ) {
		echo '</div>';
	}
}

function nrn_render_row( $list, $spec, $it ) {
	echo '<div class="nrn-row"><div class="nrn-row-bar"><span class="nrn-row-n"></span><span class="nrn-row-title"></span>';
	echo '<span class="nrn-row-btns"><button type="button" class="nrn-up" title="بالا"><span class="dashicons dashicons-arrow-up-alt2"></span></button><button type="button" class="nrn-down" title="پایین"><span class="dashicons dashicons-arrow-down-alt2"></span></button><button type="button" class="nrn-del" title="حذف"><span class="dashicons dashicons-trash"></span></button></span></div><div class="nrn-row-body">';
	foreach ( $spec['fields'] as $k => $fs ) {
		$type  = $fs[0];
		$label = $fs[1];
		$v = isset( $it[ $k ] ) ? $it[ $k ] : '';
		echo '<div class="nrn-rf nrn-rf-' . esc_attr( $type ) . '">';
		switch ( $type ) {
			case 'bool':
				echo '<label class="nrn-check"><input type="checkbox" data-f="' . esc_attr( $k ) . '"' . checked( ! empty( $v ), true, false ) . '> ' . esc_html( $label ) . '</label>';
				break;
			case 'textarea':
				echo '<label>' . esc_html( $label ) . '<textarea data-f="' . esc_attr( $k ) . '" rows="3" dir="auto">' . esc_textarea( $v ) . '</textarea></label>';
				break;
			case 'code':
				echo '<label>' . esc_html( $label ) . '<textarea data-f="' . esc_attr( $k ) . '" rows="3" dir="ltr" class="code" placeholder="&lt;a href=&quot;https://trustseal.enamad.ir/...&quot;&gt;&lt;img ...&gt;&lt;/a&gt;">' . esc_textarea( $v ) . '</textarea></label>';
				break;
			case 'img':
				$src = $v ? NRN_Content::asset( $v ) : '';
				echo '<label>' . esc_html( $label ) . '</label><div class="nrn-img nrn-img-row"><img class="nrn-img-prev" src="' . esc_url( $src ) . '" alt=""' . ( $src ? '' : ' hidden' ) . '><input type="hidden" data-f="' . esc_attr( $k ) . '" class="nrn-v-url" value="' . esc_attr( $v ) . '"><button type="button" class="button nrn-pick">انتخاب تصویر</button><button type="button" class="button-link nrn-clear-img">حذف تصویر</button></div>';
				break;
			case 'url':
				echo '<label>' . esc_html( $label ) . '<input type="text" dir="ltr" data-f="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '"></label>';
				break;
			default:
				echo '<label>' . esc_html( $label ) . '<input type="text" dir="auto" data-f="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '"></label>';
		}
		echo '</div>';
	}
	echo '</div></div>';
}

function nrn_render_list_templates() {
	foreach ( array( 'faq', 'orgs', 'videos', 'mag', 'badges', 'products', 'bnav' ) as $list ) {
		echo '<script type="text/template" id="nrn-tpl-' . esc_attr( $list ) . '">';
		ob_start();
		nrn_render_row( $list, nrn_list_spec( $list ), array() );
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
		echo '</script>';
	}
	$c = NRN_Content::get();
	echo '<script type="application/json" id="nrn-defaults">' . wp_json_encode( array(
		'faq'       => $c->lists['faq'],
		'orgs'      => $c->lists['orgs'],
		'videos'    => $c->lists['videos'],
		'badges'    => $c->lists['badges'],
		'products'  => $c->lists['products'],
		'bnav'      => $c->lists['bnav'],
		'mag'       => $c->lists['mag'],
		'provinces' => $c->lists['provinces'],
		'assetBase' => NRN_URL . 'assets/',
	) ) . '</script>';
}

function nrn_render_products( NRN_Content $c ) {
	$cfg  = $c->products_cfg();
	$woo  = NRN_Content::woo_active();
	echo '<div class="nrn-card" data-products="1"><div class="nrn-card-head"><h3>محصولات این بخش</h3></div><div class="nrn-grid2">';
	echo '<label>منبع کارت‌ها<select data-prod-k="source">';
	echo '<option value="woo"' . selected( $cfg['source'], 'woo', false ) . '>محصولات ووکامرس (قیمت، تصویر و لینک خودکار)</option>';
	echo '<option value="manual"' . selected( $cfg['source'], 'manual', false ) . '>کارت‌های دستی</option></select></label>';
	echo '<label>حداکثر تعداد کارت<input type="number" min="0" max="60" data-prod-k="limit" value="' . esc_attr( (int) $cfg['limit'] ) . '"><small>صفر یعنی همه.</small></label>';
	echo '</div>';
	if ( ! $woo ) {
		echo '<p class="notice notice-warning inline" style="padding:8px 12px">ووکامرس روی این سایت فعال نیست؛ تا وقتی فعال شود، کارت‌های دستی پایین نمایش داده می‌شوند.</p>';
	} else {
		echo '<label class="nrn-check" style="margin:12px 0"><input type="checkbox" data-prod-k="auto_new"' . checked( ! empty( $cfg['auto_new'] ), true, false ) . '> محصولاتی که بعداً به فروشگاه اضافه می‌شوند خودکار نمایش داده شوند (انتهای فهرست)</label>';
		echo '<p class="description">تیک «نمایش» هر محصول را بزنید یا بردارید و با فلش‌ها ترتیبشان را عوض کنید. قیمت، تصویر شاخص، نام و لینک صفحهٔ محصول مستقیم از ووکامرس خوانده می‌شود؛ برچسب بالای نام اگر خالی بماند، دستهٔ محصول است.</p>';
		$rows = array();
		foreach ( (array) $cfg['woo'] as $r ) {
			$rows[ (int) $r['id'] ] = $r;
		}
		$ids   = NRN_Content::woo_product_ids();
		$order = array_values( array_unique( array_merge( array_values( array_intersect( array_keys( $rows ), $ids ) ), $ids ) ) );
		echo '<div class="nrn-prov-wrap"><table class="widefat striped nrn-woo"><thead><tr><th>نمایش</th><th></th><th>محصول</th><th>قیمت</th><th>برچسب روی کارت</th><th>نوع تصویر</th><th>ترتیب</th></tr></thead><tbody>';
		if ( ! $order ) {
			echo '<tr><td colspan="7">هنوز محصول منتشرشده‌ای در فروشگاه نیست.</td></tr>';
		}
		foreach ( $order as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p ) {
				continue;
			}
			$r    = isset( $rows[ $id ] ) ? $rows[ $id ] : array( 'show' => ! empty( $cfg['auto_new'] ) || ! $rows, 'tag' => '', 'fit' => 'product' );
			$card = NRN_Content::woo_card( $p, $r );
			$thumb = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' ) : '';
			echo '<tr data-id="' . (int) $id . '"><td><input type="checkbox" class="nrn-w-show"' . checked( ! empty( $r['show'] ), true, false ) . ' aria-label="نمایش ' . esc_attr( $p->get_name() ) . '"></td>';
			echo '<td>' . ( $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="" width="44" height="44" style="border-radius:8px;object-fit:cover">' : '' ) . '</td>';
			echo '<td><a href="' . esc_url( get_edit_post_link( $id ) ) . '" target="_blank" rel="noopener">' . esc_html( $p->get_name() ) . '</a>' . ( 'hidden' === $p->get_catalog_visibility() ? ' <small>(در فروشگاه پنهان)</small>' : '' ) . '</td>';
			echo '<td>' . esc_html( trim( $card['price'] . ' ' . $card['unit'] ) ) . '</td>';
			echo '<td><input type="text" class="nrn-w-tag" value="' . esc_attr( $r['tag'] ?? '' ) . '" placeholder="' . esc_attr( $card['tag'] ) . '"></td>';
			echo '<td><select class="nrn-w-fit"><option value="product"' . selected( $r['fit'] ?? 'product', 'product', false ) . '>عکس محصول</option><option value="cover"' . selected( $r['fit'] ?? '', 'cover', false ) . '>تمام‌کادر</option></select></td>';
			echo '<td class="nrn-row-btns"><button type="button" class="nrn-wup" title="بالا"><span class="dashicons dashicons-arrow-up-alt2"></span></button><button type="button" class="nrn-wdown" title="پایین"><span class="dashicons dashicons-arrow-down-alt2"></span></button></td></tr>';
		}
		echo '</tbody></table></div>';
	}
	echo '<hr><h4>' . esc_html( nrn_list_spec( 'products' )['title'] ) . '</h4><p class="description">' . esc_html( nrn_list_spec( 'products' )['desc'] ) . '</p>';
	nrn_render_list( 'products', $cfg['items'], false );
	echo '</div>';
}

function nrn_render_mag( NRN_Content $c ) {
	$m = $c->mag();
	echo '<div class="nrn-card" data-mag="1"><div class="nrn-card-head"><h3>منبع کارت‌های مجله</h3></div><div class="nrn-grid2">';
	echo '<label>نمایش<select data-mag-k="source">';
	foreach ( array( 'latest' => 'آخرین نوشته‌های وبلاگ', 'category' => 'آخرین نوشته‌های یک دسته', 'manual' => 'کارت‌های دستی (پایین)' ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $m['source'], $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></label><label>دسته (برای حالت دسته)';
	wp_dropdown_categories( array( 'show_option_none' => '— انتخاب دسته —', 'option_none_value' => '0', 'hide_empty' => false, 'selected' => (int) $m['cat'], 'name' => '', 'id' => 'nrn-mag-cat', 'class' => 'nrn-mag-cat' ) );
	echo '</label><label>متن «مطالعه» زیر هر کارت (دسکتاپ)<input type="text" data-mag-k="more" value="' . esc_attr( $m['more'] ) . '"></label></div>';
	echo '<p class="description">در حالت نوشته‌ها، سه نوشتهٔ آخر با تصویر شاخص، دسته و عنوانشان نمایش داده می‌شوند. اگر نوشته‌ای تصویر شاخص نداشته باشد، تصویر کارت دستی هم‌ردیف استفاده می‌شود.</p>';
	echo '<hr><h4>' . esc_html( nrn_list_spec( 'mag' )['title'] ) . '</h4>';
	nrn_render_list( 'mag', $m['items'], false );
	echo '</div>';
}

function nrn_render_map( NRN_Content $c ) {
	$cfg = $c->map_cfg();
	echo '<div class="nrn-card" data-map="1"><div class="nrn-card-head"><h3>نقشهٔ نمایندگی‌ها</h3></div>';
	echo '<p class="description">رنگ هر استان خودکار از روی تعداد نمایندگی‌اش ساخته می‌شود (استان با بیشترین نمایندگی، نارنجی‌ترین). ماشین در پایان مسیر روی «استان پیش‌فرض» پارک می‌کند.</p><div class="nrn-grid2">';
	echo '<label>استان پیش‌فرض (انتخاب‌شده و محل پارک ماشین)<select data-map-k="def">';
	foreach ( $c->geo['order'] as $slug ) {
		$p = $c->province( $slug );
		echo '<option value="' . esc_attr( $slug ) . '"' . selected( $cfg['def'], $slug, false ) . '>' . esc_html( $p['name'] ) . '</option>';
	}
	echo '</select></label>';
	$texts = array(
		'url'          => array( 'لینک پیش‌فرض «مشاهدهٔ نمایندگی‌ها»', 'ltr' ),
		'count_suffix' => array( 'پسوند تعداد (مثلاً «۱۳ نمایندگی فعال»)', 'auto' ),
		'none_text'    => array( 'متن استان بدون نمایندگی', 'auto' ),
		'cta_has'      => array( 'متن دکمه برای استان دارای نمایندگی', 'auto' ),
		'cta_none'     => array( 'متن دکمه برای استان بدون نمایندگی', 'auto' ),
		'none_url'     => array( 'لینک دکمه برای استان بدون نمایندگی', 'ltr' ),
	);
	foreach ( $texts as $k => $t ) {
		echo '<label>' . esc_html( $t[0] ) . '<input type="text" dir="' . esc_attr( $t[1] ) . '" data-map-k="' . esc_attr( $k ) . '" value="' . esc_attr( $cfg[ $k ] ) . '"></label>';
	}
	echo '</div><h4>استان‌ها</h4><div class="nrn-prov-wrap"><table class="widefat striped nrn-prov"><thead><tr><th>استان</th><th>تعداد نمایندگی</th><th>لینک اختصاصی (اختیاری)</th></tr></thead><tbody>';
	foreach ( $c->geo['order'] as $slug ) {
		$p = $c->province( $slug );
		echo '<tr data-slug="' . esc_attr( $slug ) . '"><td><input type="text" class="nrn-p-name" value="' . esc_attr( $p['name'] ) . '"></td><td><input type="number" min="0" class="nrn-p-n" value="' . esc_attr( (int) $p['n'] ) . '"></td><td><input type="text" dir="ltr" class="nrn-p-url" placeholder="' . esc_attr( $cfg['url'] ) . '" value="' . esc_attr( $p['url'] ?? '' ) . '"></td></tr>';
	}
	echo '</tbody></table></div></div>';
}

function nrn_render_settings( NRN_Content $c, $preview ) {
	$front = get_option( 'show_on_front' );
	?>
	<div class="nrn-card">
		<label class="nrn-switch">
			<input type="checkbox" data-scope="settings" data-k="enabled" <?php checked( (bool) $c->setting( 'enabled' ) ); ?>>
			<span class="nrn-switch-ui"></span>
			<span><b>نمایش لندینگ به‌عنوان صفحهٔ اصلی سایت</b><small>صفحهٔ اصلی فعلی (<?php echo 'page' === $front ? 'برگهٔ «' . esc_html( get_the_title( (int) get_option( 'page_on_front' ) ) ) . '»' : 'آخرین نوشته‌ها'; ?>) با این طرح جایگزین می‌شود؛ هدر و فوتر قالب روی این صفحه نمایش داده نمی‌شوند. با خاموش کردن، صفحهٔ قبلی برمی‌گردد.</small></span>
		</label>
		<p><a href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener">پیش‌نمایش بدون فعال‌سازی (فقط برای مدیران) ↗</a></p>
	</div>
	<div class="nrn-card nrn-grid2">
		<label>جداسازی از استایل‌های قالب
			<select data-scope="settings" data-k="strip">
				<option value="theme" <?php selected( $c->setting( 'strip' ), 'theme' ); ?>>حذف استایل‌های قالب و وردپرس (پیشنهادی)</option>
				<option value="all" <?php selected( $c->setting( 'strip' ), 'all' ); ?>>حذف همهٔ استایل‌ها به جز این افزونه</option>
				<option value="none" <?php selected( $c->setting( 'strip' ), 'none' ); ?>>هیچ استایلی حذف نشود</option>
			</select>
			<small>برای اینکه صفحه دقیقاً مثل طرح دیده شود، استایل قالب روی این صفحه بارگذاری نمی‌شود. اسکریپت‌ها (آنالیتیکس، چت آنلاین و…) دست نمی‌خورند.</small>
		</label>
		<label>عرض تغییر به نسخهٔ موبایل (پیکسل)
			<input type="number" min="320" max="1400" data-scope="settings" data-k="breakpoint" value="<?php echo esc_attr( (int) $c->setting( 'breakpoint' ) ); ?>">
			<small>در صفحه‌های باریک‌تر از این عدد، طراحی موبایل نمایش داده می‌شود (پیش‌فرض ۷۶۸).</small>
		</label>
		<label>عرض طراحی دسکتاپ برای تبلت (پیکسل)
			<input type="number" min="0" max="1600" data-scope="settings" data-k="scale_from" value="<?php echo esc_attr( (int) $c->setting( 'scale_from' ) ); ?>">
			<small>بین عرض موبایل و این عدد (مثل تبلت‌ها)، طراحی دسکتاپ دقیقاً در همین عرض ساخته و متناسب با صفحه کوچک می‌شود تا چیدمان به هم نریزد. پیش‌فرض ۱۲۸۰؛ صفر یعنی بدون کوچک‌نمایی.</small>
		</label>
	</div>
	<div class="nrn-card">
		<h3>کش صفحه (افزایش سرعت)</h3>
		<label class="nrn-switch">
			<input type="checkbox" data-scope="settings" data-k="cache" <?php checked( (bool) $c->setting( 'cache' ) ); ?>>
			<span class="nrn-switch-ui"></span>
			<span><b>کش صفحهٔ اصلی روشن باشد</b><small>صفحهٔ آماده برای بازدیدکننده‌ها به‌صورت فایل ذخیره و بدون پردازش دوباره فرستاده می‌شود. برای کاربران واردشده و پیش‌نمایش کش استفاده نمی‌شود. با هر ذخیره در این پنل، یا تغییر محصول، قیمت، موجودی، نوشته و دسته، کش خودکار پاک و دوباره ساخته می‌شود.</small></span>
		</label>
		<div class="nrn-grid2" style="margin-top:12px">
			<label>مدت اعتبار کش (ساعت)
				<input type="number" min="1" max="168" data-scope="settings" data-k="cache_ttl" value="<?php echo esc_attr( (int) $c->setting( 'cache_ttl' ) ); ?>">
				<small>بعد از این مدت صفحه دوباره ساخته می‌شود (پیش‌فرض ۱۰).</small>
			</label>
			<div>
				<?php $st = nrn_cache_status(); ?>
				<p style="margin:0 0 8px"><b>وضعیت:</b> <?php echo $st ? esc_html( sprintf( 'ساخته‌شده در %s، حجم %s کیلوبایت', wp_date( 'Y/m/d H:i', $st['time'] ), number_format_i18n( $st['size'] / 1024 ) ) ) : 'هنوز ساخته نشده (با اولین بازدید ساخته می‌شود)'; ?></p>
				<button type="submit" class="button" name="nrn_cache_clear" value="1"><span class="dashicons dashicons-update"></span> پاک کردن کش</button>
			</div>
		</div>
	</div>
	<div class="nrn-card">
		<h3>نشان‌ها و برچسب‌های جایگاه طرح</h3>
		<p class="description">طرح اصلی نشان‌های «نیازمند تأیید» و برچسب‌های «جایگاه: …» روی تصاویر دارد. پیش‌فرض مثل طرح نمایش داده می‌شوند؛ می‌توانید یکجا پنهانشان کنید یا هر کدام را در بخش خودش ویرایش/خالی کنید.</p>
		<label class="nrn-check"><input type="checkbox" data-scope="settings" data-k="hide_tbd" <?php checked( (bool) $c->setting( 'hide_tbd' ) ); ?>> پنهان کردن همهٔ نشان‌های «نیازمند تأیید»</label><br>
		<label class="nrn-check"><input type="checkbox" data-scope="settings" data-k="hide_phb" <?php checked( (bool) $c->setting( 'hide_phb' ) ); ?>> پنهان کردن همهٔ برچسب‌های «جایگاه: …» روی تصاویر</label>
	</div>
	<div class="nrn-card">
		<h3>پشتیبان‌گیری</h3>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nrn_export' ), 'nrn_export' ) ); ?>"><span class="dashicons dashicons-download"></span> دریافت فایل پشتیبان (JSON)</a>
		</p>
		<p class="nrn-import"><label>بارگذاری فایل پشتیبان: <input type="file" name="nrn_import" accept=".json,application/json"></label> <button type="submit" class="button" name="nrn_do_import" value="1">بارگذاری</button></p>
		<hr>
		<p><button type="submit" class="button button-link-delete nrn-reset-all" name="nrn_reset_all" value="1">برگرداندن کل صفحه به طرح اصلی</button></p>
	</div>
	<?php
}
