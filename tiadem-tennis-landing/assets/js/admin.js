/* Tiadem Tennis Landing — admin screen */
(function ($) {
	'use strict';

	var A = window.TDLAdmin || {};
	var $form = $('#tdl-form');
	if (!$form.length) { return; }
	var dirty = false;

	function setDirty(v) {
		dirty = v;
		$('[data-tdl-dirty]').prop('hidden', !v);
	}

	/* ---------- tabs ---------- */
	function showTab(key) {
		var $tab = $('.tdl-tab[data-tab="' + key + '"]');
		if (!$tab.length) { return; }
		$('.tdl-tab').removeClass('is-active');
		$tab.addClass('is-active');
		$('.tdl-panel').removeClass('is-active');
		$('.tdl-panel[data-panel="' + key + '"]').addClass('is-active');
		$('[data-tdl-tab-input]').val(key);
		if (history.replaceState) { history.replaceState(null, '', '#' + key); }
	}
	$(document).on('click', '.tdl-tab, [data-goto]', function (e) {
		e.preventDefault();
		showTab($(this).data('tab') || $(this).data('goto'));
		window.scrollTo({ top: 0, behavior: 'smooth' });
	});
	if (location.hash && $('.tdl-tab[data-tab="' + location.hash.slice(1) + '"]').length) {
		showTab(location.hash.slice(1));
	}

	/* ---------- sortables ---------- */
	function initSortables(scope) {
		$('[data-order]', scope).sortable({ handle: '.tdl-handle', axis: 'y', update: function () { setDirty(true); } });
		$('.tdl-rep-list', scope).each(function () {
			if ($(this).data('ui-sortable')) { return; }
			$(this).sortable({ handle: '.tdl-handle', axis: 'y', update: function () { setDirty(true); } });
		});
	}
	initSortables(document);

	/* ---------- repeaters ---------- */
	function itemTitle($item) {
		var key = $item.closest('.tdl-rep').data('label-key');
		if (!key) { return; }
		var $sub = $item.find('> .tdl-item-body > .tdl-sub[data-key="' + key + '"]');
		var v = $.trim($sub.find('[data-v]').val() || '');
		$item.find('> .tdl-item-head .tdl-item-t').text(v ? v.substring(0, 60) : 'آیتم جدید');
	}
	$(document).on('click', '[data-rep-add]', function () {
		var $rep = $(this).closest('.tdl-rep');
		var html = $rep.children('.tdl-rep-tpl').html().replace(/<\\\/script/g, '</script');
		var $item = $(html).addClass('is-open');
		$rep.children('.tdl-rep-list').append($item);
		setDirty(true);
		$item.find('input,textarea').first().trigger('focus');
	});
	$(document).on('click', '[data-item-toggle]', function () {
		$(this).closest('.tdl-item').toggleClass('is-open');
	});
	$(document).on('click', '[data-item-del]', function () {
		if (!window.confirm(A.confirmDelete)) { return; }
		$(this).closest('.tdl-item').slideUp(180, function () { $(this).remove(); });
		setDirty(true);
	});
	$(document).on('click', '[data-item-dup]', function () {
		var $item = $(this).closest('.tdl-item');
		var $copy = $item.clone();
		$item.find('select').each(function (i) { $copy.find('select').eq(i).val($(this).val()); });
		$item.after($copy.addClass('is-open'));
		setDirty(true);
	});
	$(document).on('input change', '.tdl-item [data-v]', function () { itemTitle($(this).closest('.tdl-item')); });

	/* ---------- images ---------- */
	$(document).on('click', '[data-img-pick]', function (e) {
		e.preventDefault();
		var $box = $(this).closest('.tdl-img');
		var frame = wp.media({ title: A.mediaTitle, button: { text: A.mediaButton }, library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			var att = frame.state().get('selection').first().toJSON();
			var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
			$box.find('[data-v]').val(att.id);
			$box.find('.tdl-img-prev').html('<img src="' + url + '" alt="">');
			setDirty(true);
		});
		frame.open();
	});
	$(document).on('click', '[data-img-clear]', function () {
		var $box = $(this).closest('.tdl-img');
		$box.find('[data-v]').val('');
		$box.find('.tdl-img-prev').html('<span class="tdl-img-empty">بدون تصویر</span>');
		setDirty(true);
	});

	/* ---------- icons & colors ---------- */
	$(document).on('change', '.tdl-icon-pick select', function () {
		var path = (A.icons || {})[$(this).val()] || '';
		$(this).siblings('[data-icon-prev]').html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="' + path + '"/></svg>');
	});
	$(document).on('input', '.tdl-color input', function () { $(this).siblings('code').text($(this).val()); });

	/* ---------- jalali picker ---------- */
	$(document).on('click', '[data-preset]', function () {
		var $j = $(this).closest('.tdl-jdt');
		var p = $j.data('presets')[$(this).data('preset')];
		['y', 'm', 'd', 'h', 'i'].forEach(function (k) { $j.find('[data-part="' + k + '"]').val(p[k]); });
		setDirty(true);
	});
	$(document).on('click', '[data-jdt-clear]', function () {
		var $j = $(this).closest('.tdl-jdt');
		$j.find('[data-part="y"],[data-part="m"],[data-part="d"]').val('');
		setDirty(true);
	});

	/* ---------- dirty tracking ---------- */
	$form.on('input change', ':input', function () { setDirty(true); });
	$(window).on('beforeunload', function () { if (dirty) { return A.unsaved; } });

	/* ---------- serialize ---------- */
	function read($w) {
		var type = $w.data('type');
		if (type === 'repeater') {
			return $w.find('> .tdl-rep > .tdl-rep-list > .tdl-item').map(function () {
				var item = {};
				$(this).find('> .tdl-item-body > .tdl-sub').each(function () { item[$(this).data('key')] = read($(this)); });
				return item;
			}).get();
		}
		if (type === 'toggle') { return $w.find('[data-v]').is(':checked') ? 1 : 0; }
		if (type === 'terms' || type === 'products') { return $w.find('[data-v]').val() || []; }
		if (type === 'jdatetime') {
			var o = {};
			$w.find('[data-part]').each(function () { o[$(this).data('part')] = $(this).val(); });
			return o;
		}
		if (type === 'order') { return $w.find('[data-order] > li').map(function () { return $(this).data('key'); }).get(); }
		return $w.find('[data-v]').first().val();
	}
	$form.on('submit', function () {
		var payload = {};
		$('.tdl-panel').each(function () {
			var tab = $(this).data('panel');
			$(this).find('> .tdl-fields > .tdl-field').each(function () {
				var $f = $(this);
				if ($f.data('type') === 'order') { payload.order = read($f); return; }
				payload[tab] = payload[tab] || {};
				payload[tab][$f.data('key')] = read($f);
			});
		});
		$form.find('[data-tdl-payload]').val(JSON.stringify(payload));
		setDirty(false);
		$form.find('.tdl-save').prop('disabled', true).text('در حال ذخیره…');
		return true;
	});

	/* ---------- confirms & import ---------- */
	$(document).on('click', '[data-confirm]', function (e) {
		if (!window.confirm($(this).attr('data-confirm') || A.confirmReset)) { e.preventDefault(); }
	});
	$(document).on('click', '[data-tdl-import-btn]', function () {
		var json = $('[data-tdl-import]').val();
		if (!$.trim(json)) { return; }
		var $f = $('<form method="post"></form>').attr('action', $(this).data('url'));
		$('<textarea name="json"></textarea>').val(json).appendTo($f);
		$f.appendTo('body');
		setDirty(false);
		$f.trigger('submit');
	});
}(jQuery));
