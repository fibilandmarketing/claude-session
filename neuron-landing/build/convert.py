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
IMG_SIZES = json.load(open(os.path.join(ROOT, 'build', 'image_sizes.json')))

VOID = {'img', 'br', 'input', 'meta', 'link', 'hr', 'source', 'wbr', 'area', 'col', 'embed', 'param', 'track'}

# Section ids, in document order, for each layout.
SECTIONS = {
    'd': ['hero', 'why', 'products', 'choose', 'compare', 'personal', 'heavy', 'cold', 'theft', 'videos', 'city', 'fleet', 'faq', 'mag', 'footer', 'todo'],
    'm': ['hero', 'why', 'products', 'choose', 'compare', 'personal', 'heavy', 'cold', 'theft', 'videos', 'city', 'fleet', 'faq', 'mag', 'footer', 'todo', 'drawer'],
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
    """Editable fields, grouped by page section (gid) and, inside it, by card."""

    def __init__(self):
        self.groups = OrderedDict()   # gid -> OrderedDict(key -> field)
        self.cards = OrderedDict()    # gid -> OrderedDict(card_key -> title)
        self.match = {}               # (gid, kind, sig, occ) -> key
        self.counters = defaultdict(int)

    def card(self, gid, ckey, title):
        c = self.cards.setdefault(gid, OrderedDict())
        if ckey not in c:
            c[ckey] = title
        return ckey

    def field(self, layout, gid, kind, sig, occ, default, role, extra=None):
        mk = (gid, kind, sig, occ)
        g = self.groups.setdefault(gid, OrderedDict())
        if mk in self.match:
            key = self.match[mk]
            f = g[key]
            if layout not in f['layouts']:
                f['layouts'].append(layout)
            if kind == 'url':
                f['defaults'][layout] = extra['href']
            return key
        self.counters[gid] += 1
        key = '%s.%s%d' % (gid, {'text': 't', 'img': 'i', 'url': 'u'}[kind], self.counters[gid])
        f = OrderedDict(type=kind, role=role, default=default, layouts=[layout])
        if kind == 'url':
            f['defaults'] = OrderedDict([(layout, extra['href'])])
            extra = {k: v for k, v in extra.items() if k != 'href'}
        if extra:
            f.update(extra)
        g[key] = f
        self.match[mk] = key
        return key


def norm_text(t):
    return re.sub(r'\s+', ' ', html.unescape(t)).strip()


# ----------------------------------------------------------- the tree
class Node:
    __slots__ = ('tag', 'raw', 'attrs', 'children', 'close', 'parent')

    def __init__(self, tag, raw, attrs, close=''):
        self.tag, self.raw, self.attrs, self.close = tag, raw, attrs, close
        self.children = []
        self.parent = None

    @property
    def cls(self):
        return set((self.attrs.get('class') or '').split())


class Text:
    __slots__ = ('raw', 'parent')

    def __init__(self, raw):
        self.raw = raw
        self.parent = None


class Partial:
    __slots__ = ('name', 'parent')

    def __init__(self, name):
        self.name = name
        self.parent = None


def build_tree(pieces):
    root = Node('#root', '', {})
    stack = [root]

    def add(n):
        n.parent = stack[-1]
        stack[-1].children.append(n)

    for piece in pieces:
        if piece[0] == 'partial':
            add(Partial(piece[1]))
            continue
        for tok in tokenize(piece[1]):
            if tok[0] == 'text':
                add(Text(tok[1]))
            elif tok[0] == 'end':
                if tok[1] in VOID:
                    continue
                if stack[-1].tag != tok[1]:
                    raise ValueError('unbalanced </%s> in <%s>' % (tok[1], stack[-1].tag))
                stack[-1].close = tok[2]
                stack.pop()
            else:
                _, name, raw, attrs, selfclose = tok
                n = Node(name, raw, attrs)
                add(n)
                if name not in VOID and not selfclose:
                    stack.append(n)
    if len(stack) != 1:
        raise ValueError('unclosed: ' + ','.join(n.tag for n in stack[1:]))
    return root


INLINE_FMT = {'span', 'b', 'strong', 'em', 'i', 'small', 'bdi', 'sup', 'sub', 'u'}
CARD_CLASSES = {'neo', 'neo-sm', 'glass', 'glass-l', 'dark', 'mapp', 'org'}
HEAD_TAGS = ('h3', 'h4', 'h2', 'b', 'strong', 'label', 'summary')
NO_REMOVE = {'option', 'text', 'title', 'summary', 'textarea', 'select'}


def has_text(n):
    if isinstance(n, Text):
        return bool(n.raw.strip())
    if isinstance(n, Partial):
        return True
    if n.tag in ('style', 'script'):
        return False
    return any(has_text(c) for c in n.children)


def has_field_img(n):
    if isinstance(n, Node):
        if n.tag == 'img' and (n.attrs.get('src') or '').startswith('/_blob/'):
            return True
        return any(has_field_img(c) for c in n.children)
    return False


def is_inline(n):
    """An inline formatting element holding only text (one bracketed part of a field)."""
    if not isinstance(n, Node) or n.tag not in INLINE_FMT:
        return False
    if n.cls & {'tbd', 'phb', 'phb-d', 'kicker'}:
        return False  # badges / labels are separate fields
    if any(k.startswith('data-') for k in n.attrs) or 'ref' in n.attrs:
        return False
    for c in n.children:
        if isinstance(c, Text):
            continue
        return False
    return has_text(n)


def is_decor(n):
    """Elements without text or editable images: kept verbatim (icons, dots, svg...)."""
    return isinstance(n, Node) and n.tag not in ('br',) and not has_text(n) and not has_field_img(n) \
        and not (n.tag == 'a' and 'href' in n.attrs)


def text_of(n):
    if isinstance(n, Text):
        return n.raw
    if isinstance(n, Partial):
        return ''
    if n.tag in ('style', 'script', 'svg'):
        return ''
    return ' '.join(text_of(c) for c in n.children)


def card_title(n):
    for tag in HEAD_TAGS:
        found = find_first(n, tag)
        if found is not None:
            t = norm_text(text_of(found))
            if t:
                return t
    t = norm_text(text_of(n))
    return t[:60] if t else 'کارت'


def find_first(n, tag):
    for c in n.children:
        if isinstance(c, Node):
            if c.tag == tag and has_text(c):
                return c
            r = find_first(c, tag)
            if r is not None:
                return r
    return None


def is_card(n):
    if not isinstance(n, Node):
        return False
    if n.tag in ('section', 'footer', 'header', 'aside', 'svg', 'nav', 'ol', 'ul'):
        return False
    if n.tag in ('article', 'details'):
        return True
    return bool(n.cls & CARD_CLASSES) and n.tag in ('div', 'a', 'li', 'article', 'aside', 'label', 'form')


# ----------------------------------------------------------- conversion
SOCIAL_LABELS = {'تلگرام نورون', 'اینستاگرام نورون', 'واتس‌اپ نورون', 'ایتا نورون'}
SPECIAL_ATTR_TEXT = {'data-mname': "<?php $nl->map_name(); ?>", 'data-mcount': "<?php $nl->map_count(); ?>"}
PATCHES = {
    # ('replace', anchor, file)      : the element starting at anchor becomes the file's markup
    # ('remove', anchor)             : the element starting at anchor is dropped
    # ('append', container, file)    : the file's markup goes at the end of the element starting at container
    'd': [
        # "become a Neuron dealer" moves from the fleet section to buy & install (light version)
        ('remove', '<div class="glass reveal" style="position: relative; overflow: hidden; border-radius: 36px; padding: 40px 44px'),
        ('append', ('<section id="city"', '<div class="wrap"'), 'd-agent-card.html'),
    ],
    'm': [
        # the fleet card becomes a full card like the S350 / S600 cards (dark, as on desktop)
        ('replace', '<article class="dark reveal" style="border-radius: 30px; padding: 12px; display: flex; gap: 14px', 'm-fleet-card.html'),
        ('remove', '<div class="glass reveal" style="border-radius: 28px; padding: 22px 18px; display: flex; flex-direction: column; gap: 12px; background: linear-gradient(140deg'),
        ('append', ('<section id="m-city"',), 'm-agent-card.html'),
    ],
}


def apply_patch(body, op):
    def patch_file(name):
        return open(os.path.join(ROOT, 'build', 'patches', name), encoding='utf-8').read().strip()
    if op[0] == 'replace' or op[0] == 'remove':
        i = body.find(op[1])
        if i < 0 or body.count(op[1]) != 1:
            raise ValueError('patch anchor not found or not unique: ' + op[1])
        new = patch_file(op[2]) if op[0] == 'replace' else ''
        end = element_end(body, i)
        if not new and body[end:end + 1] == '\n':
            end += 1
        return body[:i] + new + body[end:]
    if op[0] == 'append':
        i = body.find(op[1][0])
        for a in op[1][1:]:
            i = body.find(a, i)
        if i < 0:
            raise ValueError('patch container not found: %r' % (op[1],))
        end = element_end(body, i)
        close = body.rfind('</', i, end)
        return body[:close] + patch_file(op[2]) + '\n' + body[close:]
    raise ValueError(op[0])


def find_regions(body, layout):
    """Regions replaced by hand-written PHP partials: list of (start, end, partial_name)."""
    regions = []
    i = body.find('<details')
    n = len(re.findall(r'<details[\s>]', body))
    regions.append(sibling_run(body, i, n) + ('faq',))
    i = body.find('<div class="mtrack">')
    e = element_end(body, i)
    regions.append((body.find('>', i) + 1, body.rfind('</div>', i, e), 'orgs'))
    i = body.rfind('<article', 0, body.find('پخش ویدئوی مشتری ۱'))
    regions.append(sibling_run(body, i, 3) + ('videos',))
    j = body.find('alt="جایگاه تصویر مقاله"')
    i = body.rfind('<a href="#" class="neo', 0, j)
    regions.append(sibling_run(body, i, 3) + ('mag',))
    i = body.find('<path class="prv')
    n = len(re.findall(r'<path class="prv', body))
    regions.append(sibling_run(body, i, n) + ('map_paths',))
    i = body.find('<select')
    first = body.find('<option', i)
    second = body.find('<option', first + 1)
    n = len(re.findall(r'<option ', body[i:element_end(body, i)])) - 1
    regions.append(sibling_run(body, second, n) + ('map_options',))
    # "all products" carousel cards (WooCommerce or manual)
    i = body.find('<a href="#" class="pcard')
    n = len(re.findall(r'<a href="#" class="pcard', body))
    regions.append(sibling_run(body, i, n) + ('products',))
    # footer trust badges (eNamad, Samandehi, knowledge-based): image / link / HTML code
    j = body.find('alt="جایگاه نماد اینماد"')
    i = body.rfind('<div style="position: relative; width:', 0, j)
    regions.append(sibling_run(body, i, 3) + ('badges',))
    regions.sort()
    return regions


class Converter:
    def __init__(self, layout, schema):
        self.L = layout
        self.schema = schema
        self.occ = defaultdict(int)
        self.sec_list = SECTIONS[layout]
        self.sec_idx = -1
        self.root_node = None

    # -- context helpers
    def gid_of(self, n):
        while n is not None and isinstance(n, (Node, Text, Partial)):
            if isinstance(n, Node) and n.tag == 'header':
                return 'header'
            n = n.parent
        return self.sec_list[self.sec_idx] if self.sec_idx >= 0 else 'global'

    def card_of(self, n, gid):
        """Outermost card-like ancestor inside the current section."""
        card = None
        p = n
        while p is not None and isinstance(p, Node) and p.tag not in ('section', 'footer', 'aside', '#root'):
            if p.tag == 'header':
                break
            if is_card(p):
                card = p
            if p.parent is not None and isinstance(p.parent, Node) and 'data-drawer' in p.parent.attrs:
                break
            p = p.parent
        if card is None:
            return ''
        title = card_title(card)
        ckey = '%s|%s' % (gid, title)
        return self.schema.card(gid, ckey, title)

    def next_occ(self, gid, kind, sig):
        k = (gid, kind, sig)
        self.occ[k] += 1
        return self.occ[k]

    # -- rendering
    def render(self, n):
        if isinstance(n, Partial):
            return "<?php $nl->partial('%s', '%s'); ?>" % (n.name, self.L)
        if isinstance(n, Text):
            return n.raw
        if n.tag == '#root':
            return ''.join(self.render(c) for c in n.children)
        out = []
        wrapper = False
        if self.root_node is None and n.attrs.get('ref') == '{{rootRef}}':
            self.root_node = n
        if self.root_node is not None and n.parent is self.root_node and (
                n.tag in ('section', 'footer', 'aside') or (n.tag == 'div' and 'data-drawer' in n.attrs)):
            self.sec_idx += 1
            gid = self.sec_list[self.sec_idx]
            if gid not in ('hero', 'footer'):
                out.append("<?php if ($nl->on('%s')): ?>" % gid)
                wrapper = True
        gid = self.gid_of(n)
        open_raw = self.open_tag(n, gid)
        if n.tag in VOID or not n.close and not n.children and n.raw.rstrip().endswith('/>'):
            out.append(open_raw)
            if wrapper:
                out.append('<?php endif; ?>')
            return ''.join(out)

        if n.tag in ('style', 'script'):
            inner = ''.join(c.raw if isinstance(c, Text) else self.render(c) for c in n.children)
            out.append(open_raw + inner + n.close)
            return ''.join(out)

        special = next((sp for sp in SPECIAL_ATTR_TEXT if sp in n.attrs), None)
        runs_made = []
        if special:
            inner = SPECIAL_ATTR_TEXT[special]
        else:
            inner, runs_made = self.render_children(n, gid)

        html_el = open_raw + inner + n.close
        # links: create the URL field now that the visible text is known
        if '\x00URL\x00' in html_el:
            label = norm_text(n.attrs.get('aria-label') or '') or self.link_label(n)
            lgid = 'social' if label in SOCIAL_LABELS else gid
            href = n.attrs['href']
            sig = label or href
            occ = 1 if lgid == 'social' else self.next_occ(lgid, 'url', sig)
            key = self.schema.field(self.L, lgid, 'url', sig, occ, href, 'لینک',
                                    {'label': label, 'href': href, 'card': self.card_of(n, lgid) if lgid != 'social' else ''})
            if len(runs_made) == 1:
                tf = self.schema.groups[gid][runs_made[0]]
                tf['link'] = key
                self.schema.groups[lgid][key]['attached'] = runs_made[0]
            html_el = html_el.replace('\x00URL\x00', "<?php $nl->url('%s', '%s'); ?>" % (key, self.L), 1)
        # an element holding a single text field disappears when that field is emptied
        sole = self.sole_run(n)
        if sole and runs_made and n.tag not in NO_REMOVE:
            html_el = "<?php if ($nl->has('%s')): ?>%s<?php endif; ?>" % (runs_made[0], html_el)
        out.append(html_el)
        if wrapper:
            out.append('<?php endif; ?>')
        return ''.join(out)

    def link_label(self, n):
        return norm_text(text_of(n))[:80]

    def sole_run(self, n):
        """True when n's content is one text run plus decoration only."""
        runs = 0
        in_run = False
        for c in n.children:
            if isinstance(c, Text):
                if c.raw.strip():
                    if not in_run:
                        runs += 1
                    in_run = True
                continue
            if isinstance(c, Partial):
                return False
            if c.tag == 'br' or is_inline(c):
                if not in_run:
                    runs += 1
                in_run = True
                continue
            if is_decor(c):
                in_run = False
                continue
            return False
        return runs == 1

    def render_children(self, n, gid):
        out = []
        made = []
        buf = []

        def flush():
            if not buf:
                return
            if any((isinstance(x, Text) and x.raw.strip()) or (isinstance(x, Node) and x.tag != 'br') for x in buf):
                s, key = self.make_run(buf, n, gid)
                out.append(s)
                if key:
                    made.append(key)
            else:
                out.append(''.join(x.raw if isinstance(x, Text) else x.raw for x in buf))
            buf.clear()

        for c in n.children:
            if isinstance(c, Text) or (isinstance(c, Node) and (c.tag == 'br' or is_inline(c))):
                buf.append(c)
                continue
            flush()
            out.append(self.render(c))
        flush()
        return ''.join(out), made

    def make_run(self, items, parent, gid):
        # leading / trailing whitespace stays outside the field
        lead = trail = ''
        if isinstance(items[0], Text):
            lead = items[0].raw[:len(items[0].raw) - len(items[0].raw.lstrip())]
        if isinstance(items[-1], Text):
            trail = items[-1].raw[len(items[-1].raw.rstrip()):]
        # a run that is just one inline element (e.g. <span class="md-t">text</span>): plain field inside it
        core = [x for x in items if not (isinstance(x, Text) and not x.raw.strip())]
        if len(core) == 1 and isinstance(core[0], Node) and core[0].tag != 'br':
            el = core[0]
            before = ''.join(x.raw for x in items[:items.index(el)])
            after = ''.join(x.raw for x in items[items.index(el) + 1:])
            s, key = self.make_run(list(el.children), el, gid)
            if key and self.sole_run(el) and el.tag not in NO_REMOVE:
                return "%s<?php if ($nl->has('%s')): ?>%s%s%s<?php endif; ?>%s" % (before, key, el.raw, s, el.close, after), key
            return before + el.raw + s + el.close + after, key
        # several inline elements with no plain text between them: one field each
        inl = [x for x in core if isinstance(x, Node) and x.tag != 'br']
        if len(inl) > 1 and len(inl) == len(core):
            parts, first = [], None
            for x in items:
                if isinstance(x, Node) and x.tag != 'br':
                    s2, k2 = self.make_run([x], parent, gid)
                    parts.append(s2)
                    first = first or k2
                else:
                    parts.append(x.raw)
            return ''.join(parts), first
        value, tags, has_br = '', [], False
        for x in items:
            if isinstance(x, Text):
                value += re.sub(r'\s+', ' ', html.unescape(x.raw))
            elif x.tag == 'br':
                value += '\n'
                has_br = True
            else:
                tags.append([x.raw, x.close])
                value += '[' + norm_text(text_of(x)) + ']'
        value = '\n'.join(line.strip() for line in value.strip().split('\n'))
        if not value:
            return ''.join(x.raw for x in items), None
        cls = parent.cls
        role = ROLE.get(parent.tag, 'متن')
        note = bool(cls & {'tbd', 'phb', 'phb-d'})
        if 'kicker' in cls:
            role = 'برچسب بالای تیتر'
        elif 'tbd' in cls:
            role = 'نشان «نیازمند تأیید»'
        elif cls & {'phb', 'phb-d'}:
            role = 'برچسب جایگاه تصویر'
        elif parent.tag == 'a' or 'btn' in cls:
            role = 'دکمه / لینک'
        if re.fullmatch(r'[+\-]?[۰-۹0-9٬,.]+\+?', value):
            role = 'عدد / قیمت'
        elif 'تومان' in value and re.search(r'[۰-۹0-9][۰-۹0-9٬,]{3,}', value):
            role = 'قیمت'
        card = '' if note else self.card_of(parent, gid)
        extra = {'long': len(value) > 90 or has_br, 'card': '_notes' if note else card}
        if tags:
            extra['tags'] = tags
        if has_br:
            extra['br'] = True
        key = self.schema.field(self.L, gid, 'text', value, self.next_occ(gid, 'text', value), value, role, extra)
        fn = 'rich' if (tags or has_br) else 't'
        return "%s<?php $nl->%s('%s'); ?>%s" % (lead, fn, key, trail), key

    def open_tag(self, n, gid):
        raw = n.raw
        m = re.search(r'\sref="\{\{(\w+)Ref\}\}"', raw)
        if m:
            raw = raw.replace(m.group(0), ' data-ref="%s"' % m.group(1))
        if n.tag == 'img' and (n.attrs.get('src') or '').startswith('/_blob/'):
            rel = asset_rel(n.attrs['src'][7:])
            alt = html.unescape(n.attrs.get('alt') or '')
            igid = 'brand' if rel == 'img/logo.png' else ('social' if rel.startswith('img/social-') else gid)
            # an image is paired across desktop / mobile by file + the card it sits in
            # (the order alone pairs the wrong ones when a layout shows the same file twice)
            card = self.card_of(n, igid) if igid not in ('brand', 'social') else ''
            sig = rel + '|' + card
            occ = 1 if igid in ('brand', 'social') else self.next_occ(igid, 'img', sig)
            key = self.schema.field(self.L, igid, 'img', sig, occ, rel, 'تصویر', {'alt': alt, 'card': card})
            raw = raw.replace('src="%s"' % n.attrs['src'], "src=\"<?php $nl->img('%s'); ?>\"" % key)
            if alt:
                raw = raw.replace('alt="%s"' % n.attrs['alt'], "alt=\"<?php $nl->alt('%s'); ?>\"" % key)
            # intrinsic size reserves the space before a lazy image arrives (no layout jump)
            if rel in IMG_SIZES and 'width=' not in raw:
                raw = raw[:-1] + ' width="%d" height="%d">' % tuple(IMG_SIZES[rel])
            # below-the-fold images load lazily (the hero and header stay eager)
            if gid not in ('hero', 'header') and 'loading=' not in raw:
                raw = raw[:-1] + ' loading="lazy" decoding="async">'
            elif gid == 'hero' and 'hero-car' in (n.attrs.get('class') or ''):
                raw = raw[:-1] + ' fetchpriority="high">'
        if n.tag == 'a' and 'href' in n.attrs:
            raw = raw.replace('href="%s"' % n.attrs['href'], 'href="\x00URL\x00"', 1)
        if self.L == 'm' and 'data-ref="root"' in raw:
            raw = raw.replace('width: 390px;', 'width: 100%;')
        return raw


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
    css = scope_css(blob_to_rel(helmet), '.nrn-%s' % layout, prefix, kf)
    # the section map patterns as pre-rendered WebP (SVG patterns were ~60% of the raster work while scrolling)
    sfx = '' if layout == 'd' else '-m'
    css = css.replace('img/bg-pearl-map.svg', 'img/bg-pearl-map%s.webp' % sfx).replace('img/bg-dark-map.svg', 'img/bg-dark-map%s.webp' % sfx)
    write(os.path.join(PLUGIN, 'assets', 'css', '%s.css' % {'d': 'desktop', 'm': 'mobile'}[layout]),
          '/* generated by build/convert.py from source/design/%s; do not edit by hand */\n%s\n' % (fname, css))

    # ---- design fixes requested after review
    for op in PATCHES.get(layout, []):
        body = apply_patch(body, op)

    # ---- template holes of the design runtime -> static initial values
    body = re.sub(r'<sc-for list="\{\{stops\}\}".*?</sc-for>', '', body, flags=re.S)
    body = body.replace('d="{{dr}}"', 'data-road="1" d="M-20 -20"')
    body = re.sub(r'd="\{\{(leg\d|d)\}\}"', 'd="M-20 -20"', body)
    body = body.replace('{{vb}}', '0 0 100 100').replace('{{clipY}}', '-100000')
    left = [x for x in re.findall(r'\{\{[^}]*\}\}', body) if not x.endswith('Ref}}')]
    if left:
        raise ValueError('unhandled template holes: %r' % left)

    regions = find_regions(body, layout)
    pieces, pos = [], 0
    for s, e, name in regions:
        pieces.append(('markup', body[pos:s]))
        pieces.append(('partial', name, body[s:e]))
        write(os.path.join(ROOT, 'build', 'regions', '%s-%s.html' % (layout, name)), body[s:e])
        pos = e
    pieces.append(('markup', body[pos:]))
    tree = build_tree(pieces)
    php = Converter(layout, schema).render(tree)
    return php, script, css


def write(path, content):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)


def merge_lone_images(schema):
    """An image shown once on desktop and once on mobile (same file, same section) is one field,
    even when its card got a different title in each layout. Returns {mobile key: desktop key}."""
    remap = {}
    for gid, fields in schema.groups.items():
        d_only = defaultdict(list)
        m_only = defaultdict(list)
        for k, f in fields.items():
            if f['type'] == 'img' and f['layouts'] == ['d']:
                d_only[f['default']].append(k)
            elif f['type'] == 'img' and f['layouts'] == ['m']:
                m_only[f['default']].append(k)
        for rel, dk in d_only.items():
            mk = m_only.get(rel, [])
            if len(dk) == 1 and len(mk) == 1:
                remap[mk[0]] = dk[0]
                fields[dk[0]]['layouts'] = ['d', 'm']
                del fields[mk[0]]
    return remap


def main():
    schema = Schema()
    outs = {}
    for layout, fname in (('d', 'Main.dc.html'), ('m', 'Mobile.dc.html')):
        php, script, css = convert_layout(layout, fname, schema)
        if '\x00' in php:
            raise ValueError('unresolved link placeholder')
        outs[layout] = (fname, php, script)
    remap = merge_lone_images(schema)
    for layout, (fname, php, script) in outs.items():
        for mk, dk in remap.items():
            php = php.replace("('%s')" % mk, "('%s')" % dk)
        write(os.path.join(PLUGIN, 'templates', 'layout-%s.php' % {'d': 'desktop', 'm': 'mobile'}[layout]),
              "<?php\n/* generated by build/convert.py from source/design/%s; do not edit by hand */\ndefined('ABSPATH') || exit;\n?>%s" % (fname, php))
        write(os.path.join(ROOT, 'build', 'regions', '%s-script.js' % layout), script)
    for bid, rel in ASSET_MAP.items():
        if rel in ('img/bg-pearl-map.svg', 'img/bg-dark-map.svg'):
            continue  # shipped as pre-rendered WebP (source/raster)
        srcf = next(f for f in os.listdir(BLOBS) if f.startswith(bid))
        dst = os.path.join(PLUGIN, 'assets', rel)
        os.makedirs(os.path.dirname(dst), exist_ok=True)
        shutil.copyfile(os.path.join(BLOBS, srcf), dst)
    for f in os.listdir(os.path.join(ROOT, 'source', 'raster')):
        shutil.copyfile(os.path.join(ROOT, 'source', 'raster', f), os.path.join(PLUGIN, 'assets', 'img', f))
    try:
        from fontTools.ttLib import TTFont
        for w in (400, 500, 700):
            f = TTFont(os.path.join(PLUGIN, 'assets', 'fonts', 'vazir-%d.ttf' % w))
            f.flavor = 'woff2'
            f.save(os.path.join(PLUGIN, 'assets', 'fonts', 'vazir-%d.woff2' % w))
    except ImportError:
        print('fontTools/brotli not installed: WOFF2 fonts not regenerated (pip install fonttools brotli)')
    write(os.path.join(PLUGIN, 'includes', 'schema-generated.php'), schema_php(schema))
    write(os.path.join(PLUGIN, 'includes', 'migrate-v1.php'), migration_php(schema))
    write(os.path.join(PLUGIN, 'includes', 'migrate-keys.php'), keymap_php(schema))
    lines = []
    for gid, fields in schema.groups.items():
        for k, f in fields.items():
            lines.append('%-9s %-12s %-3s %-4s %-28s %s' % (gid, k, ''.join(f['layouts']), f['type'], (f.get('card') or '-')[:28], str(f['default'])[:60].replace('\n', '⏎')))
    write(os.path.join(ROOT, 'build', 'report.txt'), '\n'.join(lines) + '\n')
    total = sum(len(f) for f in schema.groups.values())
    print('fields:', total, 'cards:', sum(len(c) for c in schema.cards.values()))
    for gid, fields in schema.groups.items():
        print('  %-9s %3d fields  %2d cards' % (gid, len(fields), len(schema.cards.get(gid, {}))))


def migration_php(schema):
    """Maps the field keys of version 1.0 to the new ones where the design value is the same."""
    import subprocess
    v1 = os.path.join(ROOT, 'build', 'schema-v1.php')
    if not os.path.exists(v1):
        return "<?php defined('ABSPATH') || exit; return array();\n"
    js = subprocess.run(['php', '-r', 'define("ABSPATH",1); echo json_encode(require $argv[1]);', v1], capture_output=True, text=True).stdout
    old = json.loads(js)
    mapping = {}
    used = set()
    for gid, fields in old.items():
        for ok, of in fields.items():
            for nk, nf in schema.groups.get(gid, {}).items():
                if nk in used or nf['type'] != of['type']:
                    continue
                if of['type'] == 'url':
                    same = of.get('label', '') and nf.get('label') == of.get('label') and nf['default'] == of['default']
                else:
                    same = nf['default'] == of['default']
                if same:
                    mapping[ok] = nk
                    used.add(nk)
                    break
    return "<?php\n/* generated by build/convert.py: version-1.0 field keys -> current keys */\ndefined('ABSPATH') || exit;\nreturn " + \
        'array(' + ', '.join('%s => %s' % (php_str(a), php_str(b)) for a, b in mapping.items()) + ');\n'


SCHEMA_VERSION = 3  # bump when field keys change; build/schema-prev.php = the previous release's schema


def keymap_php(schema):
    """Previous release's field keys -> current keys (fields that moved or were renumbered)."""
    import subprocess
    prev = os.path.join(ROOT, 'build', 'schema-prev.php')
    js = subprocess.run(['php', '-r', 'define("ABSPATH",1); echo json_encode(require $argv[1]);', prev], capture_output=True, text=True).stdout
    old = json.loads(js)['fields']

    def ident(f):
        return (f['type'], f['default'].replace('\n', ' '), f.get('label', ''))
    new_by = defaultdict(list)
    for gid, fields in schema.groups.items():
        for k, f in fields.items():
            new_by[ident(f)].append((gid, k))
    used, mapping = set(), {}
    for gid, fields in old.items():
        for k, f in fields.items():
            cands = [c for c in new_by.get(ident(f), []) if c[1] not in used]
            if not cands:
                continue
            pick = next((c for c in cands if c[1] == k), None) or next((c for c in cands if c[0] == gid), None)
            if pick is None and len(cands) == 1:
                pick = cands[0]  # moved to another section
            if pick is None:
                continue
            used.add(pick[1])
            if pick[1] != k:
                mapping[k] = pick[1]
    return ("<?php\n/* generated by build/convert.py: previous release's field keys -> current keys */\ndefined('ABSPATH') || exit;\n"
            "return array('version' => %d, 'map' => array(%s));\n" % (SCHEMA_VERSION, ', '.join('%s => %s' % (php_str(a), php_str(b)) for a, b in mapping.items())))


def php_str(s):
    return "'" + str(s).replace('\\', '\\\\').replace("'", "\\'") + "'"


def php_val(v):
    if isinstance(v, bool):
        return 'true' if v else 'false'
    if isinstance(v, (int, float)):
        return str(v)
    if isinstance(v, str):
        return php_str(v)
    if isinstance(v, dict):
        return 'array(' + ', '.join('%s => %s' % (php_str(k), php_val(x)) for k, x in v.items()) + ')'
    if isinstance(v, (list, tuple)):
        return 'array(' + ', '.join(php_val(x) for x in v) + ')'
    raise TypeError(type(v))


def schema_php(schema):
    out = ["<?php\n/* generated by build/convert.py; do not edit by hand */\ndefined('ABSPATH') || exit;\nreturn array(\n'fields' => array(\n"]
    for gid, fields in schema.groups.items():
        out.append('  %s => array(\n' % php_str(gid))
        for k, f in fields.items():
            g = OrderedDict(f)
            g['layouts'] = ''.join(f['layouts'])
            out.append('    %s => %s,\n' % (php_str(k), php_val(g)))
        out.append('  ),\n')
    out.append("),\n'cards' => array(\n")
    for gid, cards in schema.cards.items():
        out.append('  %s => %s,\n' % (php_str(gid), php_val(cards)))
    out.append('),\n);\n')
    return ''.join(out)


if __name__ == '__main__':
    main()
