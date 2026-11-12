/* لندینگ شب یلدا — settings screen. */
(function ($) {
	'use strict';

	var $root = $('.yl-admin');
	if (!$root.length) return;
	var $form = $('#yl-form');
	var FA = '۰۱۲۳۴۵۶۷۸۹';
	var dirty = false, uid = 0;

	function fa(n) { return String(n).replace(/\d/g, function (d) { return FA[d]; }); }
	function en(s) { return String(s || '').replace(/[۰-۹]/g, function (c) { return FA.indexOf(c); }).replace(/[٠-٩]/g, function (c) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(c); }); }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function newId() { return 'n' + Date.now().toString(36) + (uid++); }
	function newKey() { return 'k' + Math.random().toString(36).slice(2, 8); }

	/* ---------------- toast ---------------- */
	function toast(msg, type, extra, undo) {
		var $m = $('<div class="yl-t-i is-' + (type || 'ok') + '"><span>' + esc(msg) + '</span>' + (extra || '') + '</div>');
		if (undo) {
			$('<button type="button" class="yl-link">بازگردانی</button>').on('click', function () { undo(); $m.remove(); }).appendTo($m);
		}
		$('#yl-toast').append($m);
		setTimeout(function () { $m.addClass('is-out'); }, undo ? 7000 : 4200);
		setTimeout(function () { $m.remove(); }, undo ? 7400 : 4600);
	}

	/* ---------------- dirty state ---------------- */
	function setDirty(v) {
		dirty = v;
		$('#yl-dirty').prop('hidden', !v);
		$('#yl-save').toggleClass('is-dirty', v);
	}
	$form.on('input change', ':input', function (e) {
		if ($(e.target).is('.yl-prod-q, #yl-bulk-cat')) return;
		setDirty(true);
	});
	$(document).on('tinymce-editor-init', function (e, ed) {
		ed.on('change input undo redo', function () { setDirty(true); });
	});
	window.addEventListener('beforeunload', function (e) {
		if (dirty) { e.preventDefault(); e.returnValue = ''; }
	});

	/* ---------------- tabs ---------------- */
	function showTab(id) {
		var $p = $('.yl-panel[data-panel="' + id + '"]');
		if (!$p.length) { id = 'start'; $p = $('.yl-panel[data-panel="start"]'); }
		$('.yl-panel').attr('hidden', true);
		$p.removeAttr('hidden');
		$('.yl-side-i').removeClass('is-active').removeAttr('aria-current').filter('[data-tab="' + id + '"]').addClass('is-active').attr('aria-current', 'page');
		if (id === 'catalog') syncTags();
		evalShow($p);
	}
	function hashTab() { var m = location.hash.match(/tab=([\w-]+)/); return m ? m[1] : null; }
	$(window).on('hashchange', function () {
		var t = hashTab();
		if (t) { showTab(t); window.scrollTo({ top: 0 }); }
	});

	/* ---------------- conditional fields ---------------- */
	function ctrlVal($scope, key, rowScoped) {
		var $f = $scope.find('.yl-f[data-k="' + key + '"]').filter(function () {
			var $r = $(this).closest('.yl-row');
			return rowScoped ? $r[0] === $scope[0] : (!$r.length || !$.contains($scope[0], $r[0]));
		}).first();
		if (!$f.length) return null;
		var $r = $f.find('input[type=radio]:checked');
		if ($r.length) return $r.val();
		var $c = $f.find('input[type=checkbox]');
		if ($c.length) return $c.prop('checked') ? '1' : '0';
		return $f.find('select, input, textarea').first().val();
	}
	function evalShow($ctx) {
		($ctx || $form).find('[data-show]').each(function () {
			var $el = $(this), cond = String($el.data('show'));
			var up = cond.charAt(0) === '^';
			if (up) cond = cond.slice(1);
			var neg = cond.indexOf('!=') > -1;
			var p = cond.split(neg ? '!=' : '=');
			var $scope = $el.closest('.yl-row');
			var rowScoped = true;
			if (up) $scope = $scope.parent().closest('.yl-row');
			if (!$scope.length) { $scope = $el.closest('.yl-card'); rowScoped = false; }
			var v = ctrlVal($scope, p[0], rowScoped);
			var ok = v === null ? true : (neg ? v !== p[1] : v === p[1]);
			$el.toggleClass('is-hidden', !ok);
		});
	}
	$form.on('change', 'input[type=radio], select, input[type=checkbox]', function () {
		evalShow($(this).closest('.yl-panel'));
	});

	/* ---------------- section on/off ---------------- */
	$form.on('change', '[data-proxy]', function () {
		$('[data-sec-on="' + $(this).data('proxy') + '"]').prop('checked', this.checked).trigger('change');
	});
	$form.on('change', '[data-sec-on]', function () {
		var s = $(this).data('sec-on'), on = this.checked;
		$('[data-proxy="' + s + '"]').prop('checked', on);
		$('[data-dot="' + s + '"]').toggleClass('is-on', on);
		$(this).closest('.yl-ord').toggleClass('is-off', !on).find('.yl-sw-l').text(on ? 'روشن' : 'خاموش');
	});
	$('.yl-order').sortable({ items: '> li:not(.is-fixed)', handle: '.yl-drag', axis: 'y', placeholder: 'yl-ord-ph', update: function () { setDirty(true); } });
	$form.on('click', '[data-ord]', function () {
		var $li = $(this).closest('.yl-ord'), d = +$(this).data('ord');
		var $to = d < 0 ? $li.prev('.yl-ord:not(.is-fixed)') : $li.next('.yl-ord:not(.is-fixed)');
		if (!$to.length) return;
		if (d < 0) $li.insertBefore($to); else $li.insertAfter($to);
		setDirty(true);
	});

	/* ---------------- repeaters ---------------- */
	function sortables($ctx) {
		$ctx.find('.yl-rows').each(function () {
			var $rows = $(this);
			if ($rows.data('ui-sortable')) return;
			var d = $rows.closest('.yl-rep').data('depth');
			$rows.sortable({
				items: '> .yl-row',
				handle: '.yl-drag-d' + d,
				axis: 'y',
				tolerance: 'pointer',
				placeholder: 'yl-row-ph',
				forcePlaceholderSize: true,
				update: function () { setDirty(true); }
			});
		});
	}
	function count($rep) {
		var n = $rep.children('.yl-rows').children('.yl-row').length;
		$rep.children('.yl-rep-f').find('[data-n]').text(n ? fa(n) + ' مورد' : 'هنوز موردی اضافه نشده');
	}
	function fillKeys($ctx) {
		$ctx.find('input.yl-key').each(function () { if (!this.value) this.value = newKey(); });
	}
	function addRow($rep) {
		var d = $rep.data('depth');
		var html = $rep.children('template.yl-tpl')[0].innerHTML.split('__R' + d + '__').join(newId());
		var $row = $(html);
		$rep.children('.yl-rows').append($row);
		fillKeys($row);
		sortables($row);
		$row.find('.yl-rep').each(function () { count($(this)); });
		evalShow($row);
		moneyWords($row);
		count($rep);
		setDirty(true);
		return $row;
	}
	$form.on('click', '.yl-add', function () {
		var $row = addRow($(this).closest('.yl-rep'));
		$row[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		setTimeout(function () { $row.find('.yl-in:visible').first().trigger('focus'); }, 250);
	});
	$form.on('click', '.yl-dup', function () {
		var $row = $(this).closest('.yl-row'), $rep = $row.closest('.yl-rep');
		var nm = $row.find('[name]').first().attr('name') || '';
		var depth = +$rep.data('depth');
		var re = /\[(r\d+|n[a-z0-9]+)\]/g, m, hits = [];
		while ((m = re.exec(nm))) hits.push(m);
		var hit = hits[depth];
		if (!hit) return;
		var oldPre = nm.slice(0, hit.index + hit[0].length);
		var newPre = nm.slice(0, hit.index) + '[' + newId() + ']';
		var $c = $row.clone();
		$c.find('[name]').each(function () { this.name = this.name.split(oldPre).join(newPre); });
		$c.find('[id]').each(function () { this.id = this.id + '_c' + (uid++); });
		$c.find('template').each(function () { this.innerHTML = this.innerHTML.split(oldPre).join(newPre); });
		$c.find('.ui-sortable').removeClass('ui-sortable');
		$c.find('input.yl-key').val('');
		fillKeys($c);
		var $t = $c.children('.yl-row-h').find('[data-row-title]');
		$t.text($t.text() + ' (کپی)');
		$c.insertAfter($row);
		sortables($c);
		count($rep);
		setDirty(true);
		toast('یک کپی از ردیف ساخته شد.', 'ok');
	});
	$form.on('click', '.yl-del', function () {
		var $row = $(this).closest('.yl-row'), $rep = $row.closest('.yl-rep');
		var $prev = $row.prev(), $list = $row.parent();
		var title = $row.children('.yl-row-h').find('[data-row-title]').text();
		$row.detach();
		count($rep);
		setDirty(true);
		toast(title ? '«' + title + '» حذف شد.' : 'ردیف حذف شد.', 'info', '', function () {
			if ($prev.length && $.contains(document, $prev[0])) $row.insertAfter($prev); else $list.prepend($row);
			count($rep);
		});
	});
	$form.on('click', '[data-move]', function () {
		var $row = $(this).closest('.yl-row'), d = +$(this).data('move');
		if (d < 0 && $row.prev('.yl-row').length) $row.insertBefore($row.prev('.yl-row'));
		if (d > 0 && $row.next('.yl-row').length) $row.insertAfter($row.next('.yl-row'));
		setDirty(true);
	});
	$form.on('click', '.yl-row-t', function () {
		var $row = $(this).closest('.yl-row');
		$row.toggleClass('is-open');
		$(this).attr('aria-expanded', $row.hasClass('is-open') ? 'true' : 'false');
		if ($row.hasClass('is-open')) evalShow($row);
	});
	$form.on('click', '.yl-fold', function () {
		var $rows = $(this).closest('.yl-rep').children('.yl-rows').children('.yl-row');
		var open = !$rows.first().hasClass('is-open');
		$rows.toggleClass('is-open', open).children('.yl-row-h').find('.yl-row-t').attr('aria-expanded', open ? 'true' : 'false');
	});
	// Live row titles.
	$form.on('input change', '.yl-f input, .yl-f select', function () {
		var $f = $(this).closest('.yl-f'), $row = $f.closest('.yl-row');
		if (!$row.length || $(this).is('.yl-prod-q')) return;
		var $rep = $row.closest('.yl-rep');
		if (String($rep.data('title')) === String($f.data('k'))) {
			var v = $.trim($(this).val());
			$row.children('.yl-row-h').find('[data-row-title]').text(v || 'بدون عنوان');
		}
	});

	/* ---------------- images ---------------- */
	function rowThumb($el, url) {
		var $row = $el.closest('.yl-row');
		if ($row.length) $row.children('.yl-row-h').find('.yl-row-th').html(url ? '<img src="' + esc(url) + '" alt="">' : '');
	}
	function setImg($w, url, isDef) {
		$w.toggleClass('has-img', !!url).toggleClass('is-def', !!isDef);
		$w.find('.yl-img-prev').html(url ? '<img src="' + esc(url) + '" alt="">' : '<span class="dashicons dashicons-format-image"></span>');
		$w.find('.yl-img-pick').text(url ? 'تغییر تصویر' : 'انتخاب تصویر');
		if ($w.closest('.yl-f').data('k') === 'img') rowThumb($w, url);
	}
	$form.on('click', '.yl-img-pick, .yl-img-prev', function () {
		var $w = $(this).closest('.yl-img');
		var frame = wp.media({ title: 'انتخاب تصویر', button: { text: 'استفاده از این تصویر' }, library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var sz = a.sizes && (a.sizes.medium || a.sizes.full);
			$w.find('input[type=hidden]').val(a.id).trigger('change');
			setImg($w, sz ? sz.url : a.url, false);
		});
		frame.open();
	});
	$form.on('click', '.yl-img-def', function () {
		var $w = $(this).closest('.yl-img');
		$w.find('input[type=hidden]').val('').trigger('change');
		setImg($w, $w.data('def'), true);
	});
	$form.on('click', '.yl-img-del', function () {
		var $w = $(this).closest('.yl-img');
		$w.find('input[type=hidden]').val('').trigger('change');
		setImg($w, '', false);
	});

	/* ---------------- WooCommerce product picker ---------------- */
	var timer = 0, xhr = null;
	function prodCard(p) {
		return '<span class="yl-pc">' + (p.img ? '<img src="' + esc(p.img) + '" alt="">' : '') +
			'<span><b>' + esc(p.name) + '</b><small>' + p.price + ' · شناسه ' + fa(p.id) + '</small>' + (p.warn ? '<em>' + esc(p.warn) + '</em>' : '') + '</span>' +
			'<button type="button" class="yl-link yl-prod-change">تغییر محصول</button></span>';
	}
	function setProd($w, p) {
		$w.find('.yl-prod-v').val(p.id).trigger('change');
		$w.find('.yl-prod-sel').html(prodCard(p));
		$w.addClass('has-prod');
		$w.find('.yl-prod-q').val('');
		$w.find('.yl-prod-res').removeClass('is-open').empty();
		var $row = $w.closest('.yl-row');
		if ($row.length) {
			$row.children('.yl-row-h').find('[data-row-title]').text(p.name);
			rowThumb($w, p.img);
		}
	}
	function search($in) {
		var $res = $in.siblings('.yl-prod-res');
		if (xhr) xhr.abort();
		$res.addClass('is-open').html('<div class="yl-pr-msg">در حال جستجو…</div>');
		xhr = $.post(YLA.ajax, { action: 'yl_products', nonce: YLA.nonce, q: $in.val() }).done(function (r) {
			var list = (r && r.data) || [];
			$res.data('list', list);
			if (!list.length) { $res.html('<div class="yl-pr-msg">محصولی پیدا نشد. بخشی از نام را بنویسید.</div>'); return; }
			$res.html(list.map(function (p, i) {
				return '<button type="button" class="yl-pr-i" role="option" data-i="' + i + '">' + (p.img ? '<img src="' + esc(p.img) + '" alt="">' : '<i></i>') +
					'<span><b>' + esc(p.name) + '</b><small>' + p.price + ' · شناسه ' + fa(p.id) + '</small>' + (p.warn ? '<em>' + esc(p.warn) + '</em>' : '') + '</span></button>';
			}).join(''));
		});
	}
	$form.on('input', '.yl-prod-q', function () {
		var $in = $(this);
		clearTimeout(timer);
		timer = setTimeout(function () { search($in); }, 280);
	});
	$form.on('focus', '.yl-prod-q', function () { search($(this)); });
	$form.on('blur', '.yl-prod-q', function () {
		var $res = $(this).siblings('.yl-prod-res');
		setTimeout(function () { if (!$res.find(':focus').length) $res.removeClass('is-open'); }, 200);
	});
	$form.on('mousedown', '.yl-pr-i', function (e) { e.preventDefault(); });
	$form.on('click', '.yl-pr-i', function () {
		var $res = $(this).closest('.yl-prod-res');
		var p = ($res.data('list') || [])[+$(this).data('i')];
		if (p) setProd($(this).closest('.yl-prod'), p);
	});
	$form.on('keydown', '.yl-prod-q', function (e) {
		if (e.key === 'ArrowDown') { e.preventDefault(); $(this).siblings('.yl-prod-res').find('.yl-pr-i').first().trigger('focus'); }
		if (e.key === 'Enter') e.preventDefault();
	});
	$form.on('keydown', '.yl-pr-i', function (e) {
		if (e.key === 'ArrowDown') { e.preventDefault(); $(this).next('.yl-pr-i').trigger('focus'); }
		if (e.key === 'ArrowUp') { e.preventDefault(); var $p = $(this).prev('.yl-pr-i'); ($p.length ? $p : $(this).closest('.yl-prod-find').find('.yl-prod-q')).trigger('focus'); }
	});
	$form.on('click', '.yl-prod-change', function () {
		var $w = $(this).closest('.yl-prod');
		$w.removeClass('has-prod');
		$w.find('.yl-prod-q').trigger('focus');
	});

	/* bulk add from a category */
	$('#yl-bulk-add').on('click', function () {
		var cat = $('#yl-bulk-cat').val(), $b = $(this);
		if (!cat || cat === '0') return toast('ابتدا یک دسته انتخاب کنید.', 'err');
		$b.addClass('is-busy');
		$.post(YLA.ajax, { action: 'yl_cat_products', nonce: YLA.nonce, cat: cat }).done(function (r) {
			var list = (r && r.data) || [];
			var $rep = $('.yl-panel[data-panel="catalog"] .yl-rep').first();
			var have = {};
			$rep.find('.yl-prod-v').each(function () { have[this.value] = 1; });
			var n = 0;
			list.forEach(function (p) {
				if (!p || have[p.id]) return;
				var $row = addRow($rep);
				$row.removeClass('is-open').children('.yl-row-h').find('.yl-row-t').attr('aria-expanded', 'false');
				$row.find('.yl-f[data-k="src"] input[value="woo"]').prop('checked', true);
				setProd($row.find('.yl-prod'), p);
				evalShow($row);
				n++;
			});
			toast(n ? fa(n) + ' محصول به کاتالوگ اضافه شد. برچسب حال‌وهوای هرکدام را تعیین و ذخیره کنید.' : 'محصول تازه‌ای در این دسته نبود.', n ? 'ok' : 'info');
		}).always(function () { $b.removeClass('is-busy'); });
	});

	/* mood tags in the catalog follow the builder's mood options */
	function moodList() {
		var moods = [];
		$('.yl-panel[data-panel="build"] .yl-f[data-k="steps"] > .yl-rep > .yl-rows > .yl-row').each(function () {
			var $st = $(this);
			if (ctrlVal($st, 'type', true) !== 'mood') return;
			$st.find('.yl-f[data-k="opts"] .yl-row').each(function () {
				var k = $(this).find('input.yl-key').val(), l = $(this).find('.yl-f[data-k="label"] input').val();
				if (k && l) moods.push([k, l]);
			});
		});
		return moods;
	}
	function chips(name, moods, on) {
		if (!moods.length) return '<span class="yl-note">ابتدا در تب «فرم پک‌ساز» یک سؤال از نوع «حال‌وهوا» بسازید.</span>';
		return moods.map(function (m) {
			return '<label class="yl-chip"><input type="checkbox" name="' + esc(name) + '" value="' + esc(m[0]) + '"' + (on && on[m[0]] ? ' checked' : '') + '><span>' + esc(m[1]) + '</span></label>';
		}).join('');
	}
	function syncTags() {
		var moods = moodList();
		$('.yl-panel[data-panel="catalog"] .yl-tags').each(function () {
			var $t = $(this), on = {};
			$t.find('input:checked').each(function () { on[this.value] = 1; });
			$t.html(chips($t.data('name'), moods, on));
		});
		$('.yl-panel[data-panel="catalog"] template.yl-tpl').each(function () {
			var $tmp = $('<div>').html(this.innerHTML);
			$tmp.find('.yl-tags').each(function () { $(this).html(chips($(this).data('name'), moods, null)); });
			this.innerHTML = $tmp.html();
		});
	}

	/* ---------------- money words ---------------- */
	function words(n) {
		if (!n) return '';
		var m = Math.floor(n / 1e6), k = Math.floor((n % 1e6) / 1e3), r = n % 1e3, p = [];
		if (m) p.push(fa(m) + ' میلیون');
		if (k) p.push(fa(k) + ' هزار');
		if (r) p.push(fa(r));
		return p.join(' و ') + ' تومان';
	}
	function moneyWords($ctx) {
		($ctx || $form).find('.yl-money input').each(function () {
			var n = parseInt(en(this.value).replace(/[^\d]/g, ''), 10) || 0;
			$(this).closest('.yl-f').find('[data-words]').text(words(n));
		});
	}
	$form.on('input', '.yl-money input', function () { moneyWords($(this).closest('.yl-f')); });
	$form.on('blur', '.yl-money input', function () {
		var n = parseInt(en(this.value).replace(/[^\d]/g, ''), 10);
		this.value = isNaN(n) ? '' : n.toLocaleString('en-US');
	});

	/* ---------------- color ---------------- */
	$form.on('input change', '.yl-color input', function () { $(this).siblings('code').text(this.value.toUpperCase()); });

	/* ---------------- jalali date helper ---------------- */
	function j2g(jy, jm, jd) {
		jy += 1595;
		var days = -355668 + (365 * jy) + (~~(jy / 33) * 8) + ~~(((jy % 33) + 3) / 4) + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
		var gy = 400 * ~~(days / 146097);
		days %= 146097;
		if (days > 36524) { gy += 100 * ~~(--days / 36524); days %= 36524; if (days >= 365) days++; }
		gy += 4 * ~~(days / 1461);
		days %= 1461;
		if (days > 365) { gy += ~~((days - 1) / 365); days = (days - 1) % 365; }
		var gd = days + 1;
		var sal = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
		var gm;
		for (gm = 1; gm < 13 && gd > sal[gm]; gm++) gd -= sal[gm];
		return [gy, gm, gd];
	}
	function jalInfo() {
		$('[data-jal]').each(function () {
			var $w = $(this);
			var y = +en($w.find('[name$="[y]"]').val()), m = +$w.find('[name$="[m]"]').val(), d = +en($w.find('[name$="[d]"]').val());
			var t = ($w.find('[name$="[t]"]').val() || '00:00').split(':');
			var $info = $w.next('[data-jal-info]');
			if (!y || !m || !d) { $info.text(''); return; }
			if (m >= 7 && d > 30) { $info.addClass('is-err').text('ماه ' + YLA.months[m - 1] + ' حداکثر ۳۰ روز دارد.'); return; }
			var g = j2g(y, m, d);
			var ts = Date.UTC(g[0], g[1] - 1, g[2], +t[0] || 0, +t[1] || 0) - 126e5;
			var wd = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'][new Date(Date.UTC(g[0], g[1] - 1, g[2])).getUTCDay()];
			var left = ts - Date.now();
			var txt = wd + ' ' + fa(d) + ' ' + YLA.months[m - 1] + ' ' + fa(y) + ' (' + g[0] + '/' + g[1] + '/' + g[2] + ') — ';
			txt += left > 0 ? fa(Math.floor(left / 864e5)) + ' روز و ' + fa(Math.floor((left % 864e5) / 36e5)) + ' ساعت مانده' : 'این زمان گذشته است؛ شمارنده‌ها صفر نمایش داده می‌شوند.';
			$info.toggleClass('is-err', left <= 0).text(txt);
		});
	}
	$form.on('input change', '[data-jal] :input', jalInfo);

	/* ---------------- create page ---------------- */
	$form.on('click', '#yl-new-page', function () {
		if (!window.confirm('یک صفحه‌ی جدید با عنوان «هدیه سازمانی شب یلدا» ساخته و منتشر شود و لندینگ روی آن نمایش داده شود؟')) return;
		var $b = $(this).addClass('is-busy');
		$.post(YLA.ajax, { action: 'yl_create_page', nonce: YLA.nonce }).done(function (r) {
			if (!r || !r.success) { toast((r && r.data && r.data.msg) || 'ساخت صفحه ناموفق بود.', 'err'); return; }
			var $sel = $b.siblings('select');
			$sel.append('<option value="' + r.data.id + '">' + esc(r.data.title) + '</option>').val(String(r.data.id));
			$('#yl-status').html(r.data.status);
			toast('صفحه ساخته شد و لندینگ روی آن فعال است.', 'ok', ' <a href="' + esc(r.data.url) + '" target="_blank" rel="noopener">مشاهده</a>');
		}).fail(function () { toast('ساخت صفحه ناموفق بود.', 'err'); }).always(function () { $b.removeClass('is-busy'); });
	});

	/* ---------------- save ---------------- */
	function setPath(obj, name, val) {
		var parts = name.replace(/\]/g, '').split('[');
		var cur = obj;
		for (var i = 0; i < parts.length; i++) {
			var k = parts[i], last = i === parts.length - 1;
			if (last) {
				if (k === '' && Array.isArray(cur)) cur.push(val); else cur[k] = val;
			} else {
				if (cur[k] === undefined || typeof cur[k] !== 'object') cur[k] = parts[i + 1] === '' ? [] : {};
				cur = cur[k];
			}
		}
	}
	function collect() {
		if (window.tinymce) window.tinymce.triggerSave();
		var out = {};
		$form.find('input[name], select[name], textarea[name]').each(function () {
			if (this.disabled) return;
			if ((this.type === 'checkbox' || this.type === 'radio') && !this.checked) return;
			setPath(out, this.name, this.value);
		});
		return out.yl || {};
	}
	function checkWarnings() {
		var empty = 0;
		$form.find('.yl-f[data-k="pid"]').each(function () {
			if ($(this).closest('.is-hidden').length) return;
			var v = $(this).find('.yl-prod-v').val();
			if (!v || v === '0') empty++;
		});
		if (empty) toast(fa(empty) + ' ردیف «محصول ووکامرس» هنوز محصولی انتخاب نکرده و در صفحه نمایش داده نمی‌شود.', 'info');
	}
	function save() {
		var $b = $('#yl-save');
		if ($b.hasClass('is-busy')) return;
		$b.addClass('is-busy');
		$.post(YLA.ajax, { action: 'yl_save', nonce: YLA.nonce, data: JSON.stringify(collect()) }).done(function (r) {
			if (r && r.success) {
				setDirty(false);
				$('#yl-status').html(r.data.status);
				toast(r.data.msg, 'ok');
				checkWarnings();
			} else {
				toast((r && r.data && r.data.msg) || 'ذخیره نشد؛ دوباره تلاش کنید.', 'err');
			}
		}).fail(function (x) {
			var j = x.responseJSON;
			toast((j && j.data && j.data.msg) || 'ارتباط با سرور برقرار نشد؛ دوباره تلاش کنید.', 'err');
		}).always(function () { $b.removeClass('is-busy'); });
	}
	$('#yl-save').on('click', save);
	$(document).on('keydown', function (e) {
		if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S' || e.key === 'س')) { e.preventDefault(); save(); }
	});

	/* ---------------- init ---------------- */
	sortables($form);
	$form.find('.yl-rep').each(function () { count($(this)); });
	showTab(hashTab() || 'start');
	evalShow($form);
	moneyWords();
	jalInfo();
	setDirty(false);
})(jQuery);
