#!/usr/bin/env python3
"""
data/raw.duckdb (由 stats_load.py 產生) → 清洗 + 彙總

輸出:
  public/data/{build_id}/regions.json        各區摘要 + 指數走勢 (前端用)
  public/data/{build_id}/c/{縣市}/{類型}.json  各縣市 (含轄下鄉鎮市區) 的月資料
  build/site.json                            PHP 用的區域清單與摘要 (再由 json2php 轉成 build/site.php)

用法: .venv/bin/python bin/stats_build.py
"""
import datetime
import glob
import json
import math
import os
import shutil

import duckdb
import numpy as np
import pandas as pd

ROOT = os.path.dirname(os.path.abspath(__file__)) + '/..'
BUILD_ID = datetime.datetime.now().strftime('%Y%m%d%H%M')
OUT = f'{ROOT}/public/data/{BUILD_ID}'
KEEP_BUILDS = 2   # 部署時保留前一版，避免邊緣快取的舊 HTML 抓不到資料
THIS_YEAR = datetime.date.today().year

# 縣市代碼是原始檔名的前綴 (如 a_lvr_land_a.xml)，順序是選單用的地理順序 (北 → 南 → 東 → 離島)
COUNTIES = {
    'a': '臺北市', 'f': '新北市', 'c': '基隆市', 'h': '桃園市', 'o': '新竹市', 'j': '新竹縣',
    'k': '苗栗縣', 'b': '臺中市', 'n': '彰化縣', 'm': '南投縣', 'p': '雲林縣', 'i': '嘉義市',
    'q': '嘉義縣', 'd': '臺南市', 'e': '高雄市', 't': '屏東縣', 'g': '宜蘭縣', 'u': '花蓮縣',
    'v': '臺東縣', 'x': '澎湖縣', 'w': '金門縣', 'z': '連江縣',
}
# 類型 slug 當檔名，避免中文出現在靜態檔路徑
TYPES = {'tower': '住宅大樓', 'mansion': '華廈', 'apartment': '公寓', 'house': '透天厝', 'suite': '套房'}

# ROC 日期字串 → (西元年, 月)；支援 yyyMMdd / yyMMdd / yyyMM
DATE_Y = "(CASE WHEN try_cast({c} AS INT) >= 10000 THEN try_cast({c} AS INT) // 10000 ELSE try_cast({c} AS INT) // 100 END + 1911)"
DATE_M = "(CASE WHEN try_cast({c} AS INT) >= 10000 THEN (try_cast({c} AS INT) // 100) % 100 ELSE try_cast({c} AS INT) % 100 END)"

# 原始資料的區名異常：亂碼、以及「台」「臺」混用
DIST = "replace(replace(replace(replace(鄉鎮市區, '金&#27;fa4b鄉', '金峰鄉'), 'fa72埔鄉', '鹽埔鄉'), '台西鄉', '臺西鄉'), '台東市', '臺東市')"


def ym(col):
    return DATE_Y.format(c=col), DATE_M.format(c=col)


def say(msg):
    print(f'\033[32m[{datetime.datetime.now():%H:%M:%S}]\033[m {msg}', flush=True)


con = duckdb.connect(f'{ROOT}/data/raw.duckdb', read_only=True)
con.execute("SET memory_limit='8GB'")

ty, tm = ym('交易年月日')
by, bm = ym('建築完成年月')
TYPE_EXPR = "CASE " + " ".join(f"WHEN 建物型態 LIKE '{n}%' THEN '{s}'" for s, n in TYPES.items()) + " END"

common_filter = f"""
    交易標的 IN ('房地(土地+建物)', '房地(土地+建物)+車位')
    AND ({TYPE_EXPR}) IS NOT NULL
    AND regexp_matches(交易筆棟數, '建物1(車位|$)')
    AND NOT regexp_matches(coalesce(備註, ''), '親友|特殊關係|二親等|關係人|員工')
    -- 政府配售、眷改、國宅等非市場交易
    AND NOT regexp_matches(coalesce(備註, ''), '配售|眷|國宅|政府機關|社會住宅|合宜住宅|公營|專案讓售')
    AND (主要用途 IS NULL OR 主要用途 = '' OR 主要用途 LIKE '%住%' OR 主要用途 LIKE '%見其%登記事項%' OR 主要用途 LIKE '%見使用執照%')
    AND {ty} BETWEEN 2012 AND {THIS_YEAR} AND {tm} BETWEEN 1 AND 12
"""

say('清洗交易資料')
con.execute(f"""
CREATE TEMP TABLE tx0 AS
SELECT county_code AS c, {DIST} AS d, {TYPE_EXPR} AS type, 'a' AS src,
    {ty} AS y, {tm} AS m,
    CASE WHEN try_cast(建築完成年月 AS INT) > 100 AND {bm} BETWEEN 1 AND 12
         THEN ({ty} + {tm} / 12.0) - ({by} + {bm} / 12.0) END AS age,
    try_cast(總價元 AS DOUBLE) AS price,
    try_cast(建物移轉總面積平方公尺 AS DOUBLE) * 0.3025 AS area,
    coalesce(try_cast(車位移轉總面積平方公尺 AS DOUBLE), 0) * 0.3025 AS parea,
    coalesce(try_cast(車位總價元 AS DOUBLE), 0) AS pprice,
    regexp_extract(土地位置建物門牌, '^(.*?號)', 1) AS addr
FROM raw_a
WHERE {common_filter}
    AND coalesce(備註, '') NOT LIKE '%預售屋%'   -- 預售屋交屋後的移轉登記，價格是當初簽約價，會跟 b 重複且時間落後
UNION ALL
SELECT county_code, {DIST}, {TYPE_EXPR}, 'b', {ty}, {tm}, NULL,
    try_cast(總價元 AS DOUBLE), try_cast(建物移轉總面積平方公尺 AS DOUBLE) * 0.3025,
    coalesce(try_cast(車位移轉總面積平方公尺 AS DOUBLE), 0) * 0.3025,
    coalesce(try_cast(車位總價元 AS DOUBLE), 0), ''
FROM raw_b
WHERE {common_filter} AND coalesce(解約情形, '') = ''
""")

# 車位估價：車位與房屋合併計價的，用 (區, 年) 車位每坪中位數估，缺的退回 (縣市, 年)、(全國, 年)
con.execute("""
CREATE TEMP TABLE ppark AS
SELECT c, d, y, median(pprice / parea) AS pu, count(*) AS n
FROM tx0 WHERE parea > 0.5 AND pprice > 0 GROUP BY GROUPING SETS ((c, d, y), (c, y), (y))
""")

# 月份索引：0 = 2012-08 (實價登錄上路)
M0 = 2012 * 12 + 7
con.execute(f"""
CREATE TEMP TABLE tx AS
SELECT t.*,
    t.y * 12 + t.m - 1 - {M0} AS mi,
    CASE WHEN t.src = 'b' THEN 'pre' WHEN t.age IS NULL THEN NULL
         WHEN t.age <= 3 THEN 'new' WHEN t.age <= 20 THEN 'mid' ELSE 'old' END AS band,
    (t.price - CASE WHEN t.parea <= 0 THEN 0 WHEN t.pprice > 0 THEN t.pprice
                    ELSE t.parea * coalesce(p1.pu, p2.pu, p3.pu) END) / (t.area - t.parea) AS up
FROM tx0 t
LEFT JOIN ppark p1 ON p1.c = t.c AND p1.d = t.d AND p1.y = t.y AND p1.n >= 10
LEFT JOIN ppark p2 ON p2.c = t.c AND p2.d IS NULL AND p2.y = t.y AND p2.n >= 10
LEFT JOIN ppark p3 ON p3.c IS NULL AND p3.d IS NULL AND p3.y = t.y
WHERE t.y * 12 + t.m - 1 >= {M0} AND t.area - t.parea >= 3 AND t.area - t.parea <= 300 AND t.price > 0 AND t.d != '' AND t.d IS NOT NULL
""")
con.execute("DELETE FROM tx WHERE up < 20000 OR up > 6000000 OR up IS NULL")

# 整批低價交易 (政府配售、眷改等常常沒寫備註)：同門牌同月 >= 20 筆，且中位數低於同區同年同屋齡層的 6 成
batch = con.execute("""
WITH ref AS (SELECT c, d, y, band, median(up) AS mu FROM tx WHERE src = 'a' GROUP BY ALL),
cl AS (SELECT c, d, y, mi, band, addr, count(*) AS n, median(up) AS mu FROM tx
       WHERE src = 'a' AND addr != '' GROUP BY ALL HAVING count(*) >= 20)
SELECT cl.c, cl.d, cl.mi, cl.addr, cl.n FROM cl JOIN ref USING (c, d, y, band) WHERE cl.mu < ref.mu * 0.6
""").df()
con.register('batch_df', batch)
con.execute("DELETE FROM tx WHERE src = 'a' AND (c, d, mi, addr) IN (SELECT (c, d, mi, addr) FROM batch_df)")
say(f'排除整批低價交易 {len(batch)} 批、{int(batch.n.sum()) if len(batch) else 0} 筆')
say('交易筆數 ' + ', '.join(f'{src}={n:,}' for src, n in con.execute("SELECT src, count(*) FROM tx GROUP BY 1 ORDER BY 1").fetchall()))

mcnt = dict(con.execute("SELECT mi, count(*) FROM tx WHERE src = 'a' GROUP BY 1").fetchall())
NM = max(mcnt) + 1
m_label = [f'{(M0 + i) // 12}-{(M0 + i) % 12 + 1:02d}' for i in range(NM)]
LAST = NM - 1

# 實價登錄有申報延遲 (成交 → 移轉登記 → 申報)，最後幾個月筆數不完整
# 從最後一個月往回找，筆數達到前 12 個月中位數 80% 的月份當作「資料完整」的終點
END = LAST
while END > 12:
    ref = sorted(mcnt.get(i, 0) for i in range(END - 12, END))[6]
    if mcnt.get(END, 0) >= ref * 0.8:
        break
    END -= 1
say(f'資料月份 {m_label[0]} ~ {m_label[LAST]}，完整資料到 {m_label[END]}')

# 區域 ID：TW / 縣市代碼 / 縣市代碼 + '-' + 鄉鎮市區
con.execute("""
CREATE TEMP TABLE txr AS
SELECT *, 'TW' AS r FROM tx
UNION ALL SELECT *, c FROM tx
UNION ALL SELECT *, c || '-' || d FROM tx
""")

say('彙總月資料')
agg = con.execute("""
SELECT r, coalesce(type, 'all') AS type, coalesce(band, 'all') AS band, mi,
    count(*) AS n,
    quantile_cont(up, 0.25) AS p25, quantile_cont(up, 0.5) AS p50, quantile_cont(up, 0.75) AS p75
FROM (SELECT r, type, band, mi, up FROM txr WHERE band IS NOT NULL)
GROUP BY GROUPING SETS ((r, type, band, mi), (r, band, mi))
UNION ALL
-- band = 'all' 指成屋全部屋齡 (不含預售)
SELECT r, coalesce(type, 'all'), 'all', mi, count(*),
    quantile_cont(up, 0.25), quantile_cont(up, 0.5), quantile_cont(up, 0.75)
FROM txr WHERE src = 'a'
GROUP BY GROUPING SETS ((r, type, mi), (r, mi))
""").df()

# ---------------- 品質調整指數 (固定效果 hedonic) ----------------
# ln(單價) = α[組別] + β[區域, 月]，組別 = 鄉鎮市區 × 類型 × 屋齡級距 × 坪數級距
say('計算品質調整指數')
h = con.execute("""
SELECT c, d, type, mi, ln(up) AS y,
    CASE WHEN age < 3 THEN 0 WHEN age < 10 THEN 1 WHEN age < 20 THEN 2
         WHEN age < 30 THEN 3 WHEN age < 40 THEN 4 ELSE 5 END AS ab,
    CASE WHEN area - parea < 15 THEN 0 WHEN area - parea < 25 THEN 1 WHEN area - parea < 35 THEN 2
         WHEN area - parea < 50 THEN 3 ELSE 4 END AS sb
FROM tx WHERE src = 'a' AND age IS NOT NULL AND age > -3
""").df()
h['stratum'] = pd.factorize(h['c'] + '|' + h['d'] + '|' + h['type'] + '|' + h['ab'].astype(str) + '|' + h['sb'].astype(str))[0]


def fe_index(df, gcol):
    """回傳 DataFrame(r, mi, beta, n)；交替投影解兩組固定效果"""
    y = df['y'].to_numpy()
    s = df['stratum'].to_numpy()
    gt_codes, gt_uni = pd.factorize(df[gcol].astype(str) + '#' + df['mi'].astype(str))
    ns = np.bincount(s)
    ngt = np.bincount(gt_codes)
    a = np.bincount(s, y) / ns
    for _ in range(60):
        b = np.bincount(gt_codes, y - a[s]) / ngt
        a_new = np.bincount(s, y - b[gt_codes]) / ns
        if np.max(np.abs(a_new - a)) < 1e-6:
            a = a_new
            break
        a = a_new
    b = np.bincount(gt_codes, y - a[s]) / ngt
    # 只有一筆的組別對 β 沒有資訊，計 n 時排除
    n_inf = np.bincount(gt_codes, (ns[s] > 1).astype(float))
    g, m = zip(*(u.rsplit('#', 1) for u in gt_uni))
    return pd.DataFrame({'r': g, 'mi': np.array(m, dtype=int), 'beta': b, 'n': n_inf.astype(int)})


h['tw'] = 'TW'
h['cd'] = h['c'] + '-' + h['d']
idx = pd.concat([fe_index(h, 'tw'), fe_index(h, 'c'), fe_index(h, 'cd')])

# ---------------- 租金 (年) ----------------
say('計算租金')
ry, rm = ym('租賃年月日')
RTYPE = "CASE " + " ".join(f"WHEN 建物型態 LIKE '{n}%' THEN '{s}'" for s, n in TYPES.items() if s != 'suite') + " END"
rent = con.execute(f"""
WITH r0 AS (
    SELECT county_code AS c, {DIST} AS d, {ry} AS y,
        try_cast(總額元 AS DOUBLE) / (try_cast(建物總面積平方公尺 AS DOUBLE) * 0.3025) AS rpp
    FROM raw_c
    WHERE ({RTYPE}) IS NOT NULL
        AND 交易標的 NOT LIKE '%車位%' AND 交易標的 != '土地'
        AND coalesce(出租型態, '') NOT IN ('分租套房', '獨立套房', '分租雅房')
        AND NOT regexp_matches(coalesce(備註, ''), '親友|特殊關係|員工')
        AND try_cast(建物總面積平方公尺 AS DOUBLE) * 0.3025 BETWEEN 8 AND 100
        AND {ry} BETWEEN 2012 AND {THIS_YEAR} AND 鄉鎮市區 != ''
), rr AS (
    SELECT 'TW' AS r, y, rpp FROM r0 UNION ALL SELECT c, y, rpp FROM r0 UNION ALL SELECT c || '-' || d, y, rpp FROM r0
)
SELECT r, y, count(*) AS n, median(rpp) AS rent FROM rr WHERE rpp BETWEEN 50 AND 10000 GROUP BY 1, 2
""").df()
sale_y = con.execute(f"""
SELECT r, (mi + {M0}) // 12 AS y, count(*) AS n, median(up) AS up FROM txr
WHERE src = 'a' AND type != 'suite' GROUP BY 1, 2
""").df()
rent = rent.merge(sale_y, on=['r', 'y'], how='left', suffixes=('', '_sale'))
rent['gy'] = rent['rent'] * 12 / rent['up']


# ---------------- 輸出 ----------------
def nz(v, digits=0):
    if v is None or (isinstance(v, float) and math.isnan(v)):
        return None
    return round(float(v), digits) if digits else int(round(float(v)))


def rolling(vals, ns, w=12, min_n=20):
    """n 加權移動平均，視窗內樣本不足 min_n 視為空"""
    out = []
    for i in range(len(vals)):
        win = [(vals[j], ns[j]) for j in range(max(0, i - w + 1), i + 1) if vals[j] is not None and ns[j]]
        tot = sum(n for _, n in win)
        out.append(sum(v * n for v, n in win) / tot if tot >= min_n else None)
    return out


say('輸出')
regions = {'TW': {'name': '全國', 'level': 0, 'parent': None}}
for c, cn in COUNTIES.items():
    regions[c] = {'name': cn, 'level': 1, 'parent': 'TW'}
for (c, d) in con.execute("SELECT DISTINCT c, d FROM tx ORDER BY 1, 2").fetchall():
    regions[f'{c}-{d}'] = {'name': d, 'level': 2, 'parent': c}

agg_g = {r: g for r, g in agg.groupby('r')}
idx_g = {r: g for r, g in idx.groupby('r')}
rent_g = {r: g for r, g in rent.groupby('r')}

summary = {}
bundles = {}   # (縣市代碼, 類型) → {rid: {...}}
for rid, meta in regions.items():
    a = agg_g.get(rid)
    if a is None:
        continue
    cid = rid.split('-')[0]
    for (t, b), g in a.groupby(['type', 'band']):
        arr = {k: [None] * NM for k in ('n', 'p25', 'p50', 'p75')}
        for row in g.itertuples():
            arr['n'][row.mi] = int(row.n)
            if row.n >= 3:
                arr['p25'][row.mi] = nz(row.p25)
                arr['p50'][row.mi] = nz(row.p50)
                arr['p75'][row.mi] = nz(row.p75)
        if sum(x or 0 for x in arr['n']) >= 20:
            bundles.setdefault((cid, t), {}).setdefault(rid, {'s': {}})['s'][b] = arr

    ix = idx_g.get(rid)
    beta, bn = [None] * NM, [0] * NM
    if ix is not None:
        for row in ix.itertuples():
            bn[row.mi] = int(row.n)
            if row.n > 0:
                beta[row.mi] = float(row.beta)
    rt = rent_g.get(rid)
    rent_out = []
    if rt is not None:
        for row in rt.sort_values('y').itertuples():
            rent_out.append({'y': int(row.y), 'n': int(row.n), 'rent': nz(row.rent),
                             'yield': None if pd.isna(row.gy) else round(float(row.gy), 4)})
    entry = bundles.setdefault((cid, 'all'), {}).setdefault(rid, {'s': {}})
    entry['index'] = {'beta': [None if v is None or n < 8 else round(v, 4) for v, n in zip(beta, bn)], 'n': bn}
    entry['rent'] = rent_out

    # 小圖、比較、漲幅都用 12 個月移動平均的指數 (視窗內至少 20 筆)
    spark = [None if v is None else round(v, 3) for v in rolling(beta, bn)]

    def chg(years):
        now, past = spark[END], spark[END - years * 12]
        return None if now is None or past is None else round(math.exp(now - past) - 1, 4)

    summary[rid] = {'id': rid, **meta, 'chg5': chg(5), 'chg10': chg(10),
                    'spark': spark if any(v is not None for v in spark) else None}

# 近 12 個月：各類型的單價中位數、筆數、預售 vs 新成屋價差
recent = con.execute(f"""
SELECT r, coalesce(type, 'all') AS type,
    median(up) FILTER (WHERE src = 'a') AS up,
    count(*) FILTER (WHERE src = 'a') AS n,
    median(up) FILTER (WHERE band = 'new') AS up_new,
    median(up) FILTER (WHERE band = 'pre') AS up_pre,
    count(*) FILTER (WHERE band = 'new') AS n_new,
    count(*) FILTER (WHERE band = 'pre') AS n_pre
FROM txr WHERE mi BETWEEN {END - 11} AND {END}
GROUP BY GROUPING SETS ((r, type), (r))
""").df()
by_type = {}
for row in recent.itertuples():
    prem = round(row.up_pre / row.up_new - 1, 4) if row.n_new >= 10 and row.n_pre >= 10 else None
    by_type.setdefault(row.r, {})[row.type] = {'up': nz(row.up), 'n12': int(row.n), 'pre_prem': prem,
                                               'up_pre': nz(row.up_pre) if prem is not None else None,
                                               'up_new': nz(row.up_new) if prem is not None else None}

end_y, end_m = (M0 + END) // 12, (M0 + END) % 12 + 1
RENT_YEAR = end_y if end_m == 12 else end_y - 1   # 最近一個完整年度
for rid, s in summary.items():
    bt = by_type.get(rid, {})
    s['by_type'] = bt
    s['up'] = bt.get('all', {}).get('up')
    s['n12'] = bt.get('all', {}).get('n12', 0)
    # 「全部住宅」的預售價差用住宅大樓比較，避免新成屋混進透天厝造成組成偏誤
    s['pre_prem'] = bt.get('tower', {}).get('pre_prem')
    rt = rent_g.get(rid)
    if rt is not None:
        rr = rt[rt.y == RENT_YEAR]
        if len(rr) and rr.iloc[0].n >= 15 and not pd.isna(rr.iloc[0]['gy']):
            s['yield'] = round(float(rr.iloc[0]['gy']), 4)
            s['rent'] = nz(rr.iloc[0]['rent'])

for (cid, t), b in bundles.items():
    os.makedirs(f'{OUT}/c/{cid}', exist_ok=True)
    json.dump(b, open(f'{OUT}/c/{cid}/{t}.json', 'w'), ensure_ascii=False, separators=(',', ':'))

PUBLIC_KEYS = ('id', 'name', 'level', 'parent', 'n12', 'chg5', 'chg10', 'up', 'pre_prem', 'yield', 'rent', 'spark')
json.dump({'m': m_label, 'end': END, 'last': LAST, 'rent_year': RENT_YEAR,
           'regions': [{k: s.get(k) for k in PUBLIC_KEYS} for s in summary.values()]},
          open(f'{OUT}/regions.json', 'w'), ensure_ascii=False, separators=(',', ':'))

# PHP 用：路由驗證 (area/type 的格式沿用 resolve_short_url)、選單、頁面摘要文字
districts = {c: sorted((rid for rid, s in summary.items() if s['parent'] == c), key=lambda rid: -summary[rid]['n12'])
             for c in COUNTIES}
releases = [os.path.basename(p) for p in glob.glob(f'{ROOT}/data/*') if os.path.isdir(p)]
site = {
    'build_id': BUILD_ID,
    'dataver': max([r for r in releases if r.isdigit()] or releases or ['不明']),
    'month_first': m_label[0], 'month_end': m_label[END], 'month_last': m_label[LAST],
    'month_end_start': m_label[END - 11],
    'rent_year': RENT_YEAR,
    'types': TYPES,
    'counties': {c: n for c, n in COUNTIES.items()},
    'districts': districts,
    'area': {COUNTIES[c]: [summary[rid]['name'] for rid in ids] for c, ids in districts.items()},
    'type': list(TYPES.values()),
    'regions': {rid: {k: v for k, v in s.items() if k != 'spark'} for rid, s in summary.items()},
}
os.makedirs(f'{ROOT}/build', exist_ok=True)
json.dump(site, open(f'{ROOT}/build/site.json', 'w'), ensure_ascii=False, indent=1)

# 只保留最近幾版
for old in sorted(glob.glob(f'{ROOT}/public/data/[0-9]*'))[:-KEEP_BUILDS]:
    shutil.rmtree(old)
say(f'完成：public/data/{BUILD_ID}，{len(summary)} 個區域')
