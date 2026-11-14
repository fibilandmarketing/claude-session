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
	function falStart() {
		var fals = C.fals || [];
		if (!fals.length || !stage) return;
		$$('img', fres).forEach(function (im) { im.loading = 'eager'; });
		falT.forEach(clearTimeout);
		var n = fals.length;
		falIdx = falIdx < 0 ? Math.floor(Math.random() * n) : (n > 1 ? (falIdx + 1 + Math.floor(Math.random() * (n - 1))) % n : 0);
		fres.hidden = true;
		play.hidden = false;
		var go = $('[data-fal="go"]', play);
		go.disabled = true;
		go.textContent = T.falBusy;
		falSet('go');
		var seq = [['peck', 900], ['carry', 1600], ['seal', 2500], ['open', 3300], ['done', 4200]];
		falT = seq.map(function (x) {
			return setTimeout(function () {
				if (x[0] !== 'done') return falSet(x[0]);
				var f = fals[falIdx];
				$('[data-fal-b1]', fres).textContent = f.b1;
				$('[data-fal-b2]', fres).textContent = f.b2;
				$('[data-fal-t]', fres).textContent = f.t;
				play.hidden = true;
				fres.hidden = false;
				falSet('idle');
				go.disabled = false;
				go.textContent = T.falCta;
				setTimeout(function () { smooth(fres, 'center'); }, 60);
			}, x[1]);
		});
	}

	/* ---------------- customer logos (→ left) and quotes (→ right) ----------------
	 * The motion itself is pure CSS (see landing.css), so it runs even if this script is delayed.
	 * These are extras only: finger-hold on touch screens, pausing off screen, the pause button. */
	var pf = document.getElementById('proof');
	if (pf) {
		$$('.yl-mq', pf).forEach(function (mq) {
			var rel = 0;
			var up = function () { clearTimeout(rel); rel = setTimeout(function () { mq.classList.remove('is-hold'); }, 1200); };
			mq.addEventListener('touchstart', function () { clearTimeout(rel); mq.classList.add('is-hold'); }, { passive: true });
			mq.addEventListener('touchend', up, { passive: true });
			mq.addEventListener('touchcancel', up, { passive: true });
		});
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (l) { pf.classList.toggle('is-off', !l[l.length - 1].isIntersecting); }).observe(pf);
		}
		var pfStop = $('[data-pf-stop]', pf);
		if (pfStop) {
			if (RM) pfStop.parentNode.hidden = true;
			pfStop.addEventListener('click', function () {
				var stop = !pf.classList.contains('is-stop');
				pf.classList.toggle('is-stop', stop);
				pfStop.setAttribute('aria-pressed', stop ? 'true' : 'false');
				$('[data-pf-l]', pfStop).textContent = stop ? 'ادامه‌ی حرکت' : 'توقف حرکت';
			});
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
			var tr = document.getElementById('yl-track');
			if (tr) tr.scrollBy({ left: +v * 320, behavior: RM ? 'auto' : 'smooth' });
			return;
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
