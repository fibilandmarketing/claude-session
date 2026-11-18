/*! Tiadem Tennis Landing — front-end interactions (no dependencies) */
(function () {
	'use strict';

	var C = window.TDL || {};
	var d = document;
	var root = d.getElementById('tdl-root');
	if (!root || !C.ajax) { return; }

	var reduce = !C.anim || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	var offset = (C.now || Date.now()) - Date.now();
	var T = C.i18n || {};

	function now() { return Date.now() + offset; }
	function fa(n) { return String(n).replace(/\d/g, function (x) { return '۰۱۲۳۴۵۶۷۸۹'[x]; }); }
	function pad(n) { return n < 10 ? '0' + n : String(n); }
	function ep(name) { return C.ajax.replace('%%endpoint%%', name); }
	function qs(s, c) { return (c || d).querySelector(s); }
	function qsa(s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); }
	function on(el, ev, fn, opt) { if (el) { el.addEventListener(ev, fn, opt || false); } }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function raf(fn) { return (window.requestAnimationFrame || setTimeout)(fn); }

	function request(name, data, method) {
		var url = ep(name);
		var opts = { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } };
		if (method === 'POST') {
			var body = new FormData();
			Object.keys(data || {}).forEach(function (k) {
				var v = data[k];
				if (v && typeof v === 'object') {
					Object.keys(v).forEach(function (sk) { body.append(k + '[' + sk + ']', v[sk]); });
				} else {
					body.append(k, v);
				}
			});
			opts.method = 'POST';
			opts.body = body;
		} else if (data) {
			url += (url.indexOf('?') > -1 ? '&' : '?') + Object.keys(data).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); }).join('&');
		}
		return fetch(url, opts).then(function (r) {
			return r.json().catch(function () { return { success: false, data: {} }; });
		});
	}

	/* ---------------- reveal on scroll ---------------- */
	var io = null;
	if ('IntersectionObserver' in window && !reduce) {
		io = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) {
				if (!e.isIntersecting) { return; }
				var row = e.target.parentNode && e.target.parentNode.classList && e.target.parentNode.classList.contains('vt-scroll') ? e.target.parentNode : null;
				// Items clipped inside horizontal scrollers never hit the viewport: reveal the whole row together.
				var items = row ? qsa('[data-tdl-reveal]:not(.is-in)', row) : [e.target];
				items.forEach(function (el) { el.classList.add('is-in'); io.unobserve(el); });
			});
		}, { rootMargin: '0px 0px -6% 0px', threshold: 0.01 });
	}
	function reveal(scope) {
		qsa('[data-tdl-reveal]:not(.is-in)', scope || d).forEach(function (el) {
			if (io) { io.observe(el); } else { el.classList.add('is-in'); }
		});
	}
	reveal();

	/* ---------------- toast ---------------- */
	var toastEl = qs('[data-tdl-toast]');
	var toastTimer = null;
	function toast(msg, isErr) {
		if (!toastEl) { return; }
		qs('[data-tdl-toast-msg]', toastEl).textContent = msg;
		toastEl.classList.toggle('is-err', !!isErr);
		toastEl.hidden = true;
		void toastEl.offsetWidth;
		toastEl.hidden = false;
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { toastEl.hidden = true; }, isErr ? 4200 : 3200);
	}

	/* ---------------- header: stuck state + mobile bar ---------------- */
	var hdr = d.getElementById('tdl-hdr');
	var bar = qs('[data-tdl-bar]');
	var hero = d.getElementById('top');
	var ticking = false;
	function onScroll() {
		if (ticking) { return; }
		ticking = true;
		raf(function () {
			var y = window.pageYOffset || d.documentElement.scrollTop;
			if (hdr) { hdr.classList.toggle('is-stuck', y > 8); }
			if (bar) {
				var limit = hero ? hero.offsetTop + hero.offsetHeight * 0.6 : 400;
				var footer = qs('.vt-footer-wrap', root);
				var nearEnd = footer && (y + window.innerHeight) > (footer.offsetTop + 40);
				bar.classList.toggle('is-on', y > limit && !nearEnd);
			}
			ticking = false;
		});
	}
	on(window, 'scroll', onScroll, { passive: true });
	onScroll();

	/* ---------------- header search popover ---------------- */
	var searchBtn = qs('[data-tdl-search]', root);
	var searchPop = d.getElementById('tdl-search');
	function setSearch(open) {
		if (!searchBtn || !searchPop) { return; }
		searchPop.classList.toggle('is-open', open);
		searchBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (open) { setTimeout(function () { var i = qs('input[type=search]', searchPop); if (i) { i.focus(); } }, 60); }
	}
	on(searchBtn, 'click', function (e) { e.stopPropagation(); setSearch(!searchPop.classList.contains('is-open')); });
	on(d, 'click', function (e) { if (searchPop && searchPop.classList.contains('is-open') && !searchPop.contains(e.target)) { setSearch(false); } });

	/* ---------------- focus trap helper ---------------- */
	function trap(container, e) {
		if (e.key !== 'Tab') { return; }
		var f = qsa('a[href],button:not([disabled]),input,select,textarea,[tabindex]:not([tabindex="-1"])', container).filter(function (el) { return el.offsetParent !== null; });
		if (!f.length) { return; }
		var first = f[0], last = f[f.length - 1];
		if (e.shiftKey && d.activeElement === first) { e.preventDefault(); last.focus(); }
		else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); }
	}

	/* ---------------- drawer ---------------- */
	var drawer = d.getElementById('tdl-drawer');
	var scrim = qs('.vt-scrim');
	var burger = qs('[data-tdl-open="drawer"]', root);
	var lastFocus = null;
	function openDrawer() {
		if (!drawer) { return; }
		lastFocus = d.activeElement;
		drawer.hidden = false; scrim.hidden = false;
		d.documentElement.classList.add('tdl-lock');
		raf(function () { raf(function () { drawer.classList.add('is-open'); scrim.classList.add('is-open'); }); });
		if (burger) { burger.setAttribute('aria-expanded', 'true'); }
		setTimeout(function () { var b = qs('[data-tdl-close="drawer"]', drawer); if (b) { b.focus(); } }, 80);
	}
	function closeDrawer() {
		if (!drawer || drawer.hidden) { return; }
		drawer.classList.remove('is-open'); scrim.classList.remove('is-open');
		d.documentElement.classList.remove('tdl-lock');
		if (burger) { burger.setAttribute('aria-expanded', 'false'); }
		setTimeout(function () { drawer.hidden = true; scrim.hidden = true; }, reduce ? 0 : 600);
		if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
	}
	on(burger, 'click', openDrawer);
	on(drawer, 'keydown', function (e) { trap(drawer, e); });
	qsa('[data-tdl-close="drawer"]').forEach(function (el) {
		on(el, 'click', function () { closeDrawer(); });
	});

	/* ---------------- countdowns ---------------- */
	var timers = qsa('[data-tdl-countdown]');
	var festSec = qs('[data-tdl-fest]');
	function setTick(el, val) {
		if (el.textContent !== val) {
			el.textContent = val;
			if (!reduce && el.classList.contains('vt-tick')) {
				el.classList.remove('is-flip'); void el.offsetWidth; el.classList.add('is-flip');
			}
		}
	}
	function runTimers() {
		var t = now();
		timers.forEach(function (el) {
			var end = parseInt(el.getAttribute('data-tdl-countdown'), 10) || 0;
			var left = Math.max(0, Math.floor((end - t) / 1000));
			if (el.getAttribute('data-format') === 'hms') {
				var h = Math.floor(left / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
				setTick(el, fa(pad(h) + ':' + pad(m) + ':' + pad(s)));
				return;
			}
			var parts = { d: Math.floor(left / 86400), h: Math.floor(left % 86400 / 3600), m: Math.floor(left % 3600 / 60), s: left % 60 };
			qsa('[data-u]', el).forEach(function (u) { setTick(u, fa(pad(parts[u.getAttribute('data-u')]))); });
			if (left === 0 && festSec && C.hideFest && festSec.contains(el) && !festSec.hidden) {
				festSec.style.transition = 'opacity .6s'; festSec.style.opacity = '0';
				setTimeout(function () { festSec.hidden = true; }, 650);
			}
		});
	}
	if (timers.length) { runTimers(); setInterval(runTimers, 1000); }

	/* ---------------- horizontal rows: arrows + mouse drag ---------------- */
	qsa('[data-tdl-scroll]').forEach(function (btn) {
		on(btn, 'click', function () {
			var row = d.getElementById(btn.getAttribute('aria-controls'));
			if (!row) { return; }
			var first = row.firstElementChild;
			var step = first ? first.getBoundingClientRect().width + 16 : 280;
			row.scrollBy({ left: parseInt(btn.getAttribute('data-tdl-scroll'), 10) * step, behavior: reduce ? 'auto' : 'smooth' });
		});
	});
	qsa('[data-tdl-drag]').forEach(function (row) {
		var down = false, startX = 0, startL = 0, moved = false;
		on(row, 'pointerdown', function (e) {
			if (e.pointerType !== 'mouse' || e.button !== 0) { return; }
			down = true; moved = false; startX = e.clientX; startL = row.scrollLeft;
		});
		on(window, 'pointermove', function (e) {
			if (!down) { return; }
			var dx = e.clientX - startX;
			if (!moved && Math.abs(dx) > 6) { moved = true; row.classList.add('is-drag'); }
			if (moved) { row.scrollLeft = startL - dx; }
		});
		on(window, 'pointerup', function () {
			if (!down) { return; }
			down = false;
			setTimeout(function () { row.classList.remove('is-drag'); }, 0);
		});
		on(row, 'click', function (e) { if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; } }, true);
		on(row, 'dragstart', function (e) { e.preventDefault(); });
	});

	/* ---------------- grid: filters + load more ---------------- */
	var gridSec = qs('[data-tdl-grid]', root);
	var gridEl = gridSec ? qs('[data-tdl-items]', gridSec) : null;
	var moreBtn = gridSec ? qs('[data-tdl-more]', gridSec) : null;
	var emptyEl = gridSec ? qs('[data-tdl-empty]', gridSec) : null;
	var countEl = gridSec ? qs('[data-tdl-count]', gridSec) : null;
	var gridState = { filter: 'all', page: 1, busy: false };

	function markLastRow() {
		if (!gridEl) { return; }
		var cards = qsa('.vt-card', gridEl);
		var max = 0;
		cards.forEach(function (c) { c.classList.remove('vt-rl'); max = Math.max(max, c.offsetTop); });
		cards.forEach(function (c) { if (c.offsetTop === max) { c.classList.add('vt-rl'); } });
	}
	function setChip(filter) {
		qsa('[data-tdl-chip]', gridSec).forEach(function (b) {
			var onn = b.getAttribute('data-tdl-chip') === filter;
			b.classList.toggle('is-on', onn);
			b.setAttribute('aria-pressed', onn ? 'true' : 'false');
			if (onn && b.scrollIntoView && b.parentNode.scrollWidth > b.parentNode.clientWidth) {
				b.parentNode.scrollTo({ left: b.offsetLeft - b.parentNode.clientWidth / 2 + b.offsetWidth / 2, behavior: 'smooth' });
			}
		});
	}
	function loadGrid(filter, page) {
		if (!gridEl || gridState.busy) { return; }
		gridState.busy = true;
		var append = page > 1;
		if (append) { moreBtn.classList.add('is-loading'); } else { gridEl.classList.add('is-busy'); }
		request('tdl_grid', { filter: filter, page: page }).then(function (res) {
			gridState.busy = false;
			gridEl.classList.remove('is-busy');
			if (moreBtn) { moreBtn.classList.remove('is-loading'); }
			if (!res || !res.success) { toast(T.error, true); return; }
			gridState.filter = filter; gridState.page = page;
			var tpl = d.createElement('div');
			tpl.innerHTML = res.data.html;
			if (!append) { gridEl.innerHTML = ''; }
			qsa('.vt-card', tpl).forEach(function (card, i) { card.style.setProperty('--i', i % 8); gridEl.appendChild(card); });
			reveal(gridEl);
			paintWishes(gridEl);
			if (countEl) { countEl.textContent = res.data.count; }
			if (moreBtn) { moreBtn.hidden = !res.data.has_more; }
			if (emptyEl) { emptyEl.hidden = res.data.total > 0; }
			raf(markLastRow);
		}).catch(function () {
			gridState.busy = false; gridEl.classList.remove('is-busy');
			if (moreBtn) { moreBtn.classList.remove('is-loading'); }
			toast(T.error, true);
		});
	}
	if (gridSec) {
		qsa('[data-tdl-chip]', gridSec).forEach(function (b) {
			on(b, 'click', function () {
				var f = b.getAttribute('data-tdl-chip');
				if (f === gridState.filter) { return; }
				setChip(f);
				loadGrid(f, 1);
			});
		});
		on(moreBtn, 'click', function () { loadGrid(gridState.filter, gridState.page + 1); });
		markLastRow();
		var rz;
		on(window, 'resize', function () { clearTimeout(rz); rz = setTimeout(markLastRow, 150); }, { passive: true });
		on(window, 'load', markLastRow);
	}
	// Any element that should jump to the grid with a filter (category cards, "view all", festival button).
	on(d, 'click', function (e) {
		var a = e.target.closest ? e.target.closest('[data-tdl-filter]') : null;
		if (!a || !gridSec) { return; }
		e.preventDefault();
		var f = a.getAttribute('data-tdl-filter');
		setChip(f);
		if (f !== gridState.filter) { loadGrid(f, 1); }
		gridSec.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
	});

	/* ---------------- cart ---------------- */
	function setCount(n) {
		qsa('.tdl-cart-count').forEach(function (el) {
			el.setAttribute('data-count', n);
			el.textContent = fa(n);
			el.classList.remove('is-bump'); void el.offsetWidth; el.classList.add('is-bump');
		});
	}
	function cartTarget() {
		var c = qs('[data-tdl-cart]', root);
		return c && c.getBoundingClientRect().width ? c : null;
	}
	function fly(img) {
		var target = cartTarget();
		if (!img || !target || reduce || !img.animate) { return; }
		var a = img.getBoundingClientRect(), b = target.getBoundingClientRect();
		if (!a.width) { return; }
		var clone = img.cloneNode(false);
		clone.removeAttribute('srcset'); clone.removeAttribute('sizes'); clone.removeAttribute('loading');
		clone.src = img.currentSrc || img.src;
		clone.className = 'tdl-fly';
		clone.style.cssText = 'left:' + a.left + 'px;top:' + a.top + 'px;width:' + a.width + 'px;height:' + a.height + 'px';
		d.body.appendChild(clone);
		var dx = (b.left + b.width / 2) - (a.left + a.width / 2);
		var dy = (b.top + b.height / 2) - (a.top + a.height / 2);
		clone.animate([
			{ transform: 'translate(0,0) scale(1)', opacity: 1, borderRadius: '18px' },
			{ transform: 'translate(' + dx * 0.55 + 'px,' + (dy * 0.55 - 80) + 'px) scale(.5)', opacity: 0.95, offset: 0.55 },
			{ transform: 'translate(' + dx + 'px,' + dy + 'px) scale(.08)', opacity: 0.2, borderRadius: '50%' }
		], { duration: 850, easing: 'cubic-bezier(.5,0,.3,1)' }).onfinish = function () { clone.remove(); };
	}
	function notifyTheme(data, btn) {
		var jq = window.jQuery;
		if (jq && data) {
			try { jq(d.body).trigger('added_to_cart', [data.fragments || {}, data.cart_hash || '', jq(btn)]); } catch (err) { /* noop */ }
		}
	}
	function addToCart(payload, btn, img) {
		if (btn) { btn.classList.add('is-loading'); btn.disabled = true; }
		return request('tdl_add', payload, 'POST').then(function (res) {
			if (btn) { btn.classList.remove('is-loading'); btn.disabled = false; }
			if (!res || !res.success) {
				var data = (res && res.data) || {};
				if (data.redirect) { window.location.href = data.redirect; return false; }
				toast(data.message || T.error, true);
				return false;
			}
			fly(img);
			setTimeout(function () { setCount(res.data.count); }, img && !reduce ? 700 : 0);
			if (btn) {
				var label = qs('.vt-add-l', btn) || btn;
				var prev = label.textContent;
				btn.classList.add('is-added');
				label.textContent = T.added + ' ✓';
				setTimeout(function () { btn.classList.remove('is-added'); label.textContent = prev; }, 1800);
			}
			toast(res.data.message);
			notifyTheme(res.data, btn);
			return true;
		}).catch(function () {
			if (btn) { btn.classList.remove('is-loading'); btn.disabled = false; }
			toast(T.error, true);
			return false;
		});
	}
	on(d, 'click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-tdl-add]') : null;
		if (!btn || btn.disabled) { return; }
		e.preventDefault();
		var id = btn.getAttribute('data-tdl-add');
		var card = btn.closest('.vt-card');
		var img = card ? qs('.vt-card-media img', card) : null;
		if (btn.getAttribute('data-mode') === 'popup') { openQV(id, btn); return; }
		addToCart({ product_id: id, quantity: 1 }, btn, img);
	});

	/* ---------------- quick-select modal (variable products) ---------------- */
	var qv = d.getElementById('tdl-qv');
	var qvBody = qv ? qs('[data-tdl-qv-body]', qv) : null;
	var qvBox = qv ? qs('.tdl-qv', qv) : null;
	var qvReturn = null;
	function closeQV() {
		if (!qv || qv.hidden) { return; }
		qv.hidden = true;
		d.documentElement.classList.remove('tdl-lock');
		if (qvReturn && qvReturn.focus) { qvReturn.focus(); }
	}
	function openQV(id, from) {
		if (!qv) { return; }
		qvReturn = from;
		qvBody.innerHTML = '<div class="tdl-qv-skel" aria-label="' + esc(T.loading) + '"></div>';
		qv.hidden = false;
		d.documentElement.classList.add('tdl-lock');
		qvBox.focus();
		request('tdl_qv', { id: id }).then(function (res) {
			if (!res || !res.success) { closeQV(); toast((res && res.data && res.data.message) || T.error, true); return; }
			renderQV(res.data);
		}).catch(function () { closeQV(); toast(T.error, true); });
	}
	function renderQV(p) {
		var sel = {};
		var qty = 1;
		var html = '<div class="tdl-qv-grid">' +
			'<div class="vt-well tdl-qv-media"><img src="' + esc(p.image) + '" alt="' + esc(p.name) + '" data-qv-img></div>' +
			'<div class="tdl-qv-info"><div class="tdl-qv-head">' +
			(p.tag ? '<span class="vt-glass tdl-qv-tag">' + esc(p.tag) + '</span>' : '') +
			'<h3 id="tdl-qv-title">' + esc(p.name) + '</h3>' +
			'<div class="tdl-qv-price"><b data-qv-price>' + esc(p.price) + '</b><small>' + esc(p.currency) + '</small><del class="vt-old" data-qv-old>' + esc(p.old || '') + '</del></div>' +
			'</div><div class="tdl-qv-rest">';
		p.attributes.forEach(function (a) {
			html += '<fieldset class="tdl-qv-attr"><legend>' + esc(a.label) + '</legend><div class="tdl-qv-opts" role="radiogroup" aria-label="' + esc(a.label) + '">';
			a.options.forEach(function (o) {
				html += '<button type="button" class="vt-chip" role="radio" aria-checked="false" data-k="' + esc(a.key) + '" data-v="' + esc(o.value) + '">' + esc(o.label) + '</button>';
			});
			html += '</div></fieldset>';
		});
		html += '<div class="tdl-qv-stock" data-qv-stock aria-live="polite"></div><div class="tdl-qv-msg" data-qv-msg aria-live="assertive"></div>' +
			'<div class="tdl-qv-actions"><div class="vt-well tdl-qty"><button type="button" data-q="1" aria-label="افزایش تعداد">+</button><span data-qv-qty>' + fa(1) + '</span><button type="button" data-q="-1" aria-label="کاهش تعداد">−</button></div>' +
			'<button type="button" class="vt-btn vt-add" data-qv-add><span class="vt-add-l">' + esc(T.add) + '</span></button></div>' +
			'<a class="tdl-qv-link" href="' + esc(p.url) + '">' + esc(T.details) + ' ←</a>' +
			'</div></div></div>';
		qvBody.innerHTML = html;

		var keys = p.attributes.map(function (a) { return a.key; });
		var addBtn = qs('[data-qv-add]', qvBody);
		var priceEl = qs('[data-qv-price]', qvBody), oldEl = qs('[data-qv-old]', qvBody);
		var stockEl = qs('[data-qv-stock]', qvBody), msgEl = qs('[data-qv-msg]', qvBody), imgEl = qs('[data-qv-img]', qvBody);

		function fits(v, ignoreKey) {
			return keys.every(function (k) {
				if (k === ignoreKey || !sel[k]) { return true; }
				return v.attrs[k] === '' || v.attrs[k] === sel[k];
			});
		}
		function match() {
			if (keys.some(function (k) { return !sel[k]; })) { return null; }
			for (var i = 0; i < p.variations.length; i++) { if (fits(p.variations[i])) { return p.variations[i]; } }
			return false;
		}
		function update() {
			qsa('[data-k]', qvBody).forEach(function (b) {
				var k = b.getAttribute('data-k'), v = b.getAttribute('data-v');
				var picked = sel[k] === v;
				b.classList.toggle('is-on', picked);
				b.setAttribute('aria-checked', picked ? 'true' : 'false');
				var avail = p.variations.some(function (vr) { return vr.in_stock && (vr.attrs[k] === '' || vr.attrs[k] === v) && fits(vr, k); });
				b.classList.toggle('is-na', !avail);
			});
			var m = match();
			msgEl.textContent = '';
			if (m) {
				priceEl.textContent = m.price; oldEl.textContent = m.old || '';
				stockEl.textContent = m.in_stock ? (m.stock || '') : T.out;
				if (m.image && imgEl.getAttribute('src') !== m.image) { imgEl.style.opacity = '.3'; imgEl.onload = function () { imgEl.style.opacity = '1'; }; imgEl.src = m.image; }
				addBtn.disabled = !m.in_stock;
			} else {
				priceEl.textContent = p.price; oldEl.textContent = p.old || ''; stockEl.textContent = '';
				if (m === false) { msgEl.textContent = T.unavail; }
				addBtn.disabled = false;
			}
		}
		qsa('[data-k]', qvBody).forEach(function (b) {
			on(b, 'click', function () {
				var k = b.getAttribute('data-k'), v = b.getAttribute('data-v');
				sel[k] = sel[k] === v ? '' : v;
				update();
			});
		});
		qsa('[data-q]', qvBody).forEach(function (b) {
			on(b, 'click', function () {
				qty = Math.max(1, Math.min(99, qty + parseInt(b.getAttribute('data-q'), 10)));
				qs('[data-qv-qty]', qvBody).textContent = fa(qty);
			});
		});
		// Auto-select attributes that have a single option.
		p.attributes.forEach(function (a) { if (a.options.length === 1) { sel[a.key] = a.options[0].value; } });
		update();

		on(addBtn, 'click', function () {
			var m = match();
			if (!m) { msgEl.textContent = m === false ? T.unavail : T.pick; return; }
			var attrs = {};
			keys.forEach(function (k) { attrs[k] = sel[k]; });
			addToCart({ product_id: p.id, variation_id: m.id, quantity: qty, attributes: attrs }, addBtn, imgEl).then(function (ok) {
				if (ok) { setTimeout(closeQV, 450); }
			});
		});
		var firstChip = qs('[data-k]', qvBody);
		if (firstChip) { firstChip.focus(); }
	}
	if (qv) {
		qsa('[data-tdl-close="qv"]', qv).forEach(function (el) { on(el, 'click', closeQV); });
		on(qv, 'keydown', function (e) { trap(qv, e); });
	}

	/* ---------------- wishlist ---------------- */
	var wishSet = {};
	var wishNonce = '';
	var LS = 'tdl_wish';
	function lsRead() { try { return JSON.parse(localStorage.getItem(LS) || '[]') || []; } catch (e) { return []; } }
	function lsWrite(ids) { try { localStorage.setItem(LS, JSON.stringify(ids.slice(-200))); } catch (e) { /* storage blocked */ } }
	function paintWishes(scope) {
		qsa('[data-tdl-wish]', scope || d).forEach(function (b) {
			var onn = !!wishSet[b.getAttribute('data-tdl-wish')];
			b.classList.toggle('is-on', onn);
			b.setAttribute('aria-pressed', onn ? 'true' : 'false');
		});
	}
	lsRead().forEach(function (id) { wishSet[id] = true; });
	paintWishes();
	on(d, 'click', function (e) {
		var b = e.target.closest ? e.target.closest('[data-tdl-wish]') : null;
		if (!b) { return; }
		e.preventDefault();
		var id = b.getAttribute('data-tdl-wish');
		var next = !wishSet[id];
		wishSet[id] = next;
		paintWishes();
		toast(next ? T.wishOn : T.wishOff);
		if (C.wish === 'local') {
			lsWrite(Object.keys(wishSet).filter(function (k) { return wishSet[k]; }));
			return;
		}
		request('tdl_wish', { id: id, nonce: wishNonce }, 'POST').then(function (res) {
			if (res && res.success && res.data.state !== null && typeof res.data.state !== 'undefined') {
				wishSet[id] = !!res.data.state;
				paintWishes();
			} else if (res && res.success) {
				lsWrite(Object.keys(wishSet).filter(function (k) { return wishSet[k]; }));
			}
		});
	});

	/* ---------------- per-visitor state (cache-safe cart count & wishlist) ---------------- */
	if (C.wish !== 'local' || /woocommerce_items_in_cart=/.test(d.cookie)) {
		request('tdl_state').then(function (res) {
			if (!res || !res.success) { return; }
			var cnt = qsa('.tdl-cart-count')[0];
			if (cnt && String(res.data.count) !== cnt.getAttribute('data-count')) {
				qsa('.tdl-cart-count').forEach(function (el) { el.setAttribute('data-count', res.data.count); el.textContent = fa(res.data.count); });
			}
			wishNonce = res.data.nonce || '';
			if (res.data.provider !== 'local') {
				wishSet = {};
				(res.data.wish || []).forEach(function (id) { wishSet[id] = true; });
				paintWishes();
			}
		});
	}

	/* ---------------- FAQ smooth accordion ---------------- */
	qsa('.vt-details', root).forEach(function (det) {
		var sum = qs('summary', det), body = qs('.vt-details-body', det);
		if (!sum || !body || reduce || !body.animate) { return; }
		var anim = null;
		on(sum, 'click', function (e) {
			e.preventDefault();
			if (anim) { anim.cancel(); }
			if (det.open) {
				var h = body.offsetHeight;
				anim = body.animate([{ height: h + 'px', opacity: 1 }, { height: '0px', opacity: 0 }], { duration: 320, easing: 'cubic-bezier(.4,0,.2,1)' });
				anim.onfinish = function () { det.open = false; anim = null; };
			} else {
				det.open = true;
				var h2 = body.offsetHeight;
				anim = body.animate([{ height: '0px', opacity: 0 }, { height: h2 + 'px', opacity: 1 }], { duration: 420, easing: 'cubic-bezier(.2,.8,.2,1)' });
				anim.onfinish = function () { anim = null; };
			}
		});
	});

	/* ---------------- hero: full-quality animation (plays immediately) + tilt ---------------- */
	var heroMedia = qs('[data-tdl-hero]', root);
	var heroGif = heroMedia ? qs('.vt-hero-gif', heroMedia) : null;
	if (heroGif && heroGif.complete && heroGif.naturalWidth) { heroMedia.classList.add('is-ready'); }
	else if (heroGif) { on(heroGif, 'load', function () { heroMedia.classList.add('is-ready'); }); }

	var tilt = qs('[data-tdl-tilt]', root);
	if (tilt && !reduce && window.matchMedia && window.matchMedia('(hover:hover) and (pointer:fine)').matches) {
		var wrap = qs('.vt-ped-wrap', tilt);
		on(tilt, 'pointermove', function (e) {
			var r = tilt.getBoundingClientRect();
			var x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
			wrap.style.setProperty('--ry', (x * 8).toFixed(2) + 'deg');
			wrap.style.setProperty('--rx', (-y * 6).toFixed(2) + 'deg');
		});
		on(tilt, 'pointerleave', function () { wrap.style.setProperty('--ry', '0deg'); wrap.style.setProperty('--rx', '0deg'); });
	}

	/* ---------------- global keys ---------------- */
	on(d, 'keydown', function (e) {
		if (e.key !== 'Escape') { return; }
		if (qv && !qv.hidden) { closeQV(); return; }
		if (drawer && !drawer.hidden) { closeDrawer(); return; }
		setSearch(false);
	});
}());
