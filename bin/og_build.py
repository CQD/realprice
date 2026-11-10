#!/usr/bin/env python3
"""
各區的 og:image (1200×630)：區域名、10 年漲幅、近一年摘要、品質調整指數走勢、地圖

輸入: build/site.json、public/data/{build_id}/regions.json (stats_build.py 產生)、public/geo/towns.json
輸出: public/data/{build_id}/og/{檔名}.png，檔名規則見 og_name() (跟 Site::ogImage() 一致)
      --static 另外把全台那張複製成 public/og.png (README、預設 og:image 用，進 git)

字型用系統的 Noto Sans CJK TC (fc-match 找)，可用環境變數 OG_FONT_BOLD、OG_FONT_BLACK 指定 .ttc/.otf 路徑

用法: venv/bin/python bin/og_build.py [--static] [--only 區域ID,...]
"""
import hashlib
import json
import math
import os
import shutil
import subprocess
import sys
from multiprocessing import Pool

from PIL import Image, ImageDraw, ImageFont

ROOT = os.path.dirname(os.path.abspath(__file__)) + '/..'
W, H = 1200, 630
SS = 2   # 超取樣倍率：放大畫再縮小，線條、地圖邊界才有反鋸齒

# 跟 public/s/app.css 的色票一致
BRAND = '#f25500'
INK, INK2, INK3 = '#102022', '#4a5a5d', '#788689'
LINE, BG, SURFACE, MISSING = '#dde3e4', '#f2f4f4', '#fcfcfb', '#e6eaea'
UP, DOWN = '#d2472a', '#2b6cb0'   # 漲 / 跌
COUNTY_LINE, NATION_LINE = '#2a78d6', '#a7b1b3'
WARM = ['#e1ebf2', '#eef0e4', '#fbe1ab', '#f7b264', '#ec7b3a', '#d2472a', '#9c1f1c']   # app.js 的 WARM

HEADER = 64
LEFT, COL_R = 64, 744          # 左欄 (文字、走勢圖)
MAP_BOX = (780, HEADER + 22, W - 36, H - 22)   # 右欄地圖


def og_name(rid):
    """區域 ID 有中文，檔名改用 md5，避免非 ASCII 的靜態檔路徑"""
    return hashlib.md5(rid.encode()).hexdigest()[:12] + '.png'


# ---------------- 字型 ----------------
def find_font(env, style):
    path = os.environ.get(env)
    if path:
        return path, 0
    try:
        out = subprocess.run(['fc-match', '-f', '%{file}\t%{index}', f'Noto Sans CJK TC:style={style}'],
                             capture_output=True, text=True, check=True).stdout
    except (OSError, subprocess.CalledProcessError):
        out = ''
    path, _, idx = out.partition('\t')
    if 'CJK' not in os.path.basename(path):
        sys.exit(f'找不到 Noto Sans CJK TC {style}，請安裝 fonts-noto-cjk 或用 {env} 指定字型檔')
    return path, int(idx or 0)


FONT_FILES = {'bold': find_font('OG_FONT_BOLD', 'Bold'), 'black': find_font('OG_FONT_BLACK', 'Black')}
_fonts = {}


def font(size, weight='bold'):
    key = (size, weight)
    if key not in _fonts:
        path, idx = FONT_FILES[weight]
        _fonts[key] = ImageFont.truetype(path, size * SS, index=idx)
    return _fonts[key]


# ---------------- 繪圖小工具 (座標一律用 1x) ----------------
def s(v):
    return round(v * SS)


def pts(points):
    return [(x * SS, y * SS) for x, y in points]


def text(d, xy, txt, size, fill, weight='bold', anchor='ls'):
    d.text((s(xy[0]), s(xy[1])), txt, font=font(size, weight), fill=fill, anchor=anchor)
    return text_w(txt, size, weight)


def text_w(txt, size, weight='bold'):
    return font(size, weight).getlength(txt) / SS


def fit_size(txt, size, max_w, weight='black', min_size=40):
    while size > min_size and text_w(txt, size, weight) > max_w:
        size -= 2
    return size


def hex2rgb(h):
    return tuple(int(h[i:i + 2], 16) for i in (1, 3, 5))


def ramp(v, lo, hi, colors=WARM):
    t = min(1, max(0, (v - lo) / ((hi - lo) or 1))) * (len(colors) - 1)
    i = min(len(colors) - 2, int(t))
    f = t - i
    a, b = hex2rgb(colors[i]), hex2rgb(colors[i + 1])
    return tuple(round(a[k] + (b[k] - a[k]) * f) for k in range(3))


def quantile(vals, q):
    v = sorted(vals)
    pos = (len(v) - 1) * q
    i = int(pos)
    return v[i] if i + 1 >= len(v) else v[i] + (v[i + 1] - v[i]) * (pos - i)


def pct(v, sign=True, digits=0):
    if v is None:
        return '—'
    return ('+' if sign and v > 0 else '') + f'{v * 100:.{digits}f}%'


# ---------------- 地圖 ----------------
def load_geo():
    topo = json.load(open(f'{ROOT}/public/geo/towns.json'))
    (sx, sy), (tx, ty) = topo['transform']['scale'], topo['transform']['translate']
    arcs = []
    for arc in topo['arcs']:
        x = y = 0
        line = []
        for dx, dy in arc:
            x += dx
            y += dy
            line.append((x * sx + tx, y * sy + ty))
        arcs.append(line)

    def ring(ids):
        out = []
        for i in ids:
            a = arcs[i] if i >= 0 else arcs[~i][::-1]
            out.extend(a[1:] if out else a)
        return out

    towns = []
    for g in topo['objects']['towns']['geometries']:
        polys = [g['arcs']] if g['type'] == 'Polygon' else g['arcs']
        p = g['properties']
        c = p['COUNTYID'].lower()
        # 跟 app.js 的 CODE2RID 相同：嘉義市、新竹市的資料只有一個同名區
        rid = f"{c}-{p['COUNTYNAME'].replace('台', '臺', 1)}" if c in ('i', 'o') else f"{c}-{p['TOWNNAME']}"
        towns.append({'rid': rid, 'county': c, 'polys': [[ring(r) for r in poly] for poly in polys]})
    return towns


def draw_map(img, towns, box, fill_of, scope, bounds=None, highlight=None, margin=0.0):
    """scope 內的區域用 fill_of(town) → RGB 上色，範圍外的鄰近區域畫淡灰當定位底圖 (往外漸淡)；
    highlight 的區域加深色外框；margin 是 scope 外圍留給鄰近區域的比例"""
    x0, y0, x1, y1 = box
    inside = [t for t in towns if scope(t)]
    if bounds is None:
        # 範圍不算太小的離島 (如金門縣的烏坵)，不然主要區域會被縮得很小
        rings = [poly[0] for t in inside for poly in t['polys']]
        areas = [abs(sum(x0 * y1 - x1 * y0 for (x0, y0), (x1, y1) in zip(r, r[1:] + r[:1]))) for r in rings]
        rings = [r for r, a in zip(rings, areas) if a >= max(areas) * 0.03]
        xs = [x for r in rings for x, _ in r]
        ys = [y for r in rings for _, y in r]
        bounds = (min(xs), min(ys), max(xs), max(ys))
    lon0, lat0, lon1, lat1 = bounds
    kx = math.cos(math.radians((lat0 + lat1) / 2))
    bw, bh = (x1 - x0) * (1 - 2 * margin), (y1 - y0) * (1 - 2 * margin)
    scale = min(bw / ((lon1 - lon0) * kx), bh / (lat1 - lat0))
    ox = ((x1 - x0) - (lon1 - lon0) * kx * scale) / 2
    oy = ((y1 - y0) - (lat1 - lat0) * scale) / 2
    lw, lh = s(x1 - x0), s(y1 - y0)

    def proj(ring):
        return [(s(ox + (lon - lon0) * kx * scale), s(oy + (lat1 - lat) * scale)) for lon, lat in ring]

    def fill_poly(layer, rings, color):
        if len(rings) == 1:
            ImageDraw.Draw(layer).polygon(rings[0], fill=color)
            return
        # 有洞的多邊形 (如嘉義縣包著嘉義市) 用 mask 挖洞，避免蓋掉先畫的區域
        xs = [x for x, _ in rings[0]]
        ys = [y for _, y in rings[0]]
        bx, by = int(min(xs)), int(min(ys))
        mask = Image.new('L', (int(max(xs)) - bx + 2, int(max(ys)) - by + 2), 0)
        md = ImageDraw.Draw(mask)
        md.polygon([(x - bx, y - by) for x, y in rings[0]], fill=255)
        for hole in rings[1:]:
            md.polygon([(x - bx, y - by) for x, y in hole], fill=0)
        layer.paste(color, (bx, by), mask)

    def paint(layer, group, fill_of):
        d = ImageDraw.Draw(layer)
        shapes = []
        for t in group:
            for poly in t['polys']:
                rings = [proj(r) for r in poly]
                xs = [x for x, _ in rings[0]]
                ys = [y for _, y in rings[0]]
                if max(xs) < 0 or min(xs) > lw or max(ys) < 0 or min(ys) > lh:
                    continue
                shapes.append((t, rings))
                fill_poly(layer, rings, fill_of(t))
        for t, rings in shapes:
            for r in rings:
                d.line(r + r[:1], fill=(255, 255, 255, 255), width=s(0.8), joint='curve')
        # 強調的區域：先畫粗外框再重新填色，只留外側半邊，內部的區界 (如嘉義市東區／西區) 也會被蓋掉
        hl = [(t, rings) for t, rings in shapes if t['rid'] == highlight]
        for t, rings in hl:
            for r in rings:
                d.line(r + r[:1], fill=hex2rgb(INK) + (255,), width=s(5), joint='curve')
        for t, rings in hl:
            fill_poly(layer, rings, fill_of(t))

    # 鄰近區域：淡灰底，離地圖中心越遠越透明
    around = Image.new('RGBA', (lw, lh), (0, 0, 0, 0))
    paint(around, [t for t in towns if not scope(t)], lambda t: hex2rgb('#e9edee') + (255,))
    fade = Image.radial_gradient('L').resize((lw, lh)).point(lambda v: max(0, min(255, (225 - v) * 255 // 90)))
    around.putalpha(Image.composite(around.getchannel('A'), Image.new('L', (lw, lh), 0), fade))
    img.paste(around, (s(x0), s(y0)), around)

    main = Image.new('RGBA', (lw, lh), (0, 0, 0, 0))
    paint(main, inside, lambda t: fill_of(t) + (255,))
    img.paste(main, (s(x0), s(y0)), main)


def draw_legend(d, x, y, lo, hi, label):
    text(d, (x, y), label, 18, INK2)
    y += 12
    w = 150
    for i in range(w):
        c = ramp(lo + (hi - lo) * i / (w - 1), lo, hi)
        d.rectangle([s(x + i), s(y), s(x + i + 1), s(y + 10)], fill=c)
    text(d, (x, y + 32), pct(lo), 16, INK3, anchor='ls')
    text(d, (x + w, y + 32), pct(hi), 16, INK3, anchor='rs')


# ---------------- 走勢圖 ----------------
def draw_trend(img, box, lines, labels, end):
    """lines: [(名稱, spark 陣列, 顏色, 線寬, 主線?)]，各線在共同起點對齊成 0，Y 軸是 log 指數"""
    x0, y0, x1, y1 = box
    main = lines[0][1]
    start = next((i for i, v in enumerate(main[:end + 1]) if v is not None), None)
    if start is None:
        return
    series = []
    for name, sp, color, width, is_main in lines:
        base = sp[start] if sp[start] is not None else next((v for v in sp[start:end + 1] if v is not None), None)
        if base is None:
            continue
        series.append((name, [None if v is None else v - base for v in sp[start:end + 1]], color, width, is_main))
    vals = [v for _, sp, *_ in series for v in sp if v is not None]
    lo, hi = min(vals + [0]), max(vals + [0])
    pad = (hi - lo) * 0.08 or 0.05
    lo, hi = lo - pad, hi + pad
    n = end - start
    label_w = 96   # 右側留給線尾標籤
    px = lambda i: x0 + (x1 - x0 - label_w) * i / max(n, 1)
    py = lambda v: y1 - (y1 - y0) * (v - lo) / (hi - lo)

    d = ImageDraw.Draw(img)
    # 年份格線
    for i in range(n + 1):
        y_, m_ = labels[start + i].split('-')
        if m_ == '01' and int(y_) % 2 == 0:
            d.line(pts([(px(i), y0), (px(i), y1)]), fill='#eaeeee', width=s(1))
            text(d, (px(i), y1 + 22), y_, 16, INK3, weight='bold', anchor='ms')
    d.line(pts([(x0, py(0)), (x1 - label_w, py(0))]), fill=LINE, width=s(1.5))

    # 主線下方的漸層面積
    name, sp, color, width, _ = series[0]
    segs, cur = [], []
    for i, v in enumerate(sp):
        if v is None:
            if cur:
                segs.append(cur)
            cur = []
        else:
            cur.append((px(i), py(v)))
    if cur:
        segs.append(cur)
    rgb = hex2rgb(color)
    grad = Image.new('RGBA', (s(x1 - x0), s(y1 - y0)))
    gd = ImageDraw.Draw(grad)
    for yy in range(grad.height):
        a = int(70 * (1 - yy / grad.height) ** 1.5)
        gd.line([(0, yy), (grad.width, yy)], fill=rgb + (a,))
    mask = Image.new('L', grad.size, 0)
    md = ImageDraw.Draw(mask)
    for seg in segs:
        if len(seg) > 1:
            poly = seg + [(seg[-1][0], y1), (seg[0][0], y1)]
            md.polygon([(s(x - x0), s(y - y0)) for x, y in poly], fill=255)
    img.paste(grad, (s(x0), s(y0)), Image.composite(grad, Image.new('RGBA', grad.size), mask))

    # 線：次要的先畫，主線最後
    ends = []
    for name, sp, color, width, is_main in reversed(series):
        seg = []
        for i, v in enumerate(sp + [None]):
            if v is None:
                if len(seg) > 1:
                    d.line(pts(seg), fill=color, width=s(width), joint='curve')
                seg = []
            else:
                seg.append((px(i), py(v)))
        last = next((i for i in range(len(sp) - 1, -1, -1) if sp[i] is not None), None)
        if last is not None:
            ends.append([py(sp[last]), name, color, is_main, px(last)])
    # 主線終點的圓點
    for y, name, color, is_main, x in ends:
        if is_main:
            r = 7
            d.ellipse([s(x - r), s(y - r), s(x + r), s(y + r)], fill=color, outline=SURFACE, width=s(3))
    # 線尾標籤避免重疊
    ends.sort(key=lambda e: e[0])
    for i in range(1, len(ends)):
        ends[i][0] = max(ends[i][0], ends[i - 1][0] + 22)
    for y, name, color, is_main, x in ends:
        text(d, (x1 - label_w + 14, y + 7), name, 19 if is_main else 17, color if is_main else INK3, anchor='ls')


# ---------------- 一張圖 ----------------
def render(ctx, rid):
    site, regions, towns = ctx['site'], ctx['regions'], ctx['towns']
    r = regions[rid]
    level = r['level']
    county = regions[r['parent']] if level == 2 else (r if level == 1 else None)
    single = level == 2 and county['name'] == r['name']   # 嘉義市、新竹市
    if level == 1 and len(site['districts'].get(rid, [])) == 1:
        only = regions[site['districts'][rid][0]]
        single = only['name'] == r['name']

    img = Image.new('RGB', (W * SS, H * SS), SURFACE)
    d = ImageDraw.Draw(img)

    # 頂部列：跟網站一樣的 brand color 底、白字
    d.rectangle([0, 0, s(W), s(HEADER)], fill=BRAND)
    text(d, (LEFT, 43), '實價登錄房價趨勢', 28, '#ffffff')
    text(d, (W - 36, 42), 'realprice.cqd.tw', 22, '#ffffff', anchor='rs')

    # 標題
    if level == 0:
        kicker, name = f"{site['month_first'][:4]}–{site['month_end'][:4]} 實價登錄・全台 {len(ctx['district_ids'])} 個鄉鎮市區", '全台房價趨勢'
    elif level == 1 or single:
        kicker, name = '全台 ›', county['name']
    else:
        kicker, name = f"{county['name']} ›", r['name']
    text(d, (LEFT, 112), kicker, 26, INK2)
    size = fit_size(name, 84, COL_R - LEFT)
    text(d, (LEFT - 2, 198), name, size, INK, weight='black')

    # 主數字：10 年漲幅 → 5 年漲幅 → 單價，有哪個用哪個
    kpis = [
        ('chg10', pct(r['chg10']), '10 年漲幅', '品質調整・全部住宅'),
        ('chg5', pct(r['chg5']), '5 年漲幅', '品質調整・全部住宅'),
        ('up', None if r['up'] is None else f"{r['up'] / 10000:.1f}", '萬/坪', '近一年單價中位數'),
    ]
    hero = next((k for k in kpis if r[k[0]] is not None), None)
    y_hero = 316
    if hero:
        key, val, label, note = hero
        color = INK if key == 'up' else (UP if r[key] > 0 else DOWN)
        hw = text(d, (LEFT - 6, y_hero), val, 120, color, weight='black')
        text(d, (LEFT + hw + 18, y_hero - 52), label, 30, INK)
        text(d, (LEFT + hw + 18, y_hero - 16), note, 19, INK3)
    else:
        text(d, (LEFT, y_hero - 30), '近年成交量少，樣本不足', 40, INK3)

    # 次要指標：主數字沒用到的
    sub = []
    if r['up'] is not None and (hero is None or hero[0] != 'up'):
        sub.append(('近一年單價', f"{r['up'] / 10000:.1f}", '萬/坪'))
    if hero and hero[0] == 'chg10' and r['chg5'] is not None:
        sub.append(('5 年漲幅', pct(r['chg5']), ''))
    sub.append(('近一年成交', f"{r['n12']:,}", '筆'))
    if r.get('yield') is not None and len(sub) < 3:
        sub.append(('毛租金收益率', pct(r['yield'], sign=False, digits=2), ''))
    x = LEFT
    for label, val, unit in sub[:3]:
        text(d, (x, 358), label, 19, INK3)
        w = text(d, (x, 396), val, 32, INK)
        if unit:
            w += 6 + text(d, (x + w + 6, 396), unit, 19, INK2)
        x += max(w, text_w(label, 19)) + 44

    # 走勢：本區 (主線) + 所屬縣市 + 全台
    tw_spark = regions['TW']['spark']
    lines = []
    if r.get('spark'):
        lines.append((('全台' if level == 0 else name), r['spark'], BRAND, 5, True))
    if level == 2 and not single and county.get('spark'):
        lines.append((county['name'], county['spark'], COUNTY_LINE, 3, not lines))
    if level > 0 and tw_spark:
        lines.append(('全台', tw_spark, NATION_LINE, 3, not lines))
    if lines:
        text(d, (LEFT, 444), '品質調整房價指數' + ('' if r.get('spark') else '（本區樣本不足）'), 18, INK2)
        draw_trend(img, (LEFT, 458, COL_R, 576), lines, ctx['labels'], ctx['end'])

    # 地圖
    chg10 = lambda t: (regions.get(t['rid']) or {}).get('chg10')
    if level == 0:
        scope = lambda t: t['county'] not in ('w', 'z')
        vals = [v for t in towns if scope(t) and (v := chg10(t)) is not None]
        lo, hi = quantile(vals, .03), quantile(vals, .97)
        fill = lambda t: ramp(v, lo, hi) if (v := chg10(t)) is not None else hex2rgb(MISSING)
        draw_map(img, towns, MAP_BOX, fill, scope, bounds=(119.3, 21.85, 122.05, 25.35))
        draw_legend(d, MAP_BOX[0] + 4, MAP_BOX[3] - 40, lo, hi, '各區 10 年漲幅')
    else:
        cid = county['id']
        scope = lambda t: t['county'] == cid
        vals = [v for i in site['districts'][cid] if (v := regions[i]['chg10']) is not None]
        if level == 1 and not single and len(vals) >= 3:
            lo, hi = quantile(vals, .03), quantile(vals, .97)
            lo, hi = (lo - 1e-6, hi + 1e-6) if lo == hi else (lo, hi)
            fill = lambda t: ramp(v, lo, hi) if (v := chg10(t)) is not None else hex2rgb(MISSING)
            draw_map(img, towns, (MAP_BOX[0], MAP_BOX[1], MAP_BOX[2], MAP_BOX[3] - 56), fill, scope, margin=0.04)
            draw_legend(d, MAP_BOX[0] + 4, MAP_BOX[3] - 40, lo, hi, '各區 10 年漲幅')
        else:
            # 鄉鎮市區頁、只有一個同名區的縣市：強調該區；有漲幅的區太少的縣市 (如連江縣)：整個縣市上色
            target = rid if level == 2 else (site['districts'][cid][0] if single else None)
            fill = lambda t: hex2rgb(BRAND) if target in (None, t['rid']) else hex2rgb('#d3dadc')
            draw_map(img, towns, MAP_BOX, fill, scope, highlight=target, margin=0.25 if single else 0.06)

    out = img.resize((W, H), Image.LANCZOS)
    # 調色盤 PNG：檔案小很多，反鋸齒邊緣的顏色數也夠用
    out = out.quantize(256, method=Image.Quantize.MEDIANCUT, dither=Image.Dither.NONE)
    out.save(f"{ctx['out']}/{og_name(rid)}", optimize=True)
    return rid


CTX = None


def init(ctx):
    global CTX
    CTX = ctx


def work(rid):
    return render(CTX, rid)


def main():
    args = sys.argv[1:]
    site = json.load(open(f'{ROOT}/build/site.json'))
    pub = json.load(open(f"{ROOT}/public/data/{site['build_id']}/regions.json"))
    regions = {}
    for rid, r in site['regions'].items():
        regions[rid] = {**r, 'spark': None}
    for r in pub['regions']:
        regions[r['id']]['spark'] = r['spark']
    out = f"{ROOT}/public/data/{site['build_id']}/og"
    os.makedirs(out, exist_ok=True)
    ctx = {
        'site': site, 'regions': regions, 'towns': load_geo(), 'out': out,
        'labels': pub['m'], 'end': pub['end'],
        'district_ids': [i for ids in site['districts'].values() for i in ids],
    }
    ids = list(regions)
    if '--only' in args:
        ids = args[args.index('--only') + 1].split(',')
    with Pool(initializer=init, initargs=(ctx,)) as pool:
        done = pool.map(work, ids, chunksize=8)
    print(f'og: {len(done)} 張 → {out}')
    if '--static' in args:
        shutil.copy(f'{out}/{og_name("TW")}', f'{ROOT}/public/og.png')
        print('og: public/og.png')


if __name__ == '__main__':
    main()
