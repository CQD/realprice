<?php
global $PARKING_MAP, $parking, $type, $age_min, $age_max;

$OPTION = require __DIR__ . "/../build/option.php";

$PARKING_MAP = [
    "0" => "車位不拘",
    "1" => "有車位",
    "-1" => "無車位",
];

$area = $_GET["area"] ?? null;
$area = array_key_exists($area, $OPTION["area"]) ? $area : array_keys($OPTION["area"])[0];

$parking = $_GET["parking"] ?? 0;
$parking = ($PARKING_MAP[$parking] ?? false) ? $parking : 0;

$type = $_GET["type"] ?? null;
$type = in_array($type, $OPTION["type"]) ? $type : $OPTION["type"][0];

$age_min = $_GET["age_min"] ?? null ?: null;
$age_max = $_GET["age_max"] ?? null ?: null;
if (!is_numeric($age_min)) $age_min = null;
if (!is_numeric($age_max)) $age_max = null;

function og_title() {
    global $PARKING_MAP, $parking, $type, $age_min, $age_max;

    if (!isset($_GET["area"])) return "實價登錄房價趨勢";

    $og_title = [
        $_GET["area"] . ((isset($_GET["subarea"])) ? " (" . $_GET["subarea"] . ")" : ""),
        $type ?: " 建築類型不拘",
        $PARKING_MAP[$parking],
        "屋齡 {$age_min}年 ~ {$age_max}年",
    ];
    $og_title = implode(" - ", $og_title);
    $og_title = str_replace(" 年", " 不限", $og_title);
    $og_title = str_replace(" 不限 ~ 不限", "不限", $og_title);

    return $og_title;
}
$og_title = og_title();
?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>實價登錄房價趨勢</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=1">
<link rel="icon" type="image/png" href="/favicon.png" >
<link rel="apple-touch-icon" href="/favicon.png">
<meta name="theme-color" content="#f25500">
<link type="text/css" rel="stylesheet" href="/s/main.css?v=<?=ASSET_VERSION?>">
<link rel="index" href="https://realprice.cqd.tw/" title="實價登錄房價趨勢">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.3.0/chart.umd.js"></script>
<script src="/s/main.js?v=<?=ASSET_VERSION?>" defer></script>

<meta property="og:title" content="<?=e($og_title)?>">
<meta property="og:image" content="https://realprice.cqd.tw/og.png">
<meta name="twitter:title" content="<?=e($og_title)?>">
<meta name="twitter:image" content="https://realprice.cqd.tw/og.png">

</head>
<body>

<script async src="https://www.googletagmanager.com/gtag/js?id=G-XPVFS6XXKD"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', 'G-XPVFS6XXKD');
</script>

<header><div class="wrapper"><h1>實價登錄房價趨勢</h1><small id="pagetitle"><?=e(str_replace("實價登錄房價趨勢", "", $og_title))?></small></div></header>
<section id="input">

<select id="area" onchange="update_subareas()">
<?php foreach($OPTION["area"] as $_area => $_subareas):?>
<option id="area_<?=e($_area)?>" value="<?=e($_area)?>"<?=($_area == $area) ? " selected" : ""?>><?=e($_area)?></option>
<?php endforeach; ?>
</select>

<select id="type">
<option value="">類型不拘</option>
<?php foreach($OPTION["type"] as $_type):?>
<option value="<?=e($_type)?>"<?=($_type == $type) ? " selected" : ""?>><?=e($_type)?></option>
<?php endforeach; ?>
</select>

<select id="parking"><option value="0">車位不拘</option><option value="1">有車位</option><option value="-1">無車位</option></select>
&nbsp;
<div>
屋齡年份
<input id="age_min" type="number" placeholder="不限"></input> ~
<input id="age_max" type="number" placeholder="不限"></input>
</div>
<br>

<fieldset id="subareas">
<?php foreach ($OPTION["area"][$area] as $_subarea): ?>
<div class="checkbox-wrapper">
<input type="checkbox" value="<?=e($_subarea)?>" name="subarea" id="subarea_<?=e($_subarea)?>">
<label for="subarea_<?=e($_subarea)?>"><?=e($_subarea)?></label>
</div>
<?php endforeach;?>
</fieldset>

<div class="y_select_group">
左 Y 軸
<select id="y_left">
<option value="no_parking_unit_price_median">中位數單價(排除車位)</option>
<option value="unit_price_median">中位數單價(計入車位)</option>
<option value="price_median">中位數房價</option>
<option value="area_median">中位數坪數</option>
<?php /*
<option value="unit_price_avg">平均單價(計入車位)</option>
<option value="no_parking_unit_price_avg">平均單價(排除車位)</option>
<option value="price_avg">平均房價</option>
<option value="area_avg">平均坪數</option>
*/?>
<option value="cnt">總交易筆數</option>
<option value="price_total">總交易額</option>
</select>
</div>

&nbsp;&nbsp;

<div class="y_select_group">
右 Y 軸
<select id="y_right">
<option value="no_parking_unit_price_median">中位數單價(排除車位)</option>
<option value="unit_price_median">中位數單價(計入車位)</option>
<option value="price_median">中位數房價</option>
<option value="area_median">中位數坪數</option>
<?php /*
<option value="unit_price_avg">平均單價(計入車位)</option>
<option value="no_parking_unit_price_avg">平均單價(排除車位)</option>
<option value="price_avg">平均房價</option>
<option value="area_avg">平均坪數</option>
*/?>
<option value="cnt" selected>總交易筆數</option>
<option value="price_total">總交易額</option>
</select>
</div>

&nbsp;&nbsp;

<button id="gen_btn" onclick="click_gen_btn()">產製圖表</button>

</section>

<section id="chart_wrapper">
<canvas id="chart"></canvas>
</section>

<footer>
<div class="wrapper">
資料版本：<span id="dataver"><?=$OPTION["dataver"]?></span><br>
看的是整體統計趨勢，不能套用在單一建案。<br>
理論上應該在 30 天內登記，但實際登記狀況可能延遲到兩三個月。<br>

<p>
<a name="作者網頁" title="作者網頁" href="https://cqd.tw"><img alt="作者" src="https://cqd.tw/avatar.png" loading="lazy"></a>
&nbsp;
<a name="作者噗浪" title="作者噗浪" href="https://plurk.com/CQD"><img alt="噗浪" src="https://cqd.tw/plurk.svg" loading="lazy"></a>
&nbsp;
<a name="完整程式碼" title="完整程式碼" href="https://github.com/CQD/realprice/"><img alt="Github" src="https://cqd.tw/github.svg" loading="lazy"></a>
&nbsp;
<a name="水庫水情與歷年統計" title="水庫水情與歷年統計" href="https://reservoir.cqd.tw/"><img alt="水庫水情與歷年統計" src="https://reservoir.cqd.tw/favicon.png" loading="lazy"></a>
</p>

</div>
</footer>
<div id="loading">
<div class="lds"><div></div><div></div><div></div><div></div></div>
</div>


<script>
const options = <?=json_encode($OPTION)?>;
ASSET_VERSION = '<?=ASSET_VERSION?>';

</script>

</body>
</html>
