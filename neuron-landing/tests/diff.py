# usage: python3 diff.py <prefix>   -> compares <prefix>-ref.png and <prefix>-wp.png
import sys
import numpy as np
from PIL import Image, ImageChops
pre = sys.argv[1]
a = Image.open(pre + '-ref.png').convert('RGB'); b = Image.open(pre + '-wp.png').convert('RGB')
print('sizes', a.size, b.size)
h = min(a.height, b.height); w = min(a.width, b.width)
arr = np.array(ImageChops.difference(a.crop((0, 0, w, h)), b.crop((0, 0, w, h))).convert('L'))
print('differing pixels (>40):', int((arr > 40).sum()), 'of', arr.size)
rows = np.where(arr.max(axis=1) > 40)[0]
if len(rows):
    bands = []; s = rows[0]; p = rows[0]
    for r in rows[1:]:
        if r - p > 20: bands.append((s, p)); s = r
        p = r
    bands.append((s, p))
    for s, e in bands[:30]:
        cols = np.where(arr[s:e + 1].max(axis=0) > 40)[0]
        print('  band y=%d-%d x=%d-%d n=%d' % (s, e, cols.min(), cols.max(), (arr[s:e + 1] > 40).sum()))
