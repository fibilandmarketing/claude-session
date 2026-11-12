/*!
 * Neuron landing – scroll-driven car, route, province map and mobile drawer.
 * A line-by-line port of the design's component logic (source/design/*.dc.html)
 * to plain JavaScript; geometry and timing constants are kept unchanged.
 */
(function () {
  'use strict';

  var CFG = window.NRN_CFG || {};
  var MAP = CFG.map || {};

  function fa(n) {
    return String(n).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.charAt(+d); });
  }

  /* tablets: draw the desktop design at its design width and scale it to the screen */
  if (!window.nrnFit) window.nrnFit = function () {
    var h = document.querySelector('.nrn-d'); if (!h) return 1;
    var r = h.querySelector('[data-ref="root"]'); if (!r) return 1;
    var dw = +h.getAttribute('data-dw') || 0;
    h.classList.remove('nrn-scaled'); h.style.height = '';
    var vw = h.clientWidth;
    if (!dw || !vw || vw >= dw || getComputedStyle(h).display === 'none') return 1;
    var s = vw / dw;
    h.style.setProperty('--nrn-dw', dw + 'px'); h.style.setProperty('--nrn-s', s);
    h.classList.add('nrn-scaled');
    h.style.height = Math.ceil(r.offsetHeight * s) + 'px';
    return s;
  };

  function Drive(root) {
    this.root = root;
    var holder = root.closest('[data-layout]');
    this.layout = holder ? holder.getAttribute('data-layout') : 'd';
    this.mob = this.layout === 'm';
    var q = function (n) { return root.querySelector('[data-ref="' + n + '"]'); };
    this.pathEl = q('path');
    this.car = q('car');
    this.tail = q('tail');
    this.drive = q('drive');
    this.driveTop = q('driveTop');
    this.heroText = q('heroText');
    this.carIn = q('carIn');
    this.heroCar = q('heroCar');
    this.svg = this.pathEl ? this.pathEl.ownerSVGElement : null;
    this.roads = Array.prototype.slice.call(root.querySelectorAll('path[data-road]'));
    this.legPaths = Array.prototype.slice.call(root.querySelectorAll('path[data-leg]'));
    this.state = {};
    this.mount();
  }

  var P = Drive.prototype;

  P.mount = function () {
    var self = this;
    this.tickB = function (now) { self.tick(now); };
    this.cur = 0; this.ang = 0; this.vel = 0; this.target = 0; this.loop = 0; this.lastT = 0;
    this.stage = -1; this.isParked = null; this.isBraking = null; this.isReverse = null; this.angInit = false; this.tailS = -1; this.heroT = '';
    this.intro = false; this.introDone = false; this.startLen = 0; this.rel = 0; this.vh = window.innerHeight || 800; this.offTop = 0;
    this.reduce = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    this.onScroll = function (e) {
      var tg = e && e.target;
      if (tg && tg.nodeType === 1 && tg !== self.sc && self.root && tg.contains(self.root)) { self.sc = tg; self.syncScroller(); }
      self.updateTarget();
      self.kick();
    };
    this.onResize = function () { self.schedule(); };
    window.addEventListener('scroll', this.onScroll, { passive: true, capture: true });
    window.addEventListener('resize', this.onResize, { passive: true });
    this.timers = [setTimeout(function () { self.measure(); }, 60), setTimeout(function () { self.measure(); }, 1000), setTimeout(function () { self.measure(); }, 2800)];
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { self.schedule(); });
    if (window.ResizeObserver && this.root) { this.ro = new ResizeObserver(function () { self.schedule(); }); this.ro.observe(this.root); }
  };

  P.schedule = function () { var self = this; clearTimeout(this.mt); this.mt = setTimeout(function () { self.measure(); }, 140); };

  P.findScroller = function () {
    var el = this.root ? this.root.parentElement : null;
    while (el && el !== document.body && el !== document.documentElement) {
      var cs = getComputedStyle(el);
      if (/(auto|scroll|overlay)/.test(cs.overflowY) && el.scrollHeight > el.clientHeight + 2) return el;
      el = el.parentElement;
    }
    return null;
  };

  P.scrollY = function () { return this.sc ? this.sc.scrollTop : (window.pageYOffset || document.documentElement.scrollTop || 0); };

  P.syncScroller = function () {
    var root = this.root; if (!root) return;
    var rr = root.getBoundingClientRect();
    // tablets: the desktop design is drawn at a fixed width and scaled (see nrnFit)
    this.s = root.offsetWidth ? (rr.width / root.offsetWidth) || 1 : 1;
    var top0 = this.sc ? this.sc.getBoundingClientRect().top : 0;
    this.offTop = rr.top - top0 + this.scrollY();
    this.vh = this.sc ? this.sc.clientHeight : window.innerHeight;
    this.scrollable = rr.height > this.vh + 40;
  };

  /* ------------------------------------------------------------ map */
  P.initMap = function () {
    var self = this, root = this.root; if (!root || this.mapOff) return;
    var doc = root.ownerDocument || document;
    var W = function () { return self.root ? self.root.querySelector('[data-mapwrap]') : null; };
    var inMap = function (t) { return !!(t && t.closest && self.root && self.root.contains(t) && t.closest('[data-mapwrap]')); };
    var prvOf = function (t) { return inMap(t) ? t.closest('[data-province]') : null; };
    var show = function (p) {
      var w = W(); if (!w || !p) return;
      var nm = w.querySelector('[data-mname]'), ct = w.querySelector('[data-mcount]'), cta = w.querySelector('[data-mcta]');
      var n = +p.getAttribute('data-n') || 0;
      var url = p.getAttribute('data-url') || MAP.url || '#';
      if (nm) nm.textContent = p.getAttribute('data-name');
      if (ct) ct.textContent = n ? fa(n) + ' ' + (MAP.countSuffix || 'نمایندگی فعال') : (MAP.noneText || 'هنوز نمایندگی در این استان ثبت نشده است');
      if (cta) { cta.setAttribute('href', n ? url : (MAP.noneUrl || '#')); cta.textContent = n ? (MAP.ctaHas || 'مشاهدهٔ نمایندگی‌ها') : (MAP.ctaNone || 'تماس با نورون'); }
    };
    var selEl = function () { var w = W(); return w ? w.querySelector('[data-province="' + (self.mapSel || MAP.def || 'tehran') + '"]') : null; };
    this.mapApply = function () {
      var w = W(); if (!w) return;
      var p = selEl(); if (!p) return;
      Array.prototype.forEach.call(w.querySelectorAll('[data-province].sel'), function (el) { if (el !== p) { el.classList.remove('sel'); el.setAttribute('aria-pressed', 'false'); } });
      p.classList.add('sel'); p.setAttribute('aria-pressed', 'true');
      var sl = w.querySelector('[data-msel]'); if (sl && sl.getAttribute('d') !== p.getAttribute('d')) sl.setAttribute('d', p.getAttribute('d'));
      show(p);
      var dd = self.root.querySelector('[data-mselect]'); if (dd && dd.value !== p.getAttribute('data-province')) dd.value = p.getAttribute('data-province');
      var pg = w.querySelector('[data-mapping]'); if (pg && self.ping) { pg.style.left = self.ping.x + 'px'; pg.style.top = self.ping.y + 'px'; }
      var pk = self.root.querySelector('[data-park]'); if (pk) pk.classList.toggle('arrived', !!self.isParked);
    };
    var pick = function (p) { if (!p) return; self.mapSel = p.getAttribute('data-province'); self.mapApply(); };
    var hovOn = function (p) { var w = W(); var h = w && w.querySelector('[data-mhov]'); if (h) { h.setAttribute('d', p.getAttribute('d')); h.classList.add('on'); } show(p); };
    var hovOff = function () { var w = W(); var h = w && w.querySelector('[data-mhov]'); if (h) h.classList.remove('on'); show(selEl()); };
    var over = function (e) { var p = prvOf(e.target); if (p) hovOn(p); };
    var out = function (e) { if (!prvOf(e.target)) return; if (prvOf(e.relatedTarget)) return; hovOff(); };
    var click = function (e) { var p = prvOf(e.target); if (p) pick(p); };
    var key = function (e) { if (e.key !== 'Enter' && e.key !== ' ') return; var p = prvOf(e.target); if (p) { e.preventDefault(); pick(p); } };
    var chg = function (e) { var t = e.target; if (!t || !t.matches || !t.matches('[data-mselect]') || !self.root || !self.root.contains(t) || !t.value) return; self.mapSel = t.value; self.mapApply(); };
    var ev = [['mouseover', over], ['mouseout', out], ['focusin', over], ['focusout', out], ['click', click], ['keydown', key], ['change', chg]];
    ev.forEach(function (x) { doc.addEventListener(x[0], x[1]); });
    this.mapOff = function () { ev.forEach(function (x) { doc.removeEventListener(x[0], x[1]); }); };
    this.mapApply();
  };

  /* --------------------------------------------- products carousel */
  P.initProducts = function () {
    var self = this, root = this.root; if (!root || this.prodOff) return;
    var doc = root.ownerDocument || document;
    var upd = function (tr) {
      var sec = tr.closest('section'); if (!sec) return;
      var max = tr.scrollWidth - tr.clientWidth, x = Math.abs(tr.scrollLeft);
      Array.prototype.forEach.call(sec.querySelectorAll('[data-pnav]'), function (b) { var d = +b.getAttribute('data-pnav'); b.disabled = d > 0 ? x <= 12 : x >= max - 12; });
    };
    var click = function (e) {
      var b = e.target && e.target.closest ? e.target.closest('[data-pnav]') : null;
      if (!b || !self.root || !self.root.contains(b)) return;
      var sec = b.closest('section'), tr = sec && sec.querySelector('[data-ptrack]'); if (!tr) return;
      var c = tr.querySelector('.pcard'), step = c ? c.getBoundingClientRect().width + 20 : 300;
      tr.scrollBy({ left: (+b.getAttribute('data-pnav')) * step, behavior: 'smooth' });
    };
    var scr = function (e) { var t = e.target; if (t && t.matches && t.matches('[data-ptrack]')) upd(t); };
    doc.addEventListener('click', click); doc.addEventListener('scroll', scr, true);
    this.prodOff = function () { doc.removeEventListener('click', click); doc.removeEventListener('scroll', scr, true); };
    this.prodApply = function () { if (self.root) Array.prototype.forEach.call(self.root.querySelectorAll('[data-ptrack]'), upd); };
    this.prodApply();
  };

  /* --------------------------------------------------- mobile drawer */
  P.initMenu = function () {
    var self = this, root = this.root; if (!root || this.menuOff) return;
    if (!root.querySelector('[data-drawer]')) return;
    var doc = root.ownerDocument || document;
    this.menuApply = function () {
      var d = self.root && self.root.querySelector('[data-drawer]'); if (!d) return;
      var o = !!self.menuOpen;
      if (d.classList.contains('open') !== o) d.classList.toggle('open', o);
      d.setAttribute('aria-hidden', o ? 'false' : 'true');
      var b = self.root.querySelector('[data-menu-open]'); if (b) b.setAttribute('aria-expanded', o ? 'true' : 'false');
    };
    var set = function (v) {
      self.menuOpen = v; self.menuApply();
      var t = self.root && self.root.querySelector(v ? '[data-drawer] .md-x' : '[data-menu-open]');
      if (t && t.focus) setTimeout(function () { try { t.focus({ preventScroll: true }); } catch (e) {} }, v ? 160 : 0);
    };
    var click = function (e) {
      var t = e.target; if (!t || !t.closest || !self.root) return;
      // the bottom bar lives next to the root, inside the same layout wrapper
      if (!self.root.contains(t) && t.closest('.nrn') !== self.root.closest('.nrn')) return;
      if (t.closest('[data-menu-open]')) { e.preventDefault(); set(true); return; }
      if (self.menuOpen && (t.closest('[data-menu-close]') || t.closest('[data-drawer] a'))) set(false);
    };
    var key = function (e) { if (e.key === 'Escape' && self.menuOpen) set(false); };
    doc.addEventListener('click', click); doc.addEventListener('keydown', key);
    this.menuOff = function () { doc.removeEventListener('click', click); doc.removeEventListener('keydown', key); };
    this.menuApply();
  };

  /* -------------------------------------------------- route geometry */
  P.measure = function () {
    var root = this.root; if (!root) return;
    if (!this.sc) this.sc = this.findScroller();
    if (!this.mob && window.nrnFit) window.nrnFit();
    this.syncScroller();
    var S = this.s || 1;
    if (!this.menuOff) this.initMenu();
    var rr = root.getBoundingClientRect();
    var W = Math.round(root.clientWidth), H = Math.round(rr.height / S);
    if (!W || !H) return;
    var off = function (el) { var r = el.getBoundingClientRect(); return { x: (r.left - rr.left) / S, y: (r.top - rr.top) / S, w: r.width / S, h: r.height / S }; };
    var hero = root.querySelector('[data-hero]');
    var park = root.querySelector('[data-park]');
    var secs = Array.prototype.slice.call(root.querySelectorAll('[data-stop]'));
    if (!hero) return;
    var mob = this.mob;
    var h = off(hero);
    var m = Math.round(Math.max(14, Math.min(64, (W - Math.min(W, 1240)) / 4 + 12)));
    var Lx = m, Rx = W - m;
    var hm = root.querySelector('[data-heromap]');
    var d, sx, sy, pre = '', preEnd = null, startY = 0;
    if (hm) {
      var r0 = off(hm), NW = 1672, NH = 941;
      var sc = Math.max(r0.w / NW, r0.h / NH);
      var ox = r0.x + (r0.w - NW * sc) * (+hm.getAttribute('data-ox') || 0);
      var oy = r0.y + (r0.h - NH * sc) * (+hm.getAttribute('data-oy') || 0);
      var T = function (p) { return Math.round(ox + p[0] * sc) + ' ' + Math.round(oy + p[1] * sc); };
      var R = [[-60,898],[-40,897],[21,896],[60,893],[99,890],[147,882],[175,880],[203,878],[208,881],[225,880],[242,878],[259,873],[275,870],[292,867],[309,864],[325,861],[342,858],[358,856],[375,852],[391,849],[408,846],[425,842],[442,838],[461,834],[475,830],[489,826],[499,823],[510,819],[521,816],[532,813],[541,807],[550,802],[561,792],[564,786],[568,780],[564,776],[562,771],[560,767],[555,765],[550,762],[545,758],[538,755],[530,751],[522,747],[511,742],[504,738],[498,733],[494,726],[494,722],[493,718],[496,716],[501,713],[505,710],[513,707],[521,705],[528,703],[536,700],[545,698]];
      d = 'M' + T(R[0]);
      for (var i = 1; i + 2 < R.length; i += 3) d += ' C' + T(R[i]) + ' ' + T(R[i + 1]) + ' ' + T(R[i + 2]);
      var last = R[R.length - 1];
      sx = Math.round(ox + last[0] * sc); sy = Math.round(oy + last[1] * sc);
      var pX = ox + 605 * sc, pY = oy + 684 * sc, k = sc;
      var rn = Math.round;
      pre = ' C' + rn(sx + (pX - sx) * 0.4) + ' ' + rn(sy) + ' ' + rn(pX - (pX - sx) * 0.35) + ' ' + rn(pY) + ' ' + rn(pX) + ' ' + rn(pY)
          + ' C' + rn(pX + 80 * k) + ' ' + rn(pY) + ' ' + rn(pX + 60 * k) + ' ' + rn(pY + 50 * k) + ' ' + rn(pX + 60 * k) + ' ' + rn(pY + 110 * k);
      preEnd = { x: rn(pX + 60 * k), y: rn(pY + 110 * k) };
      var hc = this.heroCar;
      if (hc) {
        var par = hc.offsetParent ? off(hc.offsetParent) : { x: 0, y: 0 };
        if (hc.hasAttribute('data-pw')) {
          // fixed width, centred on a point of the map (mobile hero)
          // phones: left-aligned and never wider than the screen (it used to be cut on the right),
          // vertically still on its spot of the map
          var pw = Math.min(+hc.getAttribute('data-pw'), W - 8);
          hc.style.width = pw + 'px';
          hc.style.left = '0px';
          hc.style.top = Math.round(oy + (+hc.getAttribute('data-cy')) * sc - pw * 0.601 / 2 - par.y) + 'px';
          var ho = off(hc);
          startY = Math.round(ho.y + ho.h - 14);
        } else {
          hc.style.left = Math.round(ox + (+hc.getAttribute('data-x')) * sc - par.x) + 'px';
          hc.style.top = Math.round(oy + (+hc.getAttribute('data-y')) * sc - par.y) + 'px';
          hc.style.width = Math.round((+hc.getAttribute('data-w')) * sc) + 'px';
        }
      }
    } else {
      sx = Math.round(W * 0.5); sy = Math.round(h.y + h.h - 100); d = 'M' + sx + ' ' + sy;
    }
    var dr = 'M' + sx + ' ' + sy + pre;
    d += pre;
    var first = dr;
    var legs = [];
    var stops = [];
    var CROSS = mob ? 170 : 320;
    var cx = preEnd ? preEnd.x : sx, cy = preEnd ? preEnd.y : sy, prevSide = null;
    secs.forEach(function (el, i) {
      var o = off(el);
      // a section may pin the side the road reaches it from (data-side)
      var sd = el.getAttribute('data-side') || (prevSide ? (prevSide === 'L' ? 'R' : 'L') : 'L');
      var tx = sd === 'L' ? Lx : Rx;
      var yTop = i === 0 ? cy : Math.round(Math.max(cy + 40, o.y - CROSS));
      var yBot = Math.round(o.y + (i === 0 ? (mob ? 90 : 140) : (mob ? 18 : 26)));
      // ... and make the road cross over a given element (data-cross -> data-crossel)
      var ck = el.getAttribute('data-cross'), ce = ck ? root.querySelector('[data-crossel="' + ck + '"]') : null;
      if (ce && i > 0) { var cr = off(ce), pad = mob ? 50 : 70; yTop = Math.round(Math.max(cy + 40, cr.y - pad)); yBot = Math.round(cr.y + cr.h + pad); }
      var minDy = mob ? 90 : 120;
      if (yBot < yTop + minDy) yBot = yTop + minDy;
      var seg = '';
      if (yTop > cy) seg += ' L' + cx + ' ' + yTop;
      var ymid = Math.round((yTop + yBot) / 2);
      seg += ' C' + cx + ' ' + ymid + ' ' + tx + ' ' + ymid + ' ' + tx + ' ' + yBot;
      legs.push((i === 0 ? first : 'M' + cx + ' ' + cy) + seg);
      d += seg; dr += seg;
      stops.push({ i: i, x: tx, y: yBot });
      cx = tx; cy = yBot; prevSide = sd;
    });
    var px, py;
    if (park) {
      var pk = off(park);
      px = Math.round(pk.x + pk.w / 2); py = Math.round(pk.y + pk.h / 2);
      if (!this.mapOff) this.initMap();
      if (!this.prodOff) this.initProducts();
      var tp = park.querySelector('[data-province="' + (MAP.def || 'tehran') + '"]');
      if (tp) {
        var o2 = off(tp);
        if (o2.w > 2) { px = Math.round(o2.x + o2.w / 2); py = Math.round(o2.y + o2.h * 0.52); }
        var mb = park.querySelector('[data-mapbox]');
        if (mb) { var ob = off(mb); this.ping = { x: px - ob.x, y: py - ob.y }; }
        if (this.mapApply) this.mapApply();
        if (this.prodApply) this.prodApply();
      }
    } else {
      // the map section is switched off: park at the bottom of the last stop's column
      px = cx; py = Math.round(Math.min(H - 80, cy + (mob ? 260 : 420)));
    }
    var yT = Math.round(Math.max(cy + 40, (park ? off(park).y : py) - (mob ? 120 : 200)));
    var seg2 = '';
    if (yT > cy) seg2 += ' L' + cx + ' ' + yT;
    var ym2 = Math.round((yT + py) / 2);
    seg2 += ' C' + cx + ' ' + ym2 + ' ' + px + ' ' + ym2 + ' ' + px + ' ' + Math.max(py, ym2 + 10);
    legs.push('M' + cx + ' ' + cy + seg2);
    d += seg2; dr += seg2;
    this.geo = { sx: sx, sy: sy };
    this.secEls = secs;
    this.W = W;
    this.heroH = h.h;
    this.collectBoxes(root, park);
    Array.prototype.forEach.call(root.querySelectorAll('section.dark, section.pearl, footer.dark'), function (el) { el.style.backgroundPosition = 'center ' + Math.round(-el.offsetTop) + 'px'; });
    var vb = '0 0 ' + W + ' ' + H;
    var st = this.state;
    var startMoved = startY !== (this.startY || 0);
    this.startY = startY;
    if (st.d !== d || st.vb !== vb || startMoved) {
      this.state = { d: d, dr: dr, vb: vb, legs: legs, stops: stops, park: { x: px, y: py } };
      this.render();
      this.buildTable();
      if (this.mapApply) this.mapApply();
      if (this.prodApply) this.prodApply();
    } else { this.updateTarget(); this.kick(); }
  };

  /* what the design runtime used to re-render from state */
  P.render = function () {
    var s = this.state;
    if (this.svg) this.svg.setAttribute('viewBox', s.vb);
    if (this.pathEl) this.pathEl.setAttribute('d', s.d);
    this.roads.forEach(function (p) { p.setAttribute('d', s.dr); });
    this.legPaths.forEach(function (p) { var i = +p.getAttribute('data-leg'); p.setAttribute('d', s.legs[i] || 'M-20 -20'); });
    if (this.drive) {
      Array.prototype.forEach.call(this.drive.querySelectorAll('.stop'), function (el) { el.parentNode.removeChild(el); });
      var car = this.car, drive = this.drive;
      s.stops.forEach(function (st) {
        var el = document.createElement('div');
        el.className = 'stop';
        el.setAttribute('data-stopi', st.i);
        el.style.left = st.x + 'px'; el.style.top = st.y + 'px';
        el.innerHTML = '<span class="ring"></span><span class="dot"></span>';
        drive.insertBefore(el, car && car.parentNode === drive ? car : null);
      });
    }
  };

  /* The route is our own M/L/C path: flatten it once and sample it by arc length.
     (SVG getPointAtLength() costs ~0.5 ms per call — 3200 calls froze the page on load.) */
  function flatten(d) {
    var px = [], py = [], cx = 0, cy = 0, re = /([MLC])([^MLC]*)/g, m;
    while ((m = re.exec(d))) {
      var v = m[2].trim().split(/[\s,]+/).map(Number), j;
      if (m[1] === 'M') { cx = v[0]; cy = v[1]; px.push(cx); py.push(cy); }
      else if (m[1] === 'L') { for (j = 0; j + 1 < v.length; j += 2) { cx = v[j]; cy = v[j + 1]; px.push(cx); py.push(cy); } }
      else {
        for (j = 0; j + 5 < v.length; j += 6) {
          var x1 = v[j], y1 = v[j + 1], x2 = v[j + 2], y2 = v[j + 3], x3 = v[j + 4], y3 = v[j + 5];
          var steps = Math.max(8, Math.min(200, Math.ceil((Math.abs(x1 - cx) + Math.abs(y1 - cy) + Math.abs(x2 - x1) + Math.abs(y2 - y1) + Math.abs(x3 - x2) + Math.abs(y3 - y2)) / 3)));
          for (var k = 1; k <= steps; k++) {
            var t = k / steps, u = 1 - t, a = u * u * u, b = 3 * u * u * t, c = 3 * u * t * t, e = t * t * t;
            px.push(a * cx + b * x1 + c * x2 + e * x3); py.push(a * cy + b * y1 + c * y2 + e * y3);
          }
          cx = x3; cy = y3;
        }
      }
    }
    var cum = new Float64Array(px.length);
    for (var i = 1; i < px.length; i++) cum[i] = cum[i - 1] + Math.hypot(px[i] - px[i - 1], py[i] - py[i - 1]);
    return { px: px, py: py, cum: cum, L: cum[cum.length - 1] || 0 };
  }

  P.buildTable = function () {
    var p = this.pathEl, s = this.state || {};
    if (!p || !s.d || s.d.length < 12) return;
    var F = flatten(s.d), L = F.L;
    if (!L) return;
    var n = Math.min(3200, Math.max(300, Math.round(L / 4)));
    var xs = new Float32Array(n), ys = new Float32Array(n), ym = new Float32Array(n), an = new Float32Array(n);
    var mx = -1e9, i, seg = 1;
    for (i = 0; i < n; i++) {
      var target = L * i / (n - 1);
      while (seg < F.cum.length - 1 && F.cum[seg] < target) seg++;
      var l0 = F.cum[seg - 1], l1 = F.cum[seg], f = l1 > l0 ? (target - l0) / (l1 - l0) : 0;
      xs[i] = F.px[seg - 1] + (F.px[seg] - F.px[seg - 1]) * f; ys[i] = F.py[seg - 1] + (F.py[seg] - F.py[seg - 1]) * f;
      if (ys[i] > mx) mx = ys[i]; ym[i] = mx;
    }
    var prevA = 0;
    for (i = 0; i < n; i++) {
      var a = Math.max(0, i - 3), b = Math.min(n - 1, i + 3);
      var g = Math.atan2(ys[b] - ys[a], xs[b] - xs[a]) * 180 / Math.PI;
      if (i > 0) { while (g - prevA > 180) g -= 360; while (g - prevA < -180) g += 360; }
      an[i] = g; prevA = g;
    }
    this.tbl = { n: n, xs: xs, ys: ys, ym: ym, an: an, L: L, W: this.W || 1440 };
    var geo = this.geo || { sx: 0 };
    var si = 0, best = 1e12;
    for (i = 0; i < n; i++) { var dd = (xs[i] - geo.sx) * (xs[i] - geo.sx) + (ys[i] - geo.sy) * (ys[i] - geo.sy); if (dd < best) { best = dd; si = i; } }
    // phones: the car waits on the road at the bottom of the hero picture, not hidden behind the pin
    if (this.startY) {
      for (i = si; i < n; i++) { if (ys[i] >= this.startY) { si = i; break; } }
    }
    var mm = ys[si];
    for (i = 0; i < n; i++) { if (i <= si) ym[i] = ys[si]; else { if (ys[i] > mm) mm = ys[i]; ym[i] = mm; } }
    this.startLen = L * si / (n - 1);
    var self = this;
    this.stopLens = (s.stops || []).map(function (st) { return Math.max(self.startLen + 1, self.lenForY(st.y)); });
    this.legEls = this.legPaths.slice();
    this.stopDom = Array.prototype.slice.call(this.root.querySelectorAll('[data-stopi]'));
    this.nLegs = (s.legs || []).length;
    this.stage = -1; this.isParked = null;
    this.updateTarget();
    if (!this.introDone) {
      this.introDone = true;
      // road, car and logo strip fade in now that the route is ready
      var holder = this.root.closest('.nrn');
      if (holder) holder.classList.add('nrn-go');
      if (this.reduce) { this.cur = this.target; }
      else { this.cur = 0; this.intro = true; this.introStart = 0; }
    }
    this.kick();
  };

  P.lenForY = function (y) {
    var t = this.tbl, n = t.n;
    if (y <= t.ym[0]) return 0;
    if (y >= t.ym[n - 1]) return t.L;
    var lo = 0, hi = n - 1;
    while (hi - lo > 1) { var mid = (lo + hi) >> 1; if (t.ym[mid] <= y) lo = mid; else hi = mid; }
    var dy = t.ym[hi] - t.ym[lo];
    var f = dy > 0 ? (y - t.ym[lo]) / dy : 0;
    return t.L * (lo + f) / (n - 1);
  };

  P.updateTarget = function () {
    if (!this.tbl) return;
    this.rel = this.scrollY() - this.offTop;
    var ty = this.scrollable ? (this.rel + this.vh * 0.6) / (this.s || 1) : -1;
    this.target = Math.max(this.startLen, this.lenForY(ty));
  };

  P.kick = function () {
    if (!this.loop && this.tbl) { this.lastT = 0; this.loop = requestAnimationFrame(this.tickB); }
  };

  P.tick = function (now) {
    this.loop = 0;
    var t = this.tbl;
    if (!t) return;
    if (!this.root.offsetWidth) { this.lastT = 0; return; } // layout hidden (other breakpoint)
    var dt = this.lastT ? Math.min(0.05, Math.max(0.001, (now - this.lastT) / 1000)) : 1 / 60;
    this.lastT = now;
    var prev = this.cur;
    if (this.intro) {
      if (!this.introStart) this.introStart = now;
      var p = Math.min(1, (now - this.introStart) / 2600);
      var e = p < 0.5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2;
      this.cur = this.startLen * e;
      if (p >= 1) this.intro = false;
    } else {
      // a fling to the far end of the page: the car is off-screen anyway, so it skips ahead and
      // only drives the last stretch (no burst of station / glass / route updates on the way)
      var lim = 1.4 * (this.vh || 800) / (this.s || 1), gap = this.target - this.cur;
      if (Math.abs(gap) > lim) { this.cur = this.target - (gap > 0 ? lim : -lim); prev = this.cur; }
      this.cur += (this.target - this.cur) * (1 - Math.exp(-dt * 7));
    }
    var v = (this.cur - prev) / dt;
    this.vel += (v - this.vel) * Math.min(1, dt * 12);
    this.place(dt);
    this.applyHero();
    this.flags();
    if (!this.intro && Math.abs(this.target - this.cur) < 0.35 && Math.abs(this.vel) < 6) {
      this.cur = this.target; this.vel = 0;
      this.place(1); this.flags();
      this.lastT = 0;
      return;
    }
    this.loop = requestAnimationFrame(this.tickB);
  };

  P.place = function (dt) {
    var t = this.tbl;
    if (!t || !this.car) return;
    var l = Math.max(0, Math.min(t.L, this.cur));
    var f = l / t.L * (t.n - 1);
    var i = Math.min(t.n - 2, Math.floor(f));
    var fr = f - i;
    var x = t.xs[i] + (t.xs[i + 1] - t.xs[i]) * fr;
    var y = t.ys[i] + (t.ys[i + 1] - t.ys[i]) * fr;
    var a = t.an[i] + (t.an[i + 1] - t.an[i]) * fr;
    if (!this.angInit) { this.ang = a; this.angInit = true; }
    else {
      var dA = a - this.ang;
      while (dA > 180) dA -= 360;
      while (dA < -180) dA += 360;
      this.ang += dA * (1 - Math.exp(-dt * 16));
    }
    this.car.style.transform = 'translate3d(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px,0) rotate(' + this.ang.toFixed(2) + 'deg)';
    var inHero = this.mob && y < (this.heroH || 0);
    if (inHero !== this.inHero) { this.inHero = inHero; if (this.drive) this.drive.classList.toggle('in-hero', inHero); }
    if (this.tail) {
      var sp = this.vel > 0 ? Math.min(1, this.vel / (this.mob ? 900 : 1500)) : 0;
      var q = Math.round(sp * 60);
      if (q !== this.tailS) {
        this.tailS = q;
        this.tail.style.transform = 'scaleX(' + Math.max(0.001, q / 60).toFixed(3) + ')';
        this.tail.style.opacity = (q / 60 * 0.95).toFixed(3);
      }
    }
    if (document.documentElement.classList.contains('nrn-fast')) {
      if (this.on && this.on.length) { this.on.forEach(function (el) { el.classList.remove('tunnel'); }); this.on = []; }
    } else {
      this.tunnel(x, y);
    }
  };

  P.applyHero = function () {
    var hp = this.scrollable ? Math.max(0, Math.min(1.5, this.rel / (this.s || 1) / (this.heroH || 900))) : 0;
    var t = Math.max(0, Math.min(1, hp / 0.6));
    var key = t.toFixed(3);
    if (key === this.heroT) return;
    this.heroT = key;
    if (this.heroText) { this.heroText.style.transform = 'translate3d(0,' + (-t * 40).toFixed(1) + 'px,0)'; this.heroText.style.opacity = (1 - t * 0.5).toFixed(3); }
  };

  P.flags = function () {
    var t = this.tbl, sl = this.stopLens || [], l = this.cur, self = this;
    var stage = 0;
    while (stage < sl.length && l >= sl[stage] - 4) stage++;
    if (stage !== this.stage) {
      this.stage = stage;
      (this.stopDom || []).forEach(function (el) { var i = +el.getAttribute('data-stopi'); el.classList.toggle('lit', i < stage); });
      (this.legEls || []).forEach(function (el) { var i = +el.getAttribute('data-leg'); el.classList.toggle('done', i < stage); el.classList.toggle('next', i === stage && i < (self.nLegs || 0)); });
      (this.secEls || []).forEach(function (el, i) { el.classList.toggle('visited', i < stage); });
    }
    var parked = l >= t.L - 3;
    if (parked !== this.isParked) {
      this.isParked = parked;
      if (this.drive) this.drive.classList.toggle('parked', parked);
      if (this.car) this.car.classList.toggle('parked', parked);
      if (this.driveTop) this.driveTop.classList.toggle('parked', parked);
      var pke = this.root && this.root.querySelector('[data-park]'); if (pke) pke.classList.toggle('arrived', parked);
      var lastLeg = (this.legEls || [])[(this.nLegs || 1) - 1];
      if (lastLeg) { lastLeg.classList.toggle('done', parked); if (parked) lastLeg.classList.remove('next'); }
    }
    var rev = !this.intro && this.vel < -60;
    var braking = !this.intro && !rev && this.vel < 70;
    if (rev !== this.isReverse) { this.isReverse = rev; if (this.car) this.car.classList.toggle('reverse', rev); }
    if (braking !== this.isBraking) { this.isBraking = braking; if (this.car) this.car.classList.toggle('braking', braking); }
  };

  P.collectBoxes = function (root, park) {
    var lr = function (el) {
      var x = 0, y = 0, e = el;
      while (e && e !== root) { x += e.offsetLeft; y += e.offsetTop; e = e.offsetParent; }
      if (e !== root) return null;
      return { x1: x, y1: y, x2: x + el.offsetWidth, y2: y + el.offsetHeight };
    };
    (this.on || []).forEach(function (el) { el.classList.remove('tunnel'); });
    this.on = [];
    var list = root.querySelectorAll('.neo, .neo-sm, .neo-in, .glass, .glass-l, .btn, .kicker, div.dark, article.dark, [data-box]');
    var boxes = [];
    Array.prototype.forEach.call(list, function (el) {
      if (el === park || el.closest('.drive, .drive-top, .stage, .ptrack') || (el.closest('.marquee') && !el.classList.contains('marquee'))) return;
      var r = lr(el);
      if (!r || r.x2 - r.x1 < 8 || r.y2 - r.y1 < 8) return;
      boxes.push({ el: el, x1: r.x1, y1: r.y1, x2: r.x2, y2: r.y2 });
    });
    this.boxes = boxes;
    this.parkR = park ? lr(park) : null;
    this.isSurf = null;
  };

  P.tunnel = function (x, y) {
    var bx = this.boxes;
    if (!bx) return;
    var r = this.mob ? 20 : 30;
    var now = [];
    for (var i = 0; i < bx.length; i++) {
      var b = bx[i];
      if (x > b.x1 - r && x < b.x2 + r && y > b.y1 - r && y < b.y2 + r) now.push(b.el);
    }
    var on = this.on || [];
    on.forEach(function (el) { if (now.indexOf(el) < 0) el.classList.remove('tunnel'); });
    now.forEach(function (el) { if (on.indexOf(el) < 0) el.classList.add('tunnel'); });
    this.on = now;
    var p = this.parkR;
    var surf = !!(p && x > p.x1 - 6 && x < p.x2 + 6 && y > p.y1 - 6 && y < p.y2 + 6);
    if (surf !== this.isSurf) { this.isSurf = surf; if (this.drive) this.drive.classList.toggle('surface', surf); }
  };

  /* ------------------------------------------------- video lightbox */
  function initVideos() {
    var box = null;
    function close() { if (box) { box.parentNode.removeChild(box); box = null; document.documentElement.classList.remove('nrn-lock'); } }
    function embed(url) {
      var m;
      if (/\.(mp4|webm|ogg)(\?|$)/i.test(url)) return '<video src="' + url + '" controls autoplay playsinline></video>';
      if ((m = url.match(/aparat\.com\/v\/([\w-]+)/i))) return '<iframe src="https://www.aparat.com/video/video/embed/videohash/' + m[1] + '/vt/frame" allow="autoplay; fullscreen" allowfullscreen></iframe>';
      if ((m = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([\w-]{6,})/i))) return '<iframe src="https://www.youtube.com/embed/' + m[1] + '?autoplay=1" allow="autoplay; fullscreen" allowfullscreen></iframe>';
      return '<iframe src="' + url + '" allow="autoplay; fullscreen" allowfullscreen></iframe>';
    }
    document.addEventListener('click', function (e) {
      var b = e.target && e.target.closest && e.target.closest('[data-video]');
      if (b) {
        var url = b.getAttribute('data-video');
        if (!url) return;
        e.preventDefault();
        close();
        box = document.createElement('div');
        box.className = 'nrn-lightbox';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.innerHTML = '<div class="nrn-lb-in">' + embed(url.replace(/"/g, '&quot;')) + '</div><button type="button" class="nrn-lb-x" aria-label="بستن">×</button>';
        document.body.appendChild(box);
        document.documentElement.classList.add('nrn-lock');
        box.querySelector('.nrn-lb-x').focus();
        return;
      }
      if (box && (e.target === box || (e.target.closest && e.target.closest('.nrn-lb-x')))) close();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  }

  // The page (text, hero, images) comes first; the car engine starts once everything
  // has loaded and the browser is idle, so it never competes with the first paint.
  function startDrives() {
    var roots = document.querySelectorAll('.nrn [data-ref="root"]');
    Array.prototype.forEach.call(roots, function (r) { if (!r.__nrn) r.__nrn = new Drive(r); });
  }
  function whenIdle(fn) {
    if (window.requestIdleCallback) window.requestIdleCallback(fn, { timeout: 1200 }); else setTimeout(fn, 300);
  }
  /* Performance on phones: decorative animations run only in sections near the screen,
     and pause altogether while the page is being flung. */
  function initPerf() {
    var secs = document.querySelectorAll('.nrn [data-ref="root"] > section, .nrn [data-ref="root"] > footer, .nrn [data-ref="root"] > aside');
    if (window.IntersectionObserver) {
      var io = new IntersectionObserver(function (es) {
        es.forEach(function (e) { e.target.classList.toggle('nrn-off', !e.isIntersecting); });
      }, { rootMargin: '400px 0px' });
      Array.prototype.forEach.call(secs, function (el) { io.observe(el); });
    }
    var html = document.documentElement, lastY = window.pageYOffset, lastT = performance.now(), timer = 0;
    window.addEventListener('scroll', function () {
      var t = performance.now(), y = window.pageYOffset, v = Math.abs(y - lastY) / Math.max(1, t - lastT);
      lastY = y; lastT = t;
      if (v > 2.5 && !html.classList.contains('nrn-fast')) html.classList.add('nrn-fast');
      clearTimeout(timer);
      timer = setTimeout(function () { html.classList.remove('nrn-fast'); }, 220);
    }, { passive: true });
  }

  /* Bottom bar: number of items in the WooCommerce cart (read live, so the cached page stays valid) */
  function initCartBadge() {
    var badges = document.querySelectorAll('[data-cart-count]');
    if (!badges.length || !CFG.cartApi || document.cookie.indexOf('woocommerce_items_in_cart') < 0) return;
    fetch(CFG.cartApi, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (c) {
        var n = c && (c.items_count || 0);
        Array.prototype.forEach.call(badges, function (b) { if (n > 0) { b.textContent = fa(n > 99 ? '99+' : n); b.hidden = false; } });
      }).catch(function () {});
  }

  function boot() {
    initVideos();
    initPerf();
    initCartBadge();
    if (document.readyState === 'complete') whenIdle(startDrives);
    else window.addEventListener('load', function () { whenIdle(startDrives); });
  }
  if (document.readyState !== 'loading') boot(); else document.addEventListener('DOMContentLoaded', boot);
  window.NeuronDrive = Drive;
})();
