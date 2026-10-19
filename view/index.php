<?php
use Q\RealPrice\Site;

$hasDistricts = (bool) $districts;
// 縣市頁：有分區的縣市顯示地圖、各區小圖、排行；嘉義市、新竹市這類沒分區的縣市改用鄉鎮市區頁的版型
$showRegionDetail = $level === 2 || ($level === 1 && !$hasDistricts);
?><!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<title><?=e($title)?></title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="description" content="<?=e($summary)?>">
<link rel="canonical" href="<?=e($canonical)?>">
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="apple-touch-icon" href="/favicon.png">
<meta name="theme-color" content="#f25500">
<meta property="og:title" content="<?=e($title)?>">
<meta property="og:description" content="<?=e($summary)?>">
<meta property="og:image" content="https://realprice.cqd.tw/og.png">
<meta name="twitter:title" content="<?=e($title)?>">
<meta name="twitter:image" content="https://realprice.cqd.tw/og.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&family=IBM+Plex+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="/s/app.css?v=<?=ASSET_VERSION?>">
<script src="https://cdn.jsdelivr.net/npm/echarts@5.5.1/dist/echarts.min.js" defer></script>
<?php if (!$showRegionDetail): ?>
<script src="https://cdn.jsdelivr.net/npm/topojson-client@3.1.0/dist/topojson-client.min.js" defer></script>
<?php endif; ?>
<script>window.PAGE = <?=json_encode($pageConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;</script>
<script src="/s/app.js?v=<?=ASSET_VERSION?>" defer></script>
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XPVFS6XXKD"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', 'G-XPVFS6XXKD');
</script>
</head>
<body>

<header class="top">
  <div class="wrap">
    <a class="brand" href="/">實價登錄房價趨勢</a>
    <nav class="crumbs" aria-label="目前位置">
<?php foreach ($breadcrumbs as $i => [$label, $href]): ?>
      <?=$i ? '<span aria-hidden="true">›</span>' : ''?><a href="<?=e($href)?>"<?=$i === count($breadcrumbs) - 1 ? ' aria-current="page"' : ''?>><?=e($label)?></a>
<?php endforeach; ?>
    </nav>
  </div>
</header>

<main class="wrap">
  <h1><?=e($level === 0 ? '全台房價趨勢' : $name . '房價趨勢')?><?php if ($type !== 'all'): ?><span class="h1-type"><?=e($typeName)?></span><?php endif; ?></h1>
  <p class="lede"><?=e($summary)?></p>

  <div class="filters" role="group" aria-label="切換區域與類型">
    <label class="field"><span>縣市</span>
      <select id="f-county">
        <option value="TW"<?=$level === 0 ? ' selected' : ''?>>全台</option>
<?php foreach ($site['counties'] as $cid => $cname): ?>
<?php if (isset($site['regions'][$cid])): ?>
        <option value="<?=e($cid)?>"<?=$cid === $countyId ? ' selected' : ''?>><?=e($cname)?></option>
<?php endif; ?>
<?php endforeach; ?>
      </select>
    </label>
<?php if ($hasDistricts): ?>
    <label class="field"><span>鄉鎮市區</span>
      <select id="f-district">
        <option value="<?=e($countyId)?>"<?=$level === 1 ? ' selected' : ''?>>全區</option>
<?php foreach ($districts as $did): ?>
        <option value="<?=e($did)?>"<?=$did === $id ? ' selected' : ''?>><?=e(Site::region($did)['name'])?></option>
<?php endforeach; ?>
      </select>
    </label>
<?php endif; ?>
<?php if ($level > 0): ?>
    <div class="field"><span>建物類型</span>
      <div class="chips">
        <a class="chip" href="<?=e(Site::url($id))?>"<?=$type === 'all' ? ' aria-current="page"' : ''?>>全部住宅</a>
<?php foreach ($site['types'] as $slug => $tname): ?>
        <a class="chip" href="<?=e(Site::url($id, $slug))?>"<?=$type === $slug ? ' aria-current="page"' : ''?>><?=e($tname)?></a>
<?php endforeach; ?>
      </div>
    </div>
<?php endif; ?>
  </div>

  <div class="kpis">
<?php foreach ($kpis as [$k, $v, $unit, $sub]): ?>
    <div class="kpi"><span class="k"><?=e($k)?></span><span class="v"><?=e($v)?><?php if ($unit && $v !== '—'): ?><small><?=e($unit)?></small><?php endif; ?></span><span class="s"><?=e($sub)?></span></div>
<?php endforeach; ?>
  </div>

<?php if ($level === 0): ?>
  <div class="filters" role="group" aria-label="地圖指標">
    <label class="field"><span>顏色指標</span>
      <select id="f-metric">
        <option value="chg10">10 年漲幅（品質調整）</option>
        <option value="chg5">5 年漲幅（品質調整）</option>
        <option value="up">近一年單價中位數</option>
        <option value="pre_prem">預售 vs 新成屋價差（住宅大樓）</option>
        <option value="yield">毛租金收益率</option>
      </select>
    </label>
  </div>
  <div class="grid2">
    <section class="panel">
      <header><div><h2 id="map-title">地圖</h2><p id="map-sub">顏色越暖代表數值越高，越冷或越淡代表越低，灰色是樣本不足。點區域可以看該區的走勢。</p></div></header>
      <div class="chart tall" id="c-map"></div>
    </section>
    <section class="panel">
      <header><div><h2>價格水準 vs 漲幅</h2><p id="scatter-sub"></p></div></header>
      <div class="chart tall" id="c-scatter"></div>
    </section>
    <section class="panel full">
      <header>
        <div><h2>多區比較：品質調整指數</h2><p>點選縣市排行/散布圖以新增欲比較區域，最多四個區域</p></div>
        <label class="field"><span>基期</span><select id="f-base"></select></label>
      </header>
      <div class="cmp-chips" id="cmp-chips"></div>
      <div class="chart" id="c-cmp"></div>
    </section>
  </div>

  <div class="sec-head">
    <h2 id="table-title">排行</h2>
    <div class="filters inline">
      <div class="seg" role="group" aria-label="排行範圍">
        <button type="button" data-scope="county" aria-pressed="true">縣市</button>
        <button type="button" data-scope="district" aria-pressed="false">鄉鎮市區</button>
      </div>
      <label class="field"><span class="sr">搜尋</span><input type="search" id="f-q" placeholder="搜尋區名，例如 竹北"></label>
    </div>
  </div>
  <div class="tablebox"><table id="t-rank"></table></div>

<?php else: ?>
  <section class="panel full">
    <header>
      <div><h2>成交單價中位數（萬/坪）與成交筆數</h2><p>新成屋：≤3 年; 中古屋：3–20 年; 老屋：&gt;20 年;  陰影：25–75 百分位區間</p></div>
      <div class="filters inline">
        <label class="field"><span>區間顯示</span><select id="f-iqr"><option value="new">新成屋</option><option value="pre">預售屋</option><option value="mid">中古屋</option><option value="old">老屋</option><option value="">不顯示</option></select></label>
        <label class="toggle"><input type="checkbox" id="f-smooth" checked> 12 個月移動平均</label>
      </div>
    </header>
    <div class="chart" id="c-price"></div>
    <div class="chart" id="c-vol" style="height:150px"></div>
    <details class="dt"><summary>以表格檢視（年度）</summary><div class="tablebox"><table id="t-year"></table></div></details>
  </section>

  <div class="grid2">
    <section class="panel">
      <header>
        <div><h2>長期漲幅：品質調整指數（基期 = 100）</h2><p>固定「區 × 類型 × 屋齡 × 坪數」組合後的價格變化（全部住宅類型）。虛線是單純中位數，兩條線的落差就是組成變化的影響。</p></div>
        <label class="field"><span>基期</span><select id="f-base"></select></label>
      </header>
      <div class="chart" id="c-index"></div>
    </section>
<?php if ($showRegionDetail): ?>
    <section class="panel">
      <header><div><h2>租金與毛租金收益率</h2><p>整層住家（不含套房與分租）月租金中位數與「年租金 ÷ 成屋單價」<br>2018 年前租賃申報少，僅供參考</p></div></header>
      <div class="chart sm" id="c-rent"></div>
      <div class="chart sm" id="c-yield"></div>
    </section>
<?php else: ?>
    <section class="panel">
      <header>
        <div><h2 id="map-title">各區地圖</h2><p id="map-sub">點區域可以看該區的走勢。</p></div>
        <label class="field"><span>顏色指標</span>
          <select id="f-metric">
            <option value="chg10">10 年漲幅</option>
            <option value="chg5">5 年漲幅</option>
            <option value="up">近一年單價</option>
            <option value="pre_prem">預售價差</option>
            <option value="yield">租金收益率</option>
          </select>
        </label>
      </header>
      <div class="chart" id="c-map"></div>
    </section>
<?php endif; ?>
  </div>

<?php $mcounty = $level === 2 ? $countyId : ($hasDistricts ? $countyId : null); ?>
<?php if ($mcounty && Site::districtsOf($mcounty)): ?>
  <div class="sec-head">
    <div><h2><?=e($site['counties'][$mcounty])?>各區比較</h2><p class="small muted">品質調整指數（12 個月移動平均，全部住宅），2016 = 100。</p></div>
    <label class="field"><span>排序</span><select id="f-sort"><option value="chg10">10 年漲幅</option><option value="chg5">5 年漲幅</option><option value="up">近一年單價</option><option value="n12">近一年筆數</option></select></label>
  </div>
  <div class="multiples" id="multiples"></div>
<?php endif; ?>

<?php if ($level === 1 && $hasDistricts): ?>
  <div class="sec-head">
    <h2><?=e($name)?>鄉鎮市區排行</h2>
  </div>
  <div class="tablebox"><table id="t-rank"></table></div>
<?php endif; ?>
<?php endif; ?>
</main>

<footer>
  <div class="wrap">
    <details class="method">
      <summary>資料與計算方式</summary>
      <ul>
        <li>資料來源：內政部不動產成交案件實際資訊（實價登錄），含成屋、預售屋與租賃。資料版本 <?=e($site['dataver'])?>，每月一個資料點，統計到 <?=e($site['month_end'])?>；之後的月份還在申報中，筆數不完整。</li>
        <li>單價一律扣除車位：車位有單獨標價就直接扣，沒有的話用同區同年的車位單價中位數估算。</li>
        <li>排除的交易：親友、員工等特殊關係交易；政府配售、眷改、國宅；預售屋交屋後的移轉登記（價格是當初簽約價，會跟預售資料重複）；多棟合併交易；非住宅用途；同一門牌同一個月 20 筆以上、單價不到同區行情 6 成的整批交易。</li>
        <li>品質調整指數：把交易依「鄉鎮市區 × 建物類型 × 屋齡級距 × 坪數級距」分組，估計每個月扣掉組別差異後的價格水準。5 年、10 年漲幅是近 12 個月與 5、10 年前同期的比較。</li>
        <li>毛租金收益率 = 整層住家月租金中位數 × 12 ÷ 成屋單價中位數（不含套房）。租賃申報率偏低，僅供參考。</li>
        <li>看的是整體統計趨勢，不能套用在單一建案。</li>
      </ul>
    </details>
    <p class="links">
      <a title="作者網頁" href="https://cqd.tw"><img alt="作者" src="https://cqd.tw/avatar.png" loading="lazy"></a>
      <a title="作者噗浪" href="https://plurk.com/CQD"><img alt="噗浪" src="https://cqd.tw/plurk.svg" loading="lazy"></a>
      <a title="完整程式碼" href="https://github.com/CQD/realprice/"><img alt="Github" src="https://cqd.tw/github.svg" loading="lazy"></a>
      <a title="水庫水情與歷年統計" href="https://reservoir.cqd.tw/"><img alt="水庫水情與歷年統計" src="https://reservoir.cqd.tw/favicon.png" loading="lazy"></a>
    </p>
  </div>
</footer>
</body>
</html>
