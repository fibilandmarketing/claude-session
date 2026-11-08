/* Neuron landing – admin panel */
(function ($) {
  'use strict';
  var L = window.NRN_ADMIN || {};
  var $form = $('#nrn-form');
  if (!$form.length) return;
  var DEF = JSON.parse($('#nrn-defaults').text() || '{}');
  var dirty = false;

  function setDirty() { dirty = true; $('.nrn-save').addClass('is-dirty'); }
  function asset(v) { return !v ? '' : (/^(https?:)?\/\//.test(v) ? v : DEF.assetBase + v); }

  /* ---------------------------------------------------------- tabs */
  function openTab(id) {
    if (!$('.nrn-panel[data-panel="' + id + '"]').length) id = 'settings';
    $('.nrn-tab').removeClass('is-active').filter('[data-tab="' + id + '"]').addClass('is-active');
    $('.nrn-panel').removeClass('is-active').filter('[data-panel="' + id + '"]').addClass('is-active');
    $('#nrn-tab-input').val(id);
    if (history.replaceState) {
      var u = new URL(location.href); u.searchParams.set('tab', id); u.searchParams.delete('nrn_msg'); u.hash = '';
      history.replaceState(null, '', u.toString());
    }
  }
  $('.nrn-tabs').on('click', '.nrn-tab', function (e) { e.preventDefault(); openTab($(this).data('tab')); window.scrollTo(0, 0); });
  if (location.hash.length > 1) openTab(location.hash.slice(1));

  /* --------------------------------------------- field state markers */
  function markField($f) {
    var t = $f.data('type'), changed;
    if (t === 'img') {
      var $i = $f.find('.nrn-img');
      changed = !!$f.find('.nrn-v-url').val() || $f.find('.nrn-v-alt').val() !== String($i.data('default-alt'));
    } else {
      var $v = $f.find('.nrn-v');
      changed = $v.val() !== String($v.data('default'));
    }
    $f.toggleClass('is-changed', changed);
  }
  $form.on('input change', 'input, textarea, select', function () {
    setDirty();
    var $f = $(this).closest('.nrn-field');
    if ($f.length) markField($f);
    var $sw = $(this).filter('[data-scope="sections"]');
    if ($sw.length) $('.nrn-tab[data-tab="' + $sw.data('k') + '"]').toggleClass('is-off', !$sw.prop('checked'));
  });

  function resetField($f) {
    if ($f.data('type') === 'img') {
      var $i = $f.find('.nrn-img');
      $f.find('.nrn-v-url').val(''); $f.find('.nrn-v-id').val('0');
      $f.find('.nrn-v-alt').val($i.data('default-alt'));
      $f.find('.nrn-img-prev').attr('src', $i.data('default-src'));
    } else {
      var $v = $f.find('.nrn-v'); $v.val($v.data('default'));
    }
    markField($f); setDirty();
  }
  $form.on('click', '.nrn-reset-field', function () { resetField($(this).closest('.nrn-field')); });

  /* ------------------------------------------------- per-tab search */
  $form.on('input', '.nrn-filter', function () {
    var q = $.trim($(this).val()).toLowerCase();
    $(this).closest('.nrn-card').find('.nrn-field').each(function () {
      var $f = $(this);
      var text = ($f.find('label').text() + ' ' + $f.find('.nrn-def').text() + ' ' + ($f.find('.nrn-v').val() || '')).toLowerCase();
      $f.toggle(!q || text.indexOf(q) >= 0);
    });
  });

  /* ---------------------------------------------------------- media */
  var frame, $target;
  $form.on('click', '.nrn-pick', function (e) {
    e.preventDefault();
    $target = $(this).closest('.nrn-img');
    if (!frame) {
      frame = wp.media({ title: L.pick, button: { text: L.use }, library: { type: 'image' }, multiple: false });
      frame.on('select', function () {
        var a = frame.state().get('selection').first().toJSON();
        $target.find('.nrn-v-url').val(a.url);
        $target.find('.nrn-v-id').val(a.id);
        $target.find('.nrn-img-prev').attr('src', (a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url)).prop('hidden', false);
        var $alt = $target.find('.nrn-v-alt');
        if ($alt.length && a.alt) $alt.val(a.alt);
        var $f = $target.closest('.nrn-field'); if ($f.length) markField($f);
        refreshTitles($target.closest('.nrn-list'));
        setDirty();
      });
    }
    frame.open();
  });
  $form.on('click', '.nrn-clear-img', function () {
    var $i = $(this).closest('.nrn-img');
    $i.find('.nrn-v-url').val(''); $i.find('.nrn-img-prev').attr('src', '').prop('hidden', true);
    setDirty();
  });

  /* ------------------------------------------------------- lists */
  function refreshTitles($list) {
    ($list && $list.length ? $list : $('.nrn-list')).each(function () {
      $(this).children('.nrn-row').each(function (i) {
        var $r = $(this);
        $r.find('.nrn-row-n').text(i + 1);
        var t = $r.find('[data-f="q"], [data-f="name"], [data-f="title"]').first().val() || '';
        $r.find('.nrn-row-title').text(t);
      });
    });
  }
  refreshTitles();
  $form.on('input', '.nrn-row [data-f]', function () { refreshTitles($(this).closest('.nrn-list')); });
  $form.on('click', '.nrn-add', function () {
    var list = $(this).data('list');
    var $row = $($('#nrn-tpl-' + list).html());
    $('.nrn-list[data-list="' + list + '"]').append($row);
    $row.addClass('is-open').find('input[type=text], textarea').first().trigger('focus');
    refreshTitles(); setDirty();
  });
  $form.on('click', '.nrn-del', function () { var $l = $(this).closest('.nrn-list'); $(this).closest('.nrn-row').remove(); refreshTitles($l); setDirty(); });
  $form.on('click', '.nrn-up', function () { var $r = $(this).closest('.nrn-row'); $r.prev('.nrn-row').before($r); refreshTitles($r.parent()); setDirty(); });
  $form.on('click', '.nrn-down', function () { var $r = $(this).closest('.nrn-row'); $r.next('.nrn-row').after($r); refreshTitles($r.parent()); setDirty(); });
  $form.on('click', '.nrn-row-bar', function (e) { if ($(e.target).closest('button').length) return; $(this).closest('.nrn-row').toggleClass('is-open'); });

  function rowsOf(list) {
    return $('.nrn-list[data-list="' + list + '"]').children('.nrn-row').map(function () {
      var o = {};
      $(this).find('[data-f]').each(function () {
        var $i = $(this);
        o[$i.data('f')] = $i.is(':checkbox') ? $i.prop('checked') : $i.val();
      });
      return o;
    }).get();
  }

  function renderList(list, items) {
    var $l = $('.nrn-list[data-list="' + list + '"]').empty();
    items.forEach(function (it) {
      var $row = $($('#nrn-tpl-' + list).html());
      $row.find('[data-f]').each(function () {
        var $i = $(this), k = $i.data('f'), v = it[k];
        if ($i.is(':checkbox')) $i.prop('checked', !!v); else $i.val(v == null ? '' : v);
        if ($i.hasClass('nrn-v-url')) { var src = asset(v); $i.closest('.nrn-img').find('.nrn-img-prev').attr('src', src).prop('hidden', !src); }
      });
      $l.append($row);
    });
    refreshTitles($l);
  }

  /* ---------------------------------------------------- group reset */
  $form.on('click', '.nrn-reset-group', function () {
    if (!window.confirm(L.sure)) return;
    var $p = $(this).closest('.nrn-panel'), gid = $p.data('panel');
    $p.find('.nrn-field').each(function () { resetField($(this)); });
    $p.find('[data-scope="sections"]').prop('checked', true).trigger('change');
    if (gid === 'why') renderList('orgs', DEF.orgs);
    if (gid === 'videos') renderList('videos', DEF.videos);
    if (gid === 'faq') renderList('faq', DEF.faq);
    if (gid === 'mag') {
      renderList('mag', DEF.mag.items);
      $p.find('[data-mag-k="source"]').val(DEF.mag.source); $p.find('[data-mag-k="more"]').val(DEF.mag.more); $p.find('.nrn-mag-cat').val('0');
    }
    if (gid === 'city') {
      var byslug = {}; DEF.provinces.forEach(function (p) { byslug[p.slug] = p; });
      $p.find('.nrn-prov tr[data-slug]').each(function () {
        var p = byslug[$(this).data('slug')];
        $(this).find('.nrn-p-name').val(p.name); $(this).find('.nrn-p-n').val(p.n); $(this).find('.nrn-p-url').val('');
      });
      var md = { def: 'tehran', url: 'https://noroncar.ir/namayandegi/', count_suffix: 'نمایندگی فعال', none_text: 'هنوز نمایندگی در این استان ثبت نشده است', cta_has: 'مشاهدهٔ نمایندگی‌ها', cta_none: 'تماس با نورون', none_url: 'tel:05152220010' };
      Object.keys(md).forEach(function (k) { $p.find('[data-map-k="' + k + '"]').val(md[k]); });
    }
    setDirty();
  });

  $form.on('click', '.nrn-reset-all', function (e) { if (!window.confirm(L.sureAll)) e.preventDefault(); else dirty = false; });

  /* --------------------------------------------------------- submit */
  $form.on('submit', function () {
    var p = { fields: {}, sections: {}, settings: {}, map: {}, mag: { items: [] } };
    $('.nrn-field').each(function () {
      var $f = $(this), key = $f.data('key');
      if ($f.data('type') === 'img') {
        p.fields[key] = { url: $f.find('.nrn-v-url').val(), id: $f.find('.nrn-v-id').val(), alt: $f.find('.nrn-v-alt').val() };
      } else {
        p.fields[key] = $f.find('.nrn-v').val();
      }
    });
    $('[data-scope="sections"]').each(function () { p.sections[$(this).data('k')] = $(this).prop('checked') ? 1 : 0; });
    $('[data-scope="settings"]').each(function () {
      var $i = $(this);
      p.settings[$i.data('k')] = $i.is(':checkbox') ? ($i.prop('checked') ? 1 : 0) : $i.val();
    });
    ['faq', 'orgs', 'videos'].forEach(function (l) { p[l] = rowsOf(l); });
    p.mag = { source: $('[data-mag-k="source"]').val(), cat: $('.nrn-mag-cat').val(), more: $('[data-mag-k="more"]').val(), items: rowsOf('mag') };
    $('[data-map-k]').each(function () { p.map[$(this).data('map-k')] = $(this).val(); });
    p.provinces = $('.nrn-prov tr[data-slug]').map(function () {
      return { slug: $(this).data('slug'), name: $(this).find('.nrn-p-name').val(), n: $(this).find('.nrn-p-n').val(), url: $(this).find('.nrn-p-url').val() };
    }).get();
    $('#nrn-payload').val(JSON.stringify(p));
    dirty = false;
  });

  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = L.leave; return L.leave; } });
})(jQuery);
