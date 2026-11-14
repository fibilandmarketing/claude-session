/* لندینگ شب یلدا — front-end behaviour (vanilla, no dependencies). */
(function () {
	'use strict';

	var root = document.getElementById('yl');
	var dataEl = document.getElementById('yl-data');
	if (!root || !dataEl) return;
	var C;
	try { C = JSON.parse(dataEl.textContent); } catch (e) { return; }
	var T = C.t || {};
	var RM = !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
	var FA = '۰۱۲۳۴۵۶۷۸۹';

	/* ---------------- helpers ---------------- */
	function fa(n) { return String(n).replace(/\d/g, function (d) { return FA[d]; }); }
	function en(s) {
		return String(s == null ? '' : s)
			.replace(/[۰-۹]/g, function (c) { return FA.indexOf(c); })
			.replace(/[٠-٩]/g, function (c) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(c); });
	}
	function money(n) { return fa(String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, '٬')); }
	function pad(n) { return fa(n < 10 ? '0' + n : String(n)); }
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function $(sel, ctx) { return (ctx || root).querySelector(sel); }
	function $$(sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); }
	function smooth(el, block) {
		if (el) el.scrollIntoView({ behavior: RM ? 'auto' : 'smooth', block: block || 'start' });
	}
	function stagger(els, dy, dur, step) {
		if (RM) return;
		els.forEach(function (el, i) {
			if (el.animate) el.animate([{ opacity: 0, transform: 'translateY(' + dy + 'px)' }, { opacity: 1, transform: 'none' }], { duration: dur, delay: i * step, easing: 'ease-out', fill: 'backwards' });
		});
	}
	function normTel(t) {
		t = en(t).replace(/[\s\-()]/g, '');
		if (/^\+?98\d{10}$/.test(t)) t = '0' + t.replace(/^\+?98/, '');
		return t;
	}
	function post(data) {
		var fd = new FormData();
		fd.append('action', 'yl_lead');
		fd.append('website', '');
		Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
		return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (!r.ok || !j.ok) throw new Error(j.msg || 'ارسال درخواست ناموفق بود؛ لطفاً دوباره تلاش کنید.');
				return j;
			});
		}, function () { throw new Error('اتصال برقرار نشد؛ اینترنت را بررسی و دوباره تلاش کنید.'); });
	}

	/* ---------------- state ---------------- */
	var S = {
		people: C.people || 50, step: 0, ans: {}, err: '', sent: false, results: [], swapSlot: 0,
		quick: null, view: 'price', mode: 'invoice', co: '', who: '', tel: '',
		heroCta: true, inBuild: false, modal: false, busy: false
	};
	var steps = C.steps || [];

	/* ---------------- countdowns ---------------- */
	var cdEls = { d: $('[data-cd="d"]'), h: $('[data-cd="h"]'), m: $('[data-cd="m"]') };
	var clockEl = $('[data-clock]');
	var endEl = $('[data-end-title]');
	var last = {};
	function setText(key, el, v) {
		if (el && last[key] !== v) { el.textContent = v; last[key] = v; }
	}
	function tick() {
		var now = Date.now();
		var left = Math.max(0, C.end - now);
		var d = Math.floor(left / 864e5), h = Math.floor((left % 864e5) / 36e5), m = Math.floor((left % 36e5) / 6e4);
		setText('d', cdEls.d, fa(d));
		setText('h', cdEls.h, fa(h));
		setText('m', cdEls.m, fa(m));
		if (endEl) setText('e', endEl, left > 0 ? String(T.endTitle || '').replace('{days}', fa(d)) : (T.endOver || ''));
		if (clockEl) {
			var ms = 36e5 - ((now + 126e5) % 36e5); // to the next full hour, Tehran time
			setText('c', clockEl, pad(Math.floor(ms / 6e4) % 60) + ':' + pad(Math.floor((ms % 6e4) / 1e3)));
		}
	}
	tick();
	setInterval(tick, 1000);

	/* ---------------- parallax drift ---------------- */
	if (C.drift && window.matchMedia && matchMedia('(hover: hover) and (pointer: fine)').matches) {
		var driftEls = $$('.yl-hs-w, .yl-fl, .yl-gf-wm');
		var drift = [0, 0], frame = 0;
		var setDrift = function (x, y) {
			drift = [x, y];
			if (frame) return;
			frame = requestAnimationFrame(function () {
				frame = 0;
				var mx = drift[0].toFixed(3), my = drift[1].toFixed(3);
				driftEls.forEach(function (el) { el.style.setProperty('--mx', mx); el.style.setProperty('--my', my); });
			});
		};
		root.addEventListener('mousemove', function (e) {
			var r = Math.min(innerWidth, innerHeight) / 2;
			var x = (e.clientX - innerWidth / 2) / r, y = (e.clientY - innerHeight / 2) / r;
			var dd = Math.hypot(x, y);
			if (dd > 1) { x /= dd; y /= dd; }
			setDrift(x, y);
		}, { passive: true });
		root.addEventListener('mouseleave', function () { setDrift(0, 0); });
	}

	/* ---------------- reveal on scroll ---------------- */
	if (C.reveal && !RM && 'IntersectionObserver' in window) {
		root.classList.add('yl-js');
		var rio = new IntersectionObserver(function (list) {
			list.forEach(function (en2) {
				if (en2.isIntersecting || en2.boundingClientRect.top < 0) {
					en2.target.classList.add('is-in');
					rio.unobserve(en2.target);
				}
			});
		}, { rootMargin: '0px 0px -6% 0px' });
		$$('.yl-rv').forEach(function (el) { rio.observe(el); });
	}

	/* ---------------- sticky mobile bar ---------------- */
	var bar = document.getElementById('yl-mb');
	function updBar() {
		if (!bar) return;
		bar.classList.toggle('is-show', !S.modal && !S.heroCta && !S.inBuild);
		var t = $('[data-bar-t]', bar), s = $('[data-bar-s]', bar), b = $('[data-go-build]', bar);
		t.textContent = S.sent ? T.barT2 : T.barT;
		s.textContent = S.sent ? T.barS2 : T.barS;
		b.textContent = S.sent ? T.barCta2 : T.barCta;
	}
	if ('IntersectionObserver' in window) {
		var heroCta = document.getElementById('yl-hero-cta');
		var build = document.getElementById('build');
		if (heroCta) new IntersectionObserver(function (l) { S.heroCta = l[l.length - 1].isIntersecting; updBar(); }).observe(heroCta);
		else S.heroCta = false;
		if (build) new IntersectionObserver(function (l) { S.inBuild = l[l.length - 1].isIntersecting; updBar(); }, { rootMargin: '0px 0px -55% 0px' }).observe(build);
	}

	/* ---------------- 5-step builder ---------------- */
	var card = document.getElementById('yl-gf-card');
	var body = document.getElementById('yl-gf-body');
	var total = steps.length + 1;

	function chosen(i) {
		var v = S.ans[i];
		return steps[i] && steps[i].multi ? (v || []) : (v ? [v] : []);
	}
	function optOf(i, k) {
		var o = null;
		(steps[i].opts || []).forEach(function (x) { if (x.k === k) o = x; });
		return o;
	}
	function fld(key, label, type, ac, extra) {
		return '<label class="yl-fld">' + esc(label) + ' <span class="yl-req" aria-hidden="true">*</span>' +
			'<input' + (key === 'who' ? ' id="yl-gf-q"' : '') + ' type="' + type + '" autocomplete="' + ac + '" data-f="' + key + '" value="' + esc(S[key]) + '"' + (extra || '') + '></label>';
	}
	function renderStep() {
		if (!body) return;
		var i = S.step, st = steps[i];
		var h = '<p class="yl-gf-s">گام ' + fa(i + 1) + ' از ' + fa(total) + ': ' + esc(st ? st.label : T.info) + '</p>';
		if (st) {
			var sel = chosen(i);
			h += '<p id="yl-gf-q" class="yl-gf-q" tabindex="-1">' + esc(st.q) + ' ' + (st.hint ? '<small>' + esc(st.hint) + '</small> ' : '') + '<span class="yl-req" aria-hidden="true">*</span></p>';
			h += '<div class="yl-gf-os" role="' + (st.multi ? 'group' : 'radiogroup') + '" aria-labelledby="yl-gf-q">';
			st.opts.forEach(function (o) {
				var on = sel.indexOf(o.k) > -1;
				h += '<label class="yl-gf-o' + (on ? ' is-on' : '') + '"><input type="' + (st.multi ? 'checkbox' : 'radio') + '" name="yl-s' + i + '" value="' + esc(o.k) + '"' + (on ? ' checked' : '') + '><span>' + esc(o.label) + '</span></label>';
			});
			h += '</div>';
		} else {
			h += fld('who', T.fName, 'text', 'name');
			h += fld('tel', T.fTel, 'tel', 'tel', ' inputmode="tel" placeholder="۰۹۱۲۳۴۵۶۷۸۹"');
			h += fld('co', T.fCo, 'text', 'organization');
		}
		h += '<p class="yl-gf-e" role="alert"' + (S.err ? '' : ' hidden') + '>' + esc(S.err) + '</p>';
		h += '<div class="yl-gf-n">';
		if (i > 0) h += '<button class="yl-gf-b" type="button" data-gf="prev">' + esc(T.prev) + '</button>';
		h += st ? '<button class="yl-cta" type="button" data-gf="next">' + esc(T.next) + '</button>'
			: '<button class="yl-cta" type="button" data-gf="send">' + esc(T.send) + '</button>';
		h += '</div>';
		body.innerHTML = h;
		var pb = $('.yl-gf-p', card);
		if (pb) {
			pb.setAttribute('aria-valuenow', i + 1);
			pb.firstElementChild.style.width = ((i + 1) / total * 100) + '%';
		}
	}
	function gfErr(msg) {
		S.err = msg || '';
		var e = $('.yl-gf-e', card);
		if (e) { e.textContent = S.err; e.hidden = !S.err; }
	}
	function gfMove(n) {
		S.step = Math.max(0, Math.min(total - 1, n));
		S.err = '';
		renderStep();
		setTimeout(function () {
			if (card && card.getBoundingClientRect().top < 0) smooth(card);
			var q = document.getElementById('yl-gf-q');
			if (q) q.focus({ preventScroll: true });
			stagger($$('.yl-gf-o, .yl-gf-c .yl-fld'), 10, 300, 35);
		}, 0);
	}
	function gfNext() {
		var st = steps[S.step];
		if (!chosen(S.step).length) return gfErr(st.multi ? 'دست‌کم یک گزینه را انتخاب کنید.' : 'یک گزینه را انتخاب کنید.');
		gfMove(S.step + 1);
	}
	function stepIndex(type) {
		for (var i = 0; i < steps.length; i++) if (steps[i].type === type) return i;
		return -1;
	}
	function matchPacks() {
		var packs = C.packs || [], N = C.results || 3;
		var bi = stepIndex('budget'), mi = stepIndex('mood');
		var range = null;
		if (bi > -1 && S.ans[bi]) {
			var o = optOf(bi, S.ans[bi]);
			if (o) range = [o.min || 0, o.max > 0 ? o.max : Infinity];
		}
		var moods = mi > -1 ? (S.ans[mi] || []) : [];
		var sc = packs.map(function (p, i) {
			return { i: i, v: moods.filter(function (m) { return (p.tags || []).indexOf(m) > -1; }).length, ok: !range || (p.price > range[0] && p.price <= range[1]) };
		});
		var pick = sc.filter(function (x) { return x.ok; }).sort(function (a, b) { return b.v - a.v || a.i - b.i; }).slice(0, N).map(function (x) { return x.i; });
		if (pick.length < N && range) {
			var mid = range[1] === Infinity ? range[0] : (range[0] + range[1]) / 2;
			var rest = sc.filter(function (x) { return pick.indexOf(x.i) < 0; }).sort(function (a, b) {
				return Math.abs(packs[a.i].price - mid) - Math.abs(packs[b.i].price - mid);
			});
			pick = pick.concat(rest.slice(0, N - pick.length).map(function (x) { return x.i; }));
		}
		return pick;
	}
	function renderPacks(flash) {
		var box = document.getElementById('yl-pks');
		if (!box) return;
		box.innerHTML = S.results.map(function (pi, i) {
			var p = C.packs[pi];
			var img = '<img src="' + esc(p.img) + '" alt="' + esc(p.name) + '" width="300" height="300" loading="lazy" decoding="async">';
			return '<article class="yl-pk yl-neu">' +
				(p.url ? '<a class="yl-pk-i" href="' + esc(p.url) + '" target="_blank" rel="noopener" aria-label="جزئیات ' + esc(p.name) + '">' + img + '</a>' : '<span class="yl-pk-i">' + img + '</span>') +
				'<div class="yl-pk-b"><h3>' + esc(p.name) + '</h3><p class="yl-pk-p">' + money(p.price) + ' تومان</p></div>' +
				'<div class="yl-pk-c"><button class="yl-cta" type="button" data-pk-order="' + i + '">' + esc(T.pkOrder) + '</button>' +
				'<button class="yl-gl" type="button" data-pk-swap="' + i + '">' + esc(T.pkSwap) + '</button>' +
				(p.url ? '<a class="yl-gl yl-pk-d" href="' + esc(p.url) + '" target="_blank" rel="noopener">' + esc(T.pkMore) + '</a>' : '') +
				'</div></article>';
		}).join('');
		if (flash) setTimeout(function () { stagger($$('.yl-pk'), 14, 380, 60); }, 0);
	}
	function showResults(on) {
		var gf = document.getElementById('yl-gf'), gr = document.getElementById('yl-gr'), pw = document.getElementById('yl-pk-wrap');
		if (gf) gf.hidden = on;
		$$('[data-gf-only]').forEach(function (el) { el.hidden = on; });
		if (gr) gr.hidden = !on;
		if (pw) pw.hidden = !on || !S.results.length;
		updBar();
	}
	function gfSend(btn) {
		if (S.busy) return;
		var tel = normTel(S.tel);
		if (!S.who.trim()) return gfErr('نام و نام خانوادگی را وارد کنید.');
		if (!/^09\d{9}$/.test(tel)) return gfErr('شماره موبایل را با ۰۹ و ۱۱ رقم وارد کنید.');
		if (!S.co.trim()) return gfErr('نام سازمان را وارد کنید.');
		gfErr('');
		var results = matchPacks();
		var answers = steps.map(function (st, i) {
			return { q: st.label, a: chosen(i).map(function (k) { var o = optOf(i, k); return o ? o.label : k; }) };
		});
		S.busy = true;
		btn.classList.add('yl-busy');
		post({
			type: 'builder', name: S.who, tel: tel, co: S.co,
			answers: JSON.stringify(answers),
			packs: results.map(function (i) { return C.packs[i].name; }).join('، ')
		}).then(function () {
			S.sent = true;
			S.results = results;
			var chips = [];
			steps.forEach(function (st, i) {
				if (!st.chip) return;
				chosen(i).forEach(function (k) { var o = optOf(i, k); if (o) chips.push(o.label); });
			});
			var cbox = document.getElementById('yl-gr-c');
			if (cbox) cbox.innerHTML = chips.map(function (c) { return '<span class="yl-gr-t">' + esc(c) + '</span>'; }).join('');
			renderPacks(true);
			showResults(true);
			setTimeout(function () { smooth(document.getElementById('yl-gr')); }, 60);
		}).catch(function (e) {
			gfErr(e.message);
		}).then(function () {
			S.busy = false;
			btn.classList.remove('yl-busy');
		});
	}
	if (card) {
		card.addEventListener('change', function (e) {
			var inp = e.target;
			if (!inp.closest || !inp.closest('.yl-gf-o')) return;
			var i = S.step, st = steps[i];
			if (st.multi) {
				var cur = (S.ans[i] || []).slice();
				var at = cur.indexOf(inp.value);
				if (inp.checked && at < 0) cur.push(inp.value);
				if (!inp.checked && at > -1) cur.splice(at, 1);
				S.ans[i] = cur;
			} else {
				S.ans[i] = inp.value;
			}
			$$('.yl-gf-o', card).forEach(function (l) { l.classList.toggle('is-on', l.querySelector('input').checked); });
			gfErr('');
		});
		card.addEventListener('input', function (e) {
			var k = e.target.getAttribute && e.target.getAttribute('data-f');
			if (k) S[k] = e.target.value;
		});
		card.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && e.target.matches && e.target.matches('input[data-f]')) {
				e.preventDefault();
				var b = $('[data-gf="send"]', card);
				if (b) gfSend(b);
			}
		});
	}

	/* ---------------- modals ---------------- */
	var layer = document.getElementById('yl-layer');
	var lastFocus = null;
	var X = '<button class="yl-x" type="button" aria-label="بستن" data-m="close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M5 5l14 14M19 5 5 19"/></svg></button>';

	function openLayer(html) {
		if (!S.modal) lastFocus = document.activeElement;
		S.modal = true;
		layer.innerHTML = '<div class="yl-ov"><div class="yl-md" role="dialog" aria-modal="true" aria-labelledby="yl-md-t">' + X + html + '</div></div>';
		document.documentElement.classList.add('yl-lock');
		updBar();
		setTimeout(function () { var b = $('.yl-x', layer); if (b) b.focus(); }, 60);
	}
	function closeLayer() {
		if (!S.modal) return;
		S.modal = false;
		layer.innerHTML = '';
		document.documentElement.classList.remove('yl-lock');
		updBar();
		if (lastFocus && lastFocus.focus && document.contains(lastFocus)) lastFocus.focus({ preventScroll: true });
	}
	function swapMd(html) {
		var md = $('.yl-md', layer);
		if (!md) return openLayer(html);
		md.innerHTML = X + html;
		setTimeout(function () { var f = $('input[data-f], .yl-x', md); if (f) f.focus(); }, 30);
	}

	/* price */
	function tierOf(p) {
		var t = (C.tiers || []).slice().sort(function (a, b) { return b[0] - a[0]; });
		for (var i = 0; i < t.length; i++) if (p >= t[i][0]) return t[i][1];
		return 0;
	}
	function nextTier(p) {
		var t = (C.tiers || []).slice().sort(function (a, b) { return a[0] - b[0]; });
		for (var i = 0; i < t.length; i++) if (p < t[i][0]) return t[i];
		return null;
	}
	function calc() {
		var off = tierOf(S.people), pu = S.quick ? S.quick.price : 0;
		var unit = pu * (1 - off / 100);
		return { off: off, pu: pu, total: unit * S.people, saved: (pu - unit) * S.people };
	}
	function setPeople(v, fromInput) {
		S.people = Math.min(C.max || 5000, Math.max(C.min || 1, v));
		var md = $('.yl-md', layer);
		if (!md) return;
		var inp = $('#yl-people', md);
		if (inp && !fromInput) inp.value = fa(S.people);
		$$('[data-q-set]', md).forEach(function (b) {
			var on = +b.getAttribute('data-q-set') === S.people;
			b.classList.toggle('is-on', on);
			b.setAttribute('aria-pressed', on ? 'true' : 'false');
		});
		var c = calc();
		var put = function (k, v2) { var el = $('[data-b="' + k + '"]', md); if (el) el.textContent = v2; };
		put('unit', money(c.pu) + ' تومان');
		put('off', fa(c.off) + '٪');
		put('saved', money(c.saved) + ' تومان');
		put('total', money(c.total) + ' تومان');
		var nt = nextTier(S.people);
		put('nudge', !(C.tiers || []).length ? '' : nt
			? 'با ' + fa(nt[0] - S.people) + ' نفر بیشتر، تخفیف تعداد به ' + fa(nt[1]) + '٪ می‌رسد.'
			: 'بیشترین پله‌ی تخفیف تعداد برای سفارش شما فعال است.');
	}
	function canPay(q) {
		return !!((C.cart && q.pid && q.add) || C.payUrl || q.url);
	}
	function priceHtml() {
		var q = S.quick;
		var h = '<h2 id="yl-md-t" class="yl-hd">' + esc(T.mTitle) + '</h2>';
		if (q.img) h += '<div class="yl-qp"><img src="' + esc(q.img) + '" alt="" width="300" height="300"><b>' + esc(q.name) + '</b></div>';
		else h += '<div class="yl-qp"><b>' + esc(q.name) + '</b></div>';
		h += '<label class="yl-ql" for="yl-people">' + esc(T.qLabel) + '</label><div class="yl-qr"><div class="yl-num">' +
			'<button type="button" aria-label="کم کردن نفرات" data-q="-1">−</button>' +
			'<input id="yl-people" inputmode="numeric" autocomplete="off" value="' + fa(S.people) + '">' +
			'<button type="button" aria-label="زیاد کردن نفرات" data-q="1">+</button></div>';
		if ((C.presets || []).length) {
			h += '<div class="yl-chs">' + C.presets.map(function (n) {
				return '<button class="yl-ch" type="button" aria-pressed="false" data-q-set="' + n + '">' + fa(n) + ' نفر</button>';
			}).join('') + '</div>';
		}
		h += '</div><div class="yl-bill" aria-live="polite">' +
			'<div><span>قیمت هر پک</span><b data-b="unit"></b></div>' +
			'<div><span>تخفیف تعداد</span><b data-b="off"></b></div>' +
			'<div><span>صرفه‌جویی شما</span><b data-b="saved"></b></div>' +
			'<div class="yl-tot"><span>مبلغ نهایی</span><b data-b="total"></b></div></div>' +
			'<p class="yl-ng" data-b="nudge"></p>' +
			(T.note ? '<span class="yl-smp">' + esc(T.note) + '</span>' : '') +
			'<div class="yl-ma">' +
			(canPay(q) ? '<button class="yl-cta" type="button" data-m="pay">' + esc(T.pay) + '</button>' : '') +
			'<button class="yl-gl" type="button" data-m="invoice">' + esc(T.invoice) + '</button>' +
			'<button class="yl-gl" type="button" data-m="call">' + esc(T.call) + '</button></div>';
		return h;
	}
	function showPrice() {
		S.view = 'price';
		swapMd(priceHtml());
		setPeople(S.people);
	}
	function openQuick(item) {
		if (!item) return;
		S.quick = item;
		S.view = 'price';
		openLayer(priceHtml());
		setPeople(S.people);
	}
	function formHtml() {
		var inv = S.mode === 'invoice', c = calc();
		return '<h2 id="yl-md-t" class="yl-hd">' + esc(inv ? T.invTitle : T.callTitle) + '</h2>' +
			'<p style="margin-top:6px">' + (inv ? fa(S.people) + ' پک · ' + money(c.total) + ' تومان. پیش‌فاکتور برای شما ارسال می‌شود.' : esc(T.callLead)) + '</p>' +
			'<label class="yl-fld">نام شرکت<input autocomplete="organization" data-f="co" value="' + esc(S.co) + '"></label>' +
			'<label class="yl-fld">نام و نام خانوادگی<input autocomplete="name" data-f="who" value="' + esc(S.who) + '"></label>' +
			'<label class="yl-fld">شماره موبایل<input type="tel" inputmode="tel" autocomplete="tel" dir="ltr" style="text-align:right" data-f="tel" value="' + esc(S.tel) + '"></label>' +
			'<p class="yl-er" role="alert" hidden></p>' +
			'<div class="yl-ma"><button class="yl-cta" type="button" data-m="send">' + esc(inv ? T.invCta : T.callCta) + '</button>' +
			'<button class="yl-gl" type="button" style="grid-column:1/-1" data-m="back">بازگشت به قیمت</button></div>';
	}
	function mdErr(msg) {
		var e = $('.yl-er', layer);
		if (e) { e.textContent = msg; e.hidden = !msg; }
	}
	function sendForm(btn) {
		if (S.busy) return;
		var tel = normTel(S.tel);
		if (!S.co.trim() || !S.who.trim()) return mdErr('نام شرکت و نام خودتان را وارد کنید.');
		if (!/^09\d{9}$/.test(tel)) return mdErr('شماره موبایل را با ۰۹ و ۱۱ رقم وارد کنید.');
		mdErr('');
		var c = calc(), inv = S.mode === 'invoice';
		S.busy = true;
		btn.classList.add('yl-busy');
		post({
			type: S.mode, name: S.who, tel: tel, co: S.co,
			pack: S.quick ? S.quick.name : '', qty: S.people, unit: Math.round(c.pu), off: c.off, total: Math.round(c.total)
		}).then(function () {
			S.view = 'done';
			swapMd('<h2 id="yl-md-t" class="yl-hd">' + esc(T.doneTitle) + '</h2><p style="margin-top:8px">' + esc(inv ? T.invDone : T.callDone) + '</p>' +
				'<div class="yl-ma"><button class="yl-gl" type="button" style="grid-column:1/-1" data-m="close">بازگشت به صفحه</button></div>');
		}).catch(function (e) {
			mdErr(e.message);
		}).then(function () {
			S.busy = false;
			btn.classList.remove('yl-busy');
		});
	}
	function pay(btn) {
		var q = S.quick;
		if (C.cart && q.pid && q.add) {
			btn.classList.add('yl-busy');
			var f = document.createElement('form');
			f.method = 'post';
			f.action = C.cart;
			var add = { 'add-to-cart': q.pid, quantity: S.people, yl: 1 };
			if (q.deal) add.yl_deal = 1;
			Object.keys(add).forEach(function (k) {
				var i = document.createElement('input');
				i.type = 'hidden'; i.name = k; i.value = add[k];
				f.appendChild(i);
			});
			document.body.appendChild(f);
			f.submit();
			return;
		}
		location.href = C.payUrl || q.url;
	}

	/* swap */
	function openSwap(slot) {
		S.swapSlot = slot;
		var base = C.packs[S.results[slot]];
		var list = (C.packs || []).map(function (p, i) { return { p: p, i: i }; })
			.filter(function (x) { return S.results.indexOf(x.i) < 0; })
			.sort(function (a, b) { return Math.abs(a.p.price - base.price) - Math.abs(b.p.price - base.price); })
			.slice(0, 8);
		openLayer('<h2 id="yl-md-t">' + esc(T.swapTitle) + '</h2><p class="yl-sw-l">' + esc(T.swapSub) + '</p><div class="yl-swl">' +
			list.map(function (x) {
				return '<button class="yl-swi" type="button" data-sw="' + x.i + '"><img src="' + esc(x.p.img) + '" alt="" width="300" height="300" loading="lazy"><span><b>' + esc(x.p.name) + '</b>' + money(x.p.price) + ' تومان</span></button>';
			}).join('') + '</div>');
	}

	layer.addEventListener('click', function (e) {
		var t = e.target;
		if (t.classList && t.classList.contains('yl-ov')) return closeLayer();
		var b = t.closest ? t.closest('button') : null;
		if (!b) return;
		var m = b.getAttribute('data-m');
		if (m === 'close') return closeLayer();
		if (m === 'pay') return pay(b);
		if (m === 'invoice' || m === 'call') { S.mode = m; S.view = 'form'; return swapMd(formHtml()); }
		if (m === 'back') return showPrice();
		if (m === 'send') return sendForm(b);
		if (b.hasAttribute('data-q')) return setPeople(S.people + (+b.getAttribute('data-q')) * (C.step || 5));
		if (b.hasAttribute('data-q-set')) return setPeople(+b.getAttribute('data-q-set'));
		if (b.hasAttribute('data-sw')) {
			S.results[S.swapSlot] = +b.getAttribute('data-sw');
			closeLayer();
			renderPacks(true);
		}
	});
	layer.addEventListener('input', function (e) {
		var t = e.target;
		var k = t.getAttribute('data-f');
		if (k) { S[k] = t.value; return; }
		if (t.id === 'yl-people') {
			var v = parseInt(en(t.value), 10);
			setPeople(isNaN(v) ? 1 : v, true);
		}
	});
	layer.addEventListener('change', function (e) {
		if (e.target.id === 'yl-people') e.target.value = fa(S.people);
	});
	document.addEventListener('keydown', function (e) {
		if (S.modal && e.key === 'Escape') closeLayer();
	});
	layer.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && e.target.matches && e.target.matches('input[data-f]')) {
			var s = $('[data-m="send"]', layer);
			if (s) { e.preventDefault(); sendForm(s); }
		}
		if (e.key === 'Tab') {
			var f = $$('button, input, a[href]', layer).filter(function (x) { return !x.disabled && x.offsetParent !== null; });
			if (!f.length) return;
			if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
			else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
		}
	});

	/* ---------------- fal-e Hafez ---------------- */
	var stage = $('.yl-fst'), play = document.getElementById('yl-fal-play'), fres = document.getElementById('yl-fres');
	var falIdx = -1, falT = [];
	function falSet(ph) { if (stage) stage.setAttribute('data-ph', ph); }
	var falSrv = -1;
	// One fortune from the full list on the server; null if it fails (then a local one is used).
	function falFetch() {
		if (!window.fetch || !((C.falN || 0) > (C.fals || []).length)) return null;
		var url = C.ajax + (C.ajax.indexOf('?') < 0 ? '?' : '&') + 'action=yl_fal&not=' + falSrv + '&_=' + Date.now();
		return fetch(url, { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (j) { return j && j.ok && j.b1 ? j : null; })
			.catch(function () { return null; });
	}
	function falStart() {
		var fals = C.fals || [];
		if (!fals.length || !stage) return;
		$$('img', fres).forEach(function (im) { im.loading = 'eager'; });
		falT.forEach(clearTimeout);
		var n = fals.length;
		falIdx = falIdx < 0 ? Math.floor(Math.random() * n) : (n > 1 ? (falIdx + 1 + Math.floor(Math.random() * (n - 1))) % n : 0);
		var req = falFetch(), got = null;
		if (req) req.then(function (j) { got = j; });
		fres.hidden = true;
		play.hidden = false;
		var go = $('[data-fal="go"]', play);
		go.disabled = true;
		go.textContent = T.falBusy;
		falSet('go');
		var show = function (f) {
			if (f && typeof f.i === 'number') falSrv = f.i;
			f = f || fals[falIdx];
			$('[data-fal-b1]', fres).textContent = f.b1;
			$('[data-fal-b2]', fres).textContent = f.b2;
			$('[data-fal-t]', fres).textContent = f.t;
			play.hidden = true;
			fres.hidden = false;
			falSet('idle');
			go.disabled = false;
			go.textContent = T.falCta;
			setTimeout(function () { smooth(fres, 'center'); }, 60);
		};
		var seq = [['peck', 900], ['carry', 1600], ['seal', 2500], ['open', 3300], ['done', 4200]];
		falT = seq.map(function (x) {
			return setTimeout(function () {
				if (x[0] !== 'done') return falSet(x[0]);
				if (got || !req) return show(got);
				// Slow connection: wait up to 2.5s more for the server's fortune, then use a local one.
				var waited = false;
				var t = setTimeout(function () { waited = true; show(null); }, 2500);
				falT.push(t);
				req.then(function (j) { if (!waited) { clearTimeout(t); show(j); } });
			}, x[1]);
		});
	}

	/* ---------------- customer logos (→ left) and quotes (→ right) ----------------
	 * Without this script the strips still move with pure CSS (see landing.css). When it runs it takes
	 * the motion over so people can also move them by hand: drag with the mouse, swipe with a finger
	 * (vertical page scrolling is left alone), scroll sideways on a trackpad, use the arrows or the
	 * keyboard. The automatic motion runs as a browser animation (on the GPU, no per-frame script);
	 * the script only works while someone is moving a strip, then hands the motion back. */
	var pf = document.getElementById('proof');
	if (pf) {
		var pfStop = $('[data-pf-stop]', pf);
		var pfStopped = false, pfVisible = true, pfRaf = 0, pfLast = 0;
		var strips = [];
		// position kept in (-2 rows, 0]; the server/clones guarantee enough copies to cover that
		var wrapX = function (s) { var x = s.x % s.gw; if (x > 0) x -= s.gw; return x; };
		var put = function (s, x) { s.track.style.transform = 'translate3d(' + x.toFixed(2) + 'px,0,0)'; };
		var cardW = function (s) {
			var it = $('.yl-mq-i', s.grp);
			var gap = parseFloat(getComputedStyle(s.grp).columnGap) || 0;
			return it ? it.getBoundingClientRect().width + gap : 200;
		};
		var measure = function (s) {
			s.gw = s.grp.getBoundingClientRect().width || s.gw || 1;
			var need = Math.ceil(s.mq.clientWidth / s.gw) + 2;
			while (s.track.children.length < need) {
				var c = s.grp.cloneNode(true);
				c.setAttribute('aria-hidden', 'true');
				c.setAttribute('data-clone', '');
				$$('img', c).forEach(function (im) { im.alt = ''; });
				s.track.appendChild(c);
			}
		};
		var autoX = function (s) {
			var a = s.anim, dur = s.gw / s.v * 1000;
			var t = ((a.currentTime || 0) % dur) / dur;
			return s.a0 + s.dir * s.gw * t;
		};
		// hand the motion back to the browser, starting exactly where the strip is now
		var startAuto = function (s) {
			if (s.anim || s.drag || s.tween || Math.abs(s.vel) > 0.02 || !s.track.animate) return;
			if (Date.now() < s.hold) { clearTimeout(s.ht); s.ht = setTimeout(function () { startAuto(s); }, s.hold - Date.now() + 20); return; }
			var x = wrapX(s);
			s.a0 = s.dir < 0 ? x : x - s.gw;
			s.anim = s.track.animate(
				[{ transform: 'translate3d(' + s.a0 + 'px,0,0)' }, { transform: 'translate3d(' + (s.a0 + s.dir * s.gw) + 'px,0,0)' }],
				{ duration: s.gw / s.v * 1000, iterations: Infinity, easing: 'linear' }
			);
			if (pfStopped || !pfVisible || s.hover) s.anim.pause();
		};
		// take the motion over from the browser animation (the strip stays where it is)
		var stopAuto = function (s) {
			clearTimeout(s.ht);
			if (!s.anim) return;
			s.x = autoX(s);
			s.anim.cancel();
			s.anim = null;
			put(s, wrapX(s));
		};
		var syncPlay = function () {
			strips.forEach(function (s) {
				if (!s.anim) return;
				if (pfStopped || !pfVisible || s.hover) s.anim.pause(); else s.anim.play();
			});
		};
		var frame = function (t) {
			pfRaf = 0;
			var dt = pfLast ? Math.min(64, t - pfLast) : 16, busy = false;
			pfLast = t;
			strips.forEach(function (s) {
				if (s.tween) {
					var k = Math.min(1, (t - s.tween.t0) / s.tween.dur);
					s.x = s.tween.from + (s.tween.to - s.tween.from) * (1 - Math.pow(1 - k, 3));
					if (k >= 1) s.tween = null;
					put(s, wrapX(s));
				} else if (!s.drag && Math.abs(s.vel) > 0.02) {
					s.x += s.vel * dt;
					s.vel *= Math.pow(0.9, dt / 16); // a little momentum after a flick
					put(s, wrapX(s));
				}
				if (s.tween || (!s.drag && Math.abs(s.vel) > 0.02)) {
					busy = true;
				} else if (!s.drag && !s.anim) {
					s.vel = 0;
					startAuto(s); // tween/flick just finished: give the motion back to the browser
				}
			});
			if (busy) pfRaf = requestAnimationFrame(frame);
		};
		var kick = function () { if (!pfRaf) { pfLast = 0; pfRaf = requestAnimationFrame(frame); } };
		var nudge = function (s, dir) {
			stopAuto(s);
			s.vel = 0;
			s.tween = { from: s.x, to: s.x + dir * cardW(s), t0: performance.now(), dur: 450 };
			s.hold = Date.now() + 2500;
			kick();
		};

		if (!RM) {
			$$('.yl-mq', pf).forEach(function (mq) {
				var grp = $('.yl-mq-g', mq);
				var s = {
					mq: mq, track: $('.yl-mq-t', mq), grp: grp, gw: 0, hover: false, hold: 0, drag: null, vel: 0, tween: null, anim: null, a0: 0, ht: 0,
					dir: mq.classList.contains('yl-mq-qt') ? 1 : -1,
					v: parseFloat(getComputedStyle(mq).getPropertyValue('--v')) || 24,
					// continue from wherever the CSS animation is now, so the hand-over is invisible
					x: new DOMMatrix(getComputedStyle(grp).transform).m41 || 0
				};
				mq.classList.add('is-js');
				mq.tabIndex = 0;
				measure(s);
				put(s, wrapX(s));
				strips.push(s);
				startAuto(s);
				var box = mq.parentNode;
				$$('[data-mq]', box).forEach(function (b) {
					b.hidden = false;
					b.addEventListener('click', function () { nudge(s, +b.getAttribute('data-mq')); });
				});
				box.addEventListener('mouseenter', function () { s.hover = true; syncPlay(); });
				box.addEventListener('mouseleave', function () { s.hover = false; syncPlay(); });
				mq.addEventListener('keydown', function (e) {
					if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') { e.preventDefault(); nudge(s, e.key === 'ArrowLeft' ? 1 : -1); }
				});
				// drag (mouse) and swipe (touch): CSS touch-action:pan-y leaves vertical scrolling to the browser
				mq.addEventListener('pointerdown', function (e) {
					if (e.pointerType === 'mouse' && e.button !== 0) return;
					s.drag = { id: e.pointerId, x0: e.clientX, last: e.clientX, lt: performance.now(), moved: false, v: 0 };
				});
				mq.addEventListener('pointermove', function (e) {
					var d = s.drag;
					if (!d || d.id !== e.pointerId) return;
					if (!d.moved) {
						if (Math.abs(e.clientX - d.x0) < 6) return;
						d.moved = true;
						stopAuto(s);
						s.vel = 0; s.tween = null;
						try { mq.setPointerCapture(e.pointerId); } catch (x) {}
						mq.classList.add('is-drag');
					}
					var now = performance.now(), dx = e.clientX - d.last;
					s.x += dx;
					put(s, wrapX(s));
					d.v = 0.8 * (dx / Math.max(1, now - d.lt)) + 0.2 * d.v;
					d.last = e.clientX; d.lt = now;
				});
				var end = function (e) {
					var d = s.drag;
					if (!d || (e && d.id !== e.pointerId)) return;
					s.drag = null;
					mq.classList.remove('is-drag');
					if (d.moved) {
						s.vel = Math.max(-1.5, Math.min(1.5, d.v));
						s.hold = Date.now() + 1500;
						s.justDragged = Date.now();
						kick();
					} else if (e && e.pointerType !== 'mouse') {
						// a tap on a phone stops it briefly so it can be read
						stopAuto(s);
						s.hold = Date.now() + 2500;
						startAuto(s);
					}
				};
				mq.addEventListener('pointerup', end);
				mq.addEventListener('pointercancel', end);
				// A finger is captured by the card it touched first; moving that capture to the strip fires
				// lostpointercapture on the card, which bubbles here - only the strip's own loss ends the drag.
				mq.addEventListener('lostpointercapture', function (e) { if (e.target === mq) end(e); });
				mq.addEventListener('click', function (e) {
					if (s.justDragged && Date.now() - s.justDragged < 300) { e.preventDefault(); e.stopPropagation(); }
				}, true);
				mq.addEventListener('dragstart', function (e) { e.preventDefault(); });
				// trackpad sideways scroll (or Shift + wheel); plain vertical wheel keeps scrolling the page
				mq.addEventListener('wheel', function (e) {
					var dx = Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : (e.shiftKey ? e.deltaY : 0);
					if (!dx) return;
					e.preventDefault();
					stopAuto(s);
					s.vel = 0; s.tween = null;
					s.x -= dx;
					put(s, wrapX(s));
					s.hold = Date.now() + 1500;
					startAuto(s);
				}, { passive: false });
			});
			var remeasure = function () {
				strips.forEach(function (s) {
					var running = !!s.anim;
					stopAuto(s);
					measure(s);
					s.x = wrapX(s);
					put(s, s.x);
					if (running || !s.tween) startAuto(s);
				});
			};
			var rt = 0;
			window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(remeasure, 150); }, { passive: true });
			$$('img', pf).forEach(function (im) { if (!im.complete) im.addEventListener('load', function () { clearTimeout(rt); rt = setTimeout(remeasure, 150); }, { once: true }); });
			if (document.fonts && document.fonts.ready) document.fonts.ready.then(remeasure);
		}

		if ('IntersectionObserver' in window) {
			// Save battery: nothing in this section animates while it is off screen.
			new IntersectionObserver(function (l) {
				pfVisible = l[l.length - 1].isIntersecting;
				pf.classList.toggle('is-off', !pfVisible);
				syncPlay();
			}).observe(pf);
		}
		if (pfStop) {
			if (RM) pfStop.parentNode.hidden = true;
			pfStop.addEventListener('click', function () {
				pfStopped = !pfStopped;
				pf.classList.toggle('is-stop', pfStopped);
				pfStop.setAttribute('aria-pressed', pfStopped ? 'true' : 'false');
				$('[data-pf-l]', pfStop).textContent = pfStopped ? 'ادامه‌ی حرکت' : 'توقف حرکت';
				syncPlay();
			});
		}
	}

	/* ---------------- «یلدآف» rail: arrows, auto-advance ---------------- */
	var rail = document.getElementById('yl-track');
	var railGo = function () {};
	if (rail) {
		var railBox = rail.parentNode;
		var aPrev = $('[data-slide="1"]', railBox), aNext = $('[data-slide="-1"]', railBox), arw = $('.yl-arw', railBox);
		var railSign = getComputedStyle(rail).direction === 'rtl' ? -1 : 1; // RTL scrollLeft runs 0 → negative
		var railPos = function () { return Math.abs(rail.scrollLeft); };
		var railMax = function () { return Math.max(0, rail.scrollWidth - rail.clientWidth); };
		var railStep = function () {
			var c = rail.firstElementChild;
			var gap = parseFloat(getComputedStyle(rail).columnGap) || 0;
			return c ? c.getBoundingClientRect().width + gap : 300;
		};
		var railUser = 0;
		// dir: 1 = next card, -1 = previous; loop: past the end go back to the start (auto mode)
		railGo = function (dir, loop) {
			var max = railMax();
			if (max < 4) return;
			var pos = railPos(), step = railStep(), target;
			if (dir > 0 && pos >= max - 4) target = loop ? 0 : max;
			else if (dir < 0 && pos <= 4) target = loop ? max : 0;
			else target = Math.max(0, Math.min(max, (Math.round(pos / step) + dir) * step));
			rail.scrollTo({ left: railSign * target, behavior: RM ? 'auto' : 'smooth' });
		};
		var railUi = function () {
			var max = railMax(), pos = railPos();
			if (arw) arw.hidden = max < 4;
			if (aPrev) aPrev.disabled = pos <= 4;
			if (aNext) aNext.disabled = pos >= max - 4;
		};
		var railF = 0;
		rail.addEventListener('scroll', function () {
			if (!railF) railF = requestAnimationFrame(function () { railF = 0; railUi(); });
		}, { passive: true });
		window.addEventListener('resize', railUi, { passive: true });
		$$('img', rail).forEach(function (im) { if (!im.complete) im.addEventListener('load', railUi, { once: true }); });
		railUi();

		var railMs = +C.railAuto || 0;
		if (railMs && !RM) {
			var rh = { hover: false, focus: false, view: false, touch: 0 };
			railBox.addEventListener('mouseenter', function () { rh.hover = true; });
			railBox.addEventListener('mouseleave', function () { rh.hover = false; });
			railBox.addEventListener('focusin', function (e) {
				// Only keyboard focus pauses; a mouse click on an arrow also focuses it but should not stop the rail.
				var kb = true;
				try { kb = e.target.matches(':focus-visible'); } catch (x) {}
				rh.focus = kb;
			});
			railBox.addEventListener('focusout', function () { rh.focus = false; });
			rail.addEventListener('touchstart', function () { rh.touch = Date.now(); }, { passive: true });
			rail.addEventListener('touchend', function () { rh.touch = Date.now(); }, { passive: true });
			rail.addEventListener('wheel', function () { railUser = Date.now(); }, { passive: true });
			if ('IntersectionObserver' in window) {
				new IntersectionObserver(function (l) { rh.view = l[l.length - 1].isIntersecting; }, { threshold: 0.4 }).observe(rail);
			} else {
				rh.view = true;
			}
			setInterval(function () {
				var now = Date.now();
				if (rh.hover || rh.focus || !rh.view || document.hidden || S.modal) return;
				if (now - rh.touch < 6000 || now - railUser < railMs * 1.5) return; // let people browse in peace
				railGo(1, true);
			}, railMs);
		}
	}

	/* ---------------- clicks ---------------- */
	root.addEventListener('click', function (e) {
		var t = e.target;
		if (!t.closest || layer.contains(t)) return;

		var a = t.closest('a[href^="#"]');
		if (a) {
			var id = a.getAttribute('href').slice(1);
			var target = id && document.getElementById(id);
			if (target) {
				e.preventDefault();
				smooth(target);
				if (history.replaceState) history.replaceState(null, '', '#' + id);
			}
			return;
		}
		var b = t.closest('button');
		if (!b) return;
		var v;
		if ((v = b.getAttribute('data-deal')) !== null) return openQuick((C.deals || [])[+v]);
		if ((v = b.getAttribute('data-recent')) !== null) {
			var r = (C.recent || [])[+v];
			if (r) { S.people = Math.min(C.max || 5000, Math.max(C.min || 1, r.people)); openQuick(r); }
			return;
		}
		if ((v = b.getAttribute('data-pk-order')) !== null) return openQuick(C.packs[S.results[+v]]);
		if ((v = b.getAttribute('data-pk-swap')) !== null) return openSwap(+v);
		if ((v = b.getAttribute('data-slide')) !== null) {
			// data-slide="-1" is the «next» arrow (left in RTL), "1" the «previous» one
			railUser = Date.now();
			return railGo(+v === -1 ? 1 : -1, false);
		}
		if ((v = b.getAttribute('data-gf')) !== null) {
			if (v === 'next') return gfNext();
			if (v === 'prev') return gfMove(S.step - 1);
			if (v === 'send') return gfSend(b);
			if (v === 'edit') {
				S.sent = false;
				showResults(false);
				gfMove(0);
				setTimeout(function () { smooth(document.getElementById('build')); }, 60);
			}
			return;
		}
		if (b.hasAttribute('data-go-build')) return smooth(document.getElementById('build'));
		if (b.getAttribute('data-fal') === 'go') return falStart();
		if (b.hasAttribute('data-more')) {
			var box = document.getElementById('yl-seo-more');
			var open = !box.classList.contains('is-open');
			box.classList.toggle('is-open', open);
			box.style.maxHeight = open ? box.scrollHeight + 'px' : '';
			b.setAttribute('aria-expanded', open ? 'true' : 'false');
			b.textContent = open ? T.less : T.more;
			return;
		}
		var fi = b.closest('.yl-fi');
		if (fi) {
			var willOpen = !fi.classList.contains('is-open');
			$$('.yl-fi.is-open').forEach(function (o) {
				o.classList.remove('is-open');
				o.querySelector('button').setAttribute('aria-expanded', 'false');
			});
			if (willOpen) {
				fi.classList.add('is-open');
				b.setAttribute('aria-expanded', 'true');
			}
		}
	});

	renderStep();
	updBar();
})();
