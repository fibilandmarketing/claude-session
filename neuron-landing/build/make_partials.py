#!/usr/bin/env python3
"""Builds includes/partials.php: the repeatable lists rendered with the exact
item markup of the design (taken from build/regions/*, written by convert.py)."""
import os
import re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
REG = os.path.join(ROOT, 'build', 'regions')
OUT = os.path.join(ROOT, 'plugin', 'neuron-landing', 'includes', 'partials.php')


def rd(n):
    return open(os.path.join(REG, n), encoding='utf-8').read()


def php_s(s):
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"


def tpl(markup, subs):
    """Turn a markup sample into a PHP strtr() template: replace each (old -> token)."""
    for old, token in subs:
        if old not in markup:
            raise ValueError('not found: %r in %r' % (old, markup[:200]))
        markup = markup.replace(old, token, 1)
    return markup


def faq(layout):
    s = rd('%s-faq.html' % layout)
    items = re.findall(r'<details.*?</details>', s, re.S)
    it = items[1]
    q = re.search(r'<summary[^>]*>(.*?)<span', it, re.S).group(1)
    a = re.search(r'<p(?:\s[^>]*)?>(.*?)</p>', it, re.S).group(1)
    t = tpl(it, [('<details ', '<details{OPEN} '), ('>' + q + '<span', '>{Q}<span'), ('>' + a + '</p>', '>{A}</p>')])
    sep = s[s.find('</details>') + 10:s.find('<details', 5)]
    return t, sep


def orgs(layout):
    s = rd('%s-orgs.html' % layout)
    first = re.findall(r'<div class="org">.*?</div>', s)[0]
    img = re.search(r'<img [^>]*>', first).group(0)
    name = re.search(r'<span class="org-name">(.*?)</span>', first).group(1)
    t_logo = tpl(first, [('<div class="org">', '<div class="org"{HID}>'), (img, '<img src="{SRC}" alt="{ALT}">'), ('>' + name + '<', '>{NAME}<')])
    badge = re.findall(r'<div class="org"><span class="org-logo"><span class="org-badge">.*?</div>', s)[0]
    bname = re.search(r'<span class="org-name">(.*?)</span>', badge).group(1)
    t_badge = tpl(badge, [('<div class="org">', '<div class="org"{HID}>'), ('>' + bname + '<', '>{NAME}<')])
    # wrapper whitespace: "\n" + set A + "\n" + set B + "\n"
    return t_logo, t_badge


def videos(layout):
    s = rd('%s-videos.html' % layout)
    items = re.findall(r'<article.*?</article>', s, re.S)
    it = items[1]  # card 2 (mobile: without the extra ping ring)
    label = re.search(r'<span class="phb">(.*?)</span>', it).group(0)
    img = re.search(r'<img [^>]*>', it).group(0)
    title = re.search(r'(<h3[^>]*>)(.*?)</h3>', it, re.S)
    aria = re.search(r'aria-label="([^"]*)"', it).group(0)
    subs = [('<button type="button" ' + aria, '<button type="button" aria-label="{ARIA}"{VID}'),
            (label, '{LABEL}'), (img, '<img src="{SRC}" alt="{ALT}" style="' + re.search(r'style="([^"]*)"', img).group(1) + '">'),
            (title.group(0) + '', title.group(1) + '{TITLE}</h3>')]
    sub = re.search(r'(<p(?:\s[^>]*)?>)(.*?)</p>', it, re.S)
    if sub:
        subs.append((sub.group(0), '{SUB}'))
    t = tpl(it, subs)
    phb_open = re.search(r'<span class="phb">', it).group(0)
    sub_open = sub.group(1) if sub else ''
    # ping ring: card 1 in mobile has it, cards 2-3 do not; desktop: all
    ring = re.search(r'<span class="pin"[^>]*></span>', items[0]).group(0)
    ring_in_t = ring in it
    if ring_in_t:
        t = t.replace(ring, '{RING}', 1)
    else:
        glass = re.search(r'<span class="glass"[^>]*>', t).group(0)
        t = t.replace(glass, glass + '{RING}', 1)
    sep = s[s.find('</article>') + 10:s.find('<article', 5)]
    return t, sep, phb_open, sub_open, ring, ring_in_t


def mag(layout):
    s = rd('%s-mag.html' % layout)
    items = re.findall(r'<a href.*?</a>', s, re.S)
    it = items[0]
    img = re.search(r'<img [^>]*>', it).group(0)
    spans = re.findall(r'(<span style="[^"]*">)(.*?)</span>', it, re.S)
    h3 = re.search(r'(<h3[^>]*>)(.*?)</h3>', it, re.S)
    subs = [('<a href="#"', '<a href="{URL}"'),
            (img, '<img src="{SRC}" alt="{ALT}" style="' + re.search(r'style="([^"]*)"', img).group(1) + '">'),
            (spans[0][0] + spans[0][1] + '</span>', '{LABEL}'),
            (h3.group(0), h3.group(1) + '{TITLE}</h3>')]
    phb = re.search(r'<span class="phb">.*?</span>', it)
    if phb:
        subs.append((phb.group(0), '{PHB}'))
    if len(spans) > 1:
        subs.append((spans[1][0] + spans[1][1] + '</span>', '{MORE}'))
    t = tpl(it, subs)
    sep = s[s.find('</a>') + 4:s.find('<a href', 5)]
    return t, sep, spans[0][0], (spans[1][0] if len(spans) > 1 else '')


def map_paths(layout):
    s = rd('%s-map_paths.html' % layout)
    p = re.findall(r'<path .*?</path>', s, re.S)[0]
    a = dict(re.findall(r'([\w-]+)="([^"]*)"', p))
    t = tpl(p, [('class="%s"' % a['class'], 'class="{CLS}"'), ('data-province="%s"' % a['data-province'], 'data-province="{SLUG}"'),
                ('data-name="%s"' % a['data-name'], 'data-name="{NAME}"{URL}'), ('data-n="%s"' % a['data-n'], 'data-n="{N}"'),
                ('fill="%s"' % a['fill'], 'fill="{FILL}"'), ('d="%s"' % a['d'], 'd="{D}"'), ('aria-label="%s"' % a['aria-label'], 'aria-label="{ARIA}"')])
    return t


def badges(layout):
    s = rd('%s-badges.html' % layout)
    it = re.findall(r'<div style="position: relative; width:.*?</div>', s, re.S)[0]
    box = re.match(r'<div style="([^"]*)">', it).group(1)
    img = re.search(r'<img [^>]*>', it).group(0)
    label = re.search(r'(<span[^>]*>)(.*?)</span>', it)
    t = tpl(it, [(img, '<img src="{SRC}" alt="{ALT}" style="' + re.search(r'style="([^"]*)"', img).group(1) + '" loading="lazy" decoding="async">'),
                 (label.group(0), '{LABEL}')])
    sep = s[s.find('</div>') + 6:s.find('<div', 5)]
    return t, sep, label.group(1), box


def products(layout):
    s = rd('%s-products.html' % layout)
    it = re.findall(r'<a href.*?</a>', s, re.S)[0]
    img = re.search(r'<img [^>]*>', it).group(0)
    t = tpl(it, [('<a href="#"', '<a href="{URL}"'), ('aria-label="%s"' % re.search(r'aria-label="([^"]*)"', it).group(1), 'aria-label="{ARIA}"'),
                 (img, '{PHB}{IMG}'),
                 (re.search(r'<span class="pc-tag">.*?</span>', it).group(0), '{TAG}'),
                 (re.search(r'(<span class="pc-name"[^>]*>).*?</span>', it).group(0), re.search(r'(<span class="pc-name"[^>]*>)', it).group(1) + '{NAME}</span>'),
                 (re.search(r'<span class="pc-price">.*?</small></span>', it).group(0), '<span class="pc-price">{PRICE}<small>{UNIT}</small></span>')])
    phb = re.search(r'<span class="phb"[^>]*>', s).group(0)
    sep = s[s.find('</a>') + 4:s.find('<a href', 5)]
    return t, sep, phb


def main():
    out = ["<?php\n/* generated by build/make_partials.py from the design regions; do not edit by hand */\ndefined('ABSPATH') || exit;\n\n"]
    T = {}
    for L in ('d', 'm'):
        T['faq_' + L], T['faq_sep_' + L] = faq(L)
        T['org_' + L], T['badge_' + L] = orgs(L)
        v = videos(L)
        T['vid_' + L], T['vid_sep_' + L], T['vid_phb_' + L], T['vid_sub_' + L], T['vid_ring_' + L], _ = v
        T['vid_ring_all_' + L] = L == 'd'
        g = mag(L)
        T['mag_' + L], T['mag_sep_' + L], T['mag_label_' + L], T['mag_more_' + L] = g
        T['path_' + L] = map_paths(L)
        T['prod_' + L], T['prod_sep_' + L], T['prod_phb_' + L] = products(L)
        T['seal_' + L], T['seal_sep_' + L], T['seal_label_' + L], T['seal_box_' + L] = badges(L)
    for k in list(T):
        if k.startswith(('org_', 'vid_', 'mag_')) and isinstance(T[k], str):
            T[k] = T[k].replace('<img src=', '<img loading="lazy" decoding="async" src=')
    out.append('function nrn_tpl() {\n\tstatic $t = null;\n\tif ( null === $t ) {\n\t\t$t = array(\n')
    for k, v in T.items():
        if isinstance(v, bool):
            out.append('\t\t\t%s => %s,\n' % (php_s(k), 'true' if v else 'false'))
        else:
            out.append('\t\t\t%s => %s,\n' % (php_s(k), php_s(v)))
    out.append('\t\t);\n\t}\n\treturn $t;\n}\n\n')
    out.append(open(os.path.join(ROOT, 'build', 'partials-logic.php'), encoding='utf-8').read().split('?>', 0)[0].replace('<?php', '', 1))
    with open(OUT, 'w', encoding='utf-8') as f:
        f.write(''.join(out))
    print('partials written')


if __name__ == '__main__':
    main()
