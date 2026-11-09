#!/usr/bin/env python3
"""Extracts the default items of the repeatable lists (FAQ, organisations,
videos, magazine, provinces) from the design regions written by convert.py,
and writes plugin/neuron-landing/includes/defaults-lists.php."""
import html
import json
import os
import re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
REG = os.path.join(ROOT, 'build', 'regions')
OUT = os.path.join(ROOT, 'plugin', 'neuron-landing', 'includes', 'defaults-lists.php')
AMAP = json.load(open(os.path.join(ROOT, 'build', 'assets_map.json')))


def rd(name):
    return open(os.path.join(REG, name), encoding='utf-8').read()


def txt(s):
    return re.sub(r'\s+', ' ', html.unescape(re.sub(r'<[^>]+>', '', s))).strip()


def asset(src):
    m = re.match(r'/_blob/([0-9a-f]{32})', src)
    return AMAP[m.group(1)] if m else src


def faq():
    d = rd('d-faq.html')
    m = rd('m-faq.html')
    items = []
    for blk in re.findall(r'<details.*?</details>', d, re.S):
        q = txt(re.search(r'<summary[^>]*>(.*?)<span', blk, re.S).group(1))
        a = txt(re.search(r'<p(?:\s[^>]*)?>(.*?)</p>', blk, re.S).group(1))
        items.append({'q': q, 'a': a, 'open': 'open=""' in blk.split('>')[0]})
    mitems = [txt(re.search(r'<summary[^>]*>(.*?)<span', b, re.S).group(1)) for b in re.findall(r'<details.*?</details>', m, re.S)]
    assert mitems == [i['q'] for i in items], 'faq differs between layouts'
    return items


def orgs():
    d = rd('d-orgs.html')
    first = d.split('aria-hidden="true"')[0]
    items = []
    for blk in re.findall(r'<div class="org">(.*?)</div>', first, re.S):
        img = re.search(r'<img src="([^"]+)" alt="([^"]*)"', blk)
        name = txt(re.search(r'<span class="org-name">(.*?)</span>', blk, re.S).group(1))
        items.append({'name': name, 'logo': asset(img.group(1)) if img else '', 'alt': html.unescape(img.group(2)) if img else ''})
    m = rd('m-orgs.html')
    assert [i['name'] for i in items] == [txt(x) for x in re.findall(r'<span class="org-name">(.*?)</span>', m.split('aria-hidden="true"')[0])]
    return items


def videos():
    d = rd('d-videos.html')
    items = []
    for blk in re.findall(r'<article.*?</article>', d, re.S):
        items.append({
            'label': txt(re.search(r'<span class="phb">(.*?)</span>', blk).group(1)),
            'cover': asset(re.search(r'<img src="([^"]+)"', blk).group(1)),
            'alt': html.unescape(re.search(r'<img [^>]*alt="([^"]*)"', blk).group(1)),
            'title': txt(re.search(r'<h3[^>]*>(.*?)</h3>', blk, re.S).group(1)),
            'sub': txt(re.search(r'<p(?:\s[^>]*)?>(.*?)</p>', blk, re.S).group(1)),
            'aria': html.unescape(re.search(r'aria-label="([^"]*)"', blk).group(1)),
            'video': '',
        })
    return items


def mag():
    d = rd('d-mag.html')
    items = []
    for blk in re.findall(r'<a href.*?</a>', d, re.S):
        spans = re.findall(r'<span style="[^"]*">(.*?)</span>', blk, re.S)
        items.append({
            'label': txt(spans[0]),
            'img': asset(re.search(r'<img src="([^"]+)"', blk).group(1)),
            'alt': html.unescape(re.search(r'<img [^>]*alt="([^"]*)"', blk).group(1)),
            'phb': txt(re.search(r'<span class="phb">(.*?)</span>', blk).group(1)),
            'title': txt(re.search(r'<h3[^>]*>(.*?)</h3>', blk, re.S).group(1)),
            'url': re.search(r'<a href="([^"]*)"', blk).group(1),
        })
    more = txt(re.findall(r'<span style="[^"]*">(.*?)</span>', d, re.S)[1])
    return items, more


def badges():
    d = rd('d-badges.html')
    items = []
    for blk in re.findall(r'<div style="position: relative; width:.*?</div>', d, re.S):
        img = re.search(r'<img src="([^"]+)" alt="([^"]*)"', blk)
        items.append({'label': txt(re.search(r'<span[^>]*>(.*?)</span>', blk).group(1)), 'img': asset(img.group(1)),
                      'alt': html.unescape(img.group(2)), 'link': '', 'code': ''})
    return items


def provinces():
    d = rd('d-map_paths.html')
    m = rd('m-map_paths.html')
    out = []
    for p in re.findall(r'<path [^>]*>', d):
        a = dict(re.findall(r'([\w-]+)="([^"]*)"', p))
        out.append({'slug': a['data-province'], 'name': a['data-name'], 'n': int(a['data-n']), 'd': a['d'], 'fill': a['fill']})
    mp = [dict(re.findall(r'([\w-]+)="([^"]*)"', p)) for p in re.findall(r'<path [^>]*>', m)]
    assert [x['data-province'] for x in mp] == [x['slug'] for x in out]
    assert [x['d'] for x in mp] == [x['d'] for x in out], 'map geometry differs'
    # option order (alphabetical, as in the select)
    opts = re.findall(r'<option value="([^"]+)">([^<]+)</option>', rd('d-map_options.html'))
    return out, [o[0] for o in opts]


def php(v, ind=0):
    pad = '  ' * ind
    if isinstance(v, bool):
        return 'true' if v else 'false'
    if isinstance(v, int):
        return str(v)
    if isinstance(v, str):
        return "'" + v.replace('\\', '\\\\').replace("'", "\\'") + "'"
    if isinstance(v, list):
        return 'array(\n' + ''.join(pad + '  ' + php(x, ind + 1) + ',\n' for x in v) + pad + ')'
    if isinstance(v, dict):
        return 'array(\n' + ''.join(pad + '  ' + php(k) + ' => ' + php(x, ind + 1) + ',\n' for k, x in v.items()) + pad + ')'
    raise TypeError(type(v))


def main():
    prov, order = provinces()
    fills = sorted({(p['n'], p['fill']) for p in prov})
    mag_items, mag_more = mag()
    data = {
        'faq': faq(),
        'orgs': orgs(),
        'videos': videos(),
        'mag': {'source': 'latest', 'cat': 0, 'more': mag_more, 'items': mag_items},
        'badges': badges(),
        'provinces': [{'slug': p['slug'], 'name': p['name'], 'n': p['n'], 'url': ''} for p in prov],
    }
    geo = {'paths': {p['slug']: p['d'] for p in prov}, 'order': order, 'fills': [[n, f] for n, f in fills]}
    with open(OUT, 'w', encoding='utf-8') as f:
        f.write("<?php\n/* generated by build/extract_lists.py; do not edit by hand */\ndefined('ABSPATH') || exit;\nreturn " + php(data) + ";\n")
    with open(OUT.replace('defaults-lists.php', 'map-geometry.php'), 'w', encoding='utf-8') as f:
        f.write("<?php\n/* generated by build/extract_lists.py; province outlines of the design's map */\ndefined('ABSPATH') || exit;\nreturn " + php(geo) + ";\n")
    print('badges', len(data['badges']), 'faq', len(data['faq']), 'orgs', len(data['orgs']), 'videos', len(data['videos']), 'mag', len(mag_items), 'provinces', len(prov))
    print('fill scale', fills)


if __name__ == '__main__':
    main()
