#!/usr/bin/env python3
"""
Converts the Design canvas artboards (source/design/*.dc.html) into the
WordPress plugin's PHP templates, scoped CSS and the generated field schema.

  python3 build/convert.py

Outputs (inside plugin/neuron-landing/):
  templates/layout-desktop.php, templates/layout-mobile.php
  assets/css/desktop.css, assets/css/mobile.css
  assets/{img,fonts}/*            (renamed design assets)
  includes/schema-generated.php    (every editable text / image / link)
  build/report.txt                 (matching report desktop <-> mobile)
"""
import html
import json
import os
import re
import shutil
import sys
from collections import defaultdict, OrderedDict

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, 'source', 'design')
BLOBS = os.path.join(ROOT, 'source', 'blobs')
PLUGIN = os.path.join(ROOT, 'plugin', 'neuron-landing')
ASSET_MAP = json.load(open(os.path.join(ROOT, 'build', 'assets_map.json')))

VOID = {'img', 'br', 'input', 'meta', 'link', 'hr', 'source', 'wbr', 'area', 'col', 'embed', 'param', 'track'}

# Section ids, in document order, for each layout.
SECTIONS = {
    'd': ['hero', 'why', 'choose', 'compare', 'personal', 'heavy', 'cold', 'theft', 'videos', 'city', 'fleet', 'faq', 'mag', 'footer', 'todo'],
    'm': ['hero', 'why', 'choose', 'compare', 'personal', 'heavy', 'cold', 'theft', 'videos', 'city', 'fleet', 'faq', 'mag', 'footer', 'todo', 'drawer'],
}

ROLE = {
    'h1': 'تیتر اصلی', 'h2': 'تیتر', 'h3': 'تیتر کارت', 'h4': 'تیتر کوچک', 'p': 'متن',
    'a': 'متن لینک/دکمه', 'button': 'متن دکمه', 'li': 'آیتم فهرست', 'b': 'متن پررنگ', 'strong': 'متن پررنگ',
    'label': 'برچسب', 'summary': 'سؤال', 'option': 'گزینه', 'text': 'متن روی نقشه', 'small': 'متن کوچک',
    'span': 'متن', 'div': 'متن', 'i': 'متن', 'em': 'متن',
}


def asset_rel(blob_id):
    return ASSET_MAP[blob_id]


# ---------------------------------------------------------------- tokenizer
TOKEN_RE = re.compile(r'<!--.*?-->|<(/?)([a-zA-Z][\w:-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>', re.S)
ATTR_RE = re.compile(r'([^\s=/>]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?')


def tokenize(s):
    out = []
    pos = 0
    for m in TOKEN_RE.finditer(s):
        if m.start() > pos:
            out.append(('text', s[pos:m.start()]))
        raw = m.group(0)
        if raw.startswith('<!--'):
            pos = m.end()
            continue  # drop comments
        closing, name, attrs = m.group(1), m.group(2).lower(), m.group(3)
        if closing:
            out.append(('end', name, raw))
        else:
            selfclose = attrs.rstrip().endswith('/')
            out.append(('start', name, raw, parse_attrs(attrs), selfclose))
        pos = m.end()
    if pos < len(s):
        out.append(('text', s[pos:]))
    return out


def parse_attrs(a):
    res = OrderedDict()
    for m in ATTR_RE.finditer(a):
        k = m.group(1)
        if k == '/':
            continue
        v = m.group(2) if m.group(2) is not None else (m.group(3) if m.group(3) is not None else m.group(4))
        res[k] = v
    return res


def element_end(s, start):
    """Index just past the end of the element whose start tag begins at `start`."""
    m = TOKEN_RE.match(s, start)
    name = m.group(2).lower()
    if name in VOID:
        return m.end()
    depth = 0
    for t in TOKEN_RE.finditer(s, start):
        if t.group(0).startswith('<!--') or t.group(2).lower() != name:
            continue
        if t.group(1):
            depth -= 1
            if depth == 0:
                return t.end()
        else:
            if not t.group(3).rstrip().endswith('/'):
                depth += 1
    raise ValueError('unclosed ' + name)


def sibling_run(s, first_start, count):
    """(start, end) spanning `count` consecutive sibling elements starting at first_start."""
    pos = first_start
    end = None
    for i in range(count):
        m = re.compile(r'\s*').match(s, pos)
        pos = m.end()
        if s[pos] != '<':
            raise ValueError('expected element at %d: %r' % (pos, s[pos:pos + 60]))
        end = element_end(s, pos)
        pos = end
    return first_start, end


# ------------------------------------------------------------ CSS scoping
def split_top(sel, ch=','):
    parts, depth, cur = [], 0, ''
    for c in sel:
        if c in '([':
            depth += 1
        elif c in ')]':
            depth -= 1
        if c == ch and depth == 0:
            parts.append(cur)
            cur = ''
        else:
            cur += c
    parts.append(cur)
    return parts


def css_blocks(css):
    """Yield (prelude, body) for top-level blocks."""
    i, n = 0, len(css)
    while i < n:
        j = css.find('{', i)
        if j < 0:
            break
        prelude = css[i:j].strip()
        depth, k = 1, j + 1
        while depth:
            if css[k] == '{':
                depth += 1
            elif css[k] == '}':
                depth -= 1
            k += 1
        yield prelude, css[j + 1:k - 1]
        i = k


def scope_css(css, scope, prefix, kf_names):
    out = []
    for prelude, body in css_blocks(css):
        if prelude.startswith('@font-face'):
            continue  # fonts live in base.css
        if prelude.startswith('@keyframes'):
            name = prelude.split()[1]
            out.append('@keyframes %s%s{%s}' % (prefix, name, body))
            continue
        if prelude.startswith('@media') or prelude.startswith('@supports'):
            out.append('%s{%s}' % (prelude, scope_css(body, scope, prefix, kf_names)))
            continue
        sels = []
        for sel in split_top(prelude):
            sel = sel.strip()
            if sel == 'body':
                sels.append('body.nrn-body')
            elif sel.startswith('body'):
                sels.append('body.nrn-body' + sel[4:])
            else:
                sels.append(scope + ' ' + sel)
        out.append('%s{%s}' % (','.join(sels), rename_anims(body, prefix, kf_names)))
    return '\n'.join(out)


def rename_anims(decls, prefix, kf_names):
    def fix(m):
        val = m.group(2)
        for k in sorted(kf_names, key=len, reverse=True):
            val = re.sub(r'(?<![\w-])%s(?![\w-])' % re.escape(k), prefix + k, val)
        return m.group(1) + val
    return re.sub(r'(animation(?:-name)?\s*:)([^;}]*)', fix, decls)


def blob_to_rel(css, from_css=True):
    return re.sub(r'/_blob/([0-9a-f]{32})', lambda m: '../' + asset_rel(m.group(1)), css)


# ------------------------------------------------------------- the schema
class Schema:
    def __init__(self):
        self.groups = OrderedDict()  # gid -> OrderedDict(key -> field)
        self.match = {}              # (gid, kind, sig, occ) -> key
        self.counters = defaultdict(int)

    def field(self, layout, gid, kind, sig, occ, default, role, extra=None):
        mk = (gid, kind, sig, occ)
        g = self.groups.setdefault(gid, OrderedDict())
        if mk in self.match:
            key = self.match[mk]
            f = g[key]
            if layout not in f['layouts']:
                f['layouts'].append(layout)
            return key
        self.counters[gid] += 1
        key = '%s.%s%d' % (gid, {'text': 't', 'img': 'i', 'url': 'u'}[kind], self.counters[gid])
        f = OrderedDict(type=kind, role=role, default=default, layouts=[layout])
        if extra:
            f.update(extra)
        g[key] = f
        self.match[mk] = key
        return key


def norm_text(t):
    return re.sub(r'\s+', ' ', html.unescape(t)).strip()


# ----------------------------------------------------------- conversion
SOCIAL_LABELS = {'تلگرام نورون', 'اینستاگرام نورون', 'واتس‌اپ نورون', 'ایتا نورون'}
SPECIAL_ATTR_TEXT = {'data-mname': "<?php $nl->map_name(); ?>", 'data-mcount': "<?php $nl->map_count(); ?>"}


def find_regions(body, layout):
    """Regions replaced by hand-written PHP partials: list of (start, end, partial_name)."""
    regions = []
    # FAQ: the run of <details> elements
    i = body.find('<details')
    n = len(re.findall(r'<details[\s>]', body))
    regions.append(sibling_run(body, i, n) + ('faq',))
    # organisations marquee: inner of .mtrack
    i = body.find('<div class="mtrack">')
    e = element_end(body, i)
    inner_s = body.find('>', i) + 1
    inner_e = body.rfind('</div>', i, e)
    regions.append((inner_s, inner_e, 'orgs'))
    # videos: the 3 <article> cards containing "پخش ویدئوی مشتری"
    i = body.find('پخش ویدئوی مشتری ۱')
    i = body.rfind('<article', 0, i)
    regions.append(sibling_run(body, i, 3) + ('videos',))
    # magazine cards: the 3 <a> elements holding "جایگاه تصویر مقاله"
    j = body.find('alt="جایگاه تصویر مقاله"')
    i = body.rfind('<a href="#" class="neo', 0, j)
    regions.append(sibling_run(body, i, 3) + ('mag',))
    # province paths inside the map
    i = body.find('<path class="prv')
    n = len(re.findall(r'<path class="prv', body))
    regions.append(sibling_run(body, i, n) + ('map_paths',))
    # province select options (after the first placeholder option)
    i = body.find('<select')
    first = body.find('<option', i)
    second = body.find('<option', first + 1)
    n = len(re.findall(r'<option ', body[i:element_end(body, i)])) - 1
    regions.append(sibling_run(body, second, n) + ('map_options',))
    regions.sort()
    return regions


def convert_layout(layout, fname, schema):
    src = open(os.path.join(SRC, fname), encoding='utf-8').read()
    helmet = re.search(r'<helmet>\s*<style>(.*?)</style>\s*</helmet>', src, re.S).group(1)
    body = src[src.find('</helmet>') + len('</helmet>'):src.find('</x-dc>')]
    # a second <helmet> only loads Montserrat, which no rule uses: drop it (Google Fonts is slow/blocked in Iran)
    body = re.sub(r'<helmet>.*?</helmet>', '', body, flags=re.S)
    script = re.search(r'<script type="text/x-dc" data-dc-script[^>]*>(.*?)</script>', src, re.S).group(1)

    # ---- CSS
    prefix = 'n%s-' % layout
    kf = re.findall(r'@keyframes\s+([\w-]+)', helmet)
    scope = '.nrn-%s' % layout
    css = scope_css(blob_to_rel(helmet), scope, prefix, kf)
    write(os.path.join(PLUGIN, 'assets', 'css', '%s.css' % {'d': 'desktop', 'm': 'mobile'}[layout]),
          '/* generated by build/convert.py from source/design/%s; do not edit by hand */\n%s\n' % (fname, css))

    # ---- markup: template holes of the design runtime -> static initial values
    body = re.sub(r'<sc-for list="\{\{stops\}\}".*?</sc-for>', '', body, flags=re.S)
    body = body.replace('d="{{dr}}"', 'data-road="1" d="M-20 -20"')
    body = re.sub(r'd="\{\{(leg\d|d)\}\}"', 'd="M-20 -20"', body)
    body = body.replace('{{vb}}', '0 0 100 100').replace('{{clipY}}', '-100000')
    left = re.findall(r'\{\{[^}]*\}\}', body)
    left = [x for x in left if not x.endswith('Ref}}')]
    if left:
        raise ValueError('unhandled template holes: %r' % left)
    regions = find_regions(body, layout)
    pieces = []
    pos = 0
    for s, e, name in regions:
        pieces.append(('markup', body[pos:s]))
        pieces.append(('partial', name, body[s:e]))
        pos = e
    pieces.append(('markup', body[pos:]))

    out = []
    sec_list = SECTIONS[layout]
    state = {'sec_idx': -1, 'in_header': 0}
    stack = []
    occ = defaultdict(int)

    def cur_gid():
        if state['in_header']:
            return 'header'
        if state['sec_idx'] < 0:
            return 'global'
        return sec_list[state['sec_idx']]

    def next_occ(kind, sig):
        k = (cur_gid(), kind, sig)
        occ[k] += 1
        return occ[k]

    def next_occ_g(gid, kind, sig):
        if gid in ('brand', 'social'):
            return 1  # one shared field for every use
        return next_occ(kind, sig)

    root_depth = None
    for piece in pieces:
        if piece[0] == 'partial':
            name = piece[1]
            out.append("<?php $nl->partial('%s', '%s'); ?>" % (name, layout))
            write(os.path.join(ROOT, 'build', 'regions', '%s-%s.html' % (layout, name)), piece[2])
            if stack:
                stack[-1]['kids'] = True
            continue
        for tok in tokenize(piece[1]):
            if tok[0] == 'text':
                raw = tok[1]
                top = stack[-1] if stack else None
                for el in stack:
                    el['textbuf'].append(raw)
                special = next((sp for sp in SPECIAL_ATTR_TEXT if top and sp in top['attrs']), None)
                if special:
                    if raw.strip():
                        out.append(SPECIAL_ATTR_TEXT[special])
                    continue
                t = norm_text(raw)
                if not t:
                    out.append(raw)
                    continue
                parent = top['name'] if top else 'div'
                role = ROLE.get(parent, 'متن')
                cls = (top['attrs'].get('class') or '') if top else ''
                if 'kicker' in cls:
                    role = 'برچسب بالای تیتر'
                elif 'tbd' in cls:
                    role = 'نشان «نیازمند تأیید»'
                elif 'phb' in cls:
                    role = 'برچسب جایگاه تصویر'
                elif 'grad' in cls:
                    role = 'بخش رنگی تیتر'
                elif 'btn' in cls:
                    role = 'متن دکمه'
                key = schema.field(layout, cur_gid(), 'text', t, next_occ('text', t), t, role, {'long': len(t) > 90})
                lead = raw[:len(raw) - len(raw.lstrip())]
                trail = raw[len(raw.rstrip()):]
                out.append("%s<?php $nl->t('%s'); ?>%s" % (lead, key, trail))
                if top:
                    top['fields'].append(key)
                continue
            if tok[0] == 'end':
                name = tok[1]
                if name in VOID:
                    continue
                if not stack or stack[-1]['name'] != name:
                    raise ValueError('unbalanced </%s> (open: %s)' % (name, stack[-1]['name'] if stack else None))
                el = stack.pop()
                out.append(tok[2])
                if el['url_idx'] is not None:
                    vis = norm_text(''.join(el['textbuf']))
                    lab = el['aria'] or vis
                    href = el['attrs']['href']
                    gid = 'social' if lab in SOCIAL_LABELS else el['gid']
                    sig = href + '|' + lab
                    k = (gid, 'url', sig)
                    occ[k] += 1
                    key = schema.field(layout, gid, 'url', sig, 1 if gid == 'social' else occ[k], href, 'لینک', {'label': lab})
                    out[el['url_idx']] = out[el['url_idx']].replace('\x00URL\x00', "<?php $nl->url('%s'); ?>" % key)
                if el['name'] == 'header':
                    state['in_header'] -= 1
                if not el['kids'] and len(el['fields']) == 1 and el['name'] not in ('option', 'text', 'textarea', 'title', 'summary'):
                    out.insert(el['idx'], "<?php if ($nl->has('%s')): ?>" % el['fields'][0])
                    out.append('<?php endif; ?>')
                if el['wrapper']:
                    out.append('<?php endif; ?>')
                if stack:
                    stack[-1]['kids'] = True
                    stack[-1]['fields'].extend(el['fields'])
                continue
            # start tag
            _, name, raw, attrs, selfclose = tok
            if attrs.get('ref') == '{{rootRef}}':
                root_depth = len(stack)
            wrapper = False
            if root_depth is not None and len(stack) == root_depth + 1 and (
                    name in ('section', 'footer', 'aside') or (name == 'div' and 'data-drawer' in attrs)):
                state['sec_idx'] += 1
                gid = sec_list[state['sec_idx']]
                if gid not in ('hero', 'footer'):
                    out.append("<?php if ($nl->on('%s')): ?>" % gid)
                    wrapper = True
            if name == 'header':
                state['in_header'] += 1
            raw, url_pending = rewrite_tag(raw, attrs, name, layout, schema, cur_gid, next_occ_g)
            idx = len(out)
            out.append(raw)
            if name in VOID or selfclose:
                if wrapper:
                    out.append('<?php endif; ?>')
                if stack and name != 'br':
                    stack[-1]['kids'] = True
                continue
            stack.append({'name': name, 'attrs': attrs, 'idx': idx, 'wrapper': wrapper, 'fields': [], 'kids': False,
                          'textbuf': [], 'url_idx': idx if url_pending else None,
                          'aria': html.unescape(attrs.get('aria-label') or ''), 'gid': cur_gid()})
    if stack:
        raise ValueError('unclosed: ' + ','.join(e['name'] for e in stack))
    return ''.join(out), script, css


def rewrite_tag(raw, attrs, name, layout, schema, cur_gid, next_occ_g):
    gid = cur_gid()
    url_pending = False
    m = re.search(r'\sref="\{\{(\w+)Ref\}\}"', raw)
    if m:
        raw = raw.replace(m.group(0), ' data-ref="%s"' % m.group(1))
    if name == 'img' and (attrs.get('src') or '').startswith('/_blob/'):
        bid = attrs['src'][7:]
        rel = asset_rel(bid)
        alt = html.unescape(attrs.get('alt') or '')
        if rel == 'img/logo.png':
            gid = 'brand'
        elif rel.startswith('img/social-'):
            gid = 'social'
        key = schema.field(layout, gid, 'img', rel, next_occ_g(gid, 'img', rel), rel, 'تصویر', {'alt': alt})
        raw = raw.replace('src="%s"' % attrs['src'], "src=\"<?php $nl->img('%s'); ?>\"" % key)
        if alt:
            raw = raw.replace('alt="%s"' % attrs['alt'], "alt=\"<?php $nl->alt('%s'); ?>\"" % key)
    if name == 'a' and 'href' in attrs and not re.match(r'#[A-Za-z]', attrs['href']):
        raw = raw.replace('href="%s"' % attrs['href'], 'href="\x00URL\x00"', 1)
        url_pending = True
    if layout == 'm' and 'data-ref="root"' in raw:
        raw = raw.replace('width: 390px;', 'width: 100%;')
    return raw, url_pending


def write(path, content):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)


def main():
    schema = Schema()
    results = {}
    for layout, fname in (('d', 'Main.dc.html'), ('m', 'Mobile.dc.html')):
        php, script, css = convert_layout(layout, fname, schema)
        results[layout] = php
        write(os.path.join(PLUGIN, 'templates', 'layout-%s.php' % {'d': 'desktop', 'm': 'mobile'}[layout]),
              "<?php\n/* generated by build/convert.py from source/design/%s; do not edit by hand */\ndefined('ABSPATH') || exit;\n?>%s" % (fname, php))
        write(os.path.join(ROOT, 'build', 'regions', '%s-script.js' % layout), script)
    # assets
    for bid, rel in ASSET_MAP.items():
        srcf = next(f for f in os.listdir(BLOBS) if f.startswith(bid))
        dst = os.path.join(PLUGIN, 'assets', rel)
        os.makedirs(os.path.dirname(dst), exist_ok=True)
        shutil.copyfile(os.path.join(BLOBS, srcf), dst)
    # schema -> php
    write(os.path.join(PLUGIN, 'includes', 'schema-generated.php'), schema_php(schema))
    # report
    lines = []
    for gid, fields in schema.groups.items():
        for k, f in fields.items():
            if len(f['layouts']) == 1:
                lines.append('%-10s %-14s only-%s %-5s %s' % (gid, k, f['layouts'][0], f['type'], str(f['default'])[:70]))
    write(os.path.join(ROOT, 'build', 'report.txt'), '\n'.join(lines) + '\n')
    total = sum(len(f) for f in schema.groups.values())
    print('fields:', total, 'single-layout:', len(lines))
    for gid, fields in schema.groups.items():
        print('  %-10s %d' % (gid, len(fields)))


def php_str(s):
    return "'" + str(s).replace('\\', '\\\\').replace("'", "\\'") + "'"


def schema_php(schema):
    out = ["<?php\n/* generated by build/convert.py; do not edit by hand */\ndefined('ABSPATH') || exit;\nreturn array(\n"]
    for gid, fields in schema.groups.items():
        out.append('  %s => array(\n' % php_str(gid))
        for k, f in fields.items():
            parts = ["'type' => %s" % php_str(f['type']), "'role' => %s" % php_str(f['role']),
                     "'default' => %s" % php_str(f['default']), "'layouts' => %s" % php_str(''.join(f['layouts']))]
            if f.get('long'):
                parts.append("'long' => true")
            if 'alt' in f:
                parts.append("'alt' => %s" % php_str(f['alt']))
            if f.get('label'):
                parts.append("'label' => %s" % php_str(f['label']))
            if f.get('text'):
                parts.append("'text' => %s" % php_str(f['text']))
            out.append('    %s => array(%s),\n' % (php_str(k), ', '.join(parts)))
        out.append('  ),\n')
    out.append(');\n')
    return ''.join(out)


if __name__ == '__main__':
    main()
