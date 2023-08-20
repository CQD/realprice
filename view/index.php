<?php
global $PARKING_MAP, $parking, $type, $age_min, $age_max;

$OPTION = require __DIR__ . "/../build/option.php";

$PARKING_MAP = [
    "0" => "車位不拘",
    "1" => "有車位",
    "-1" => "無車位",
];

$parking = $_GET["parking"] ?? 0;
$parking = ($PARKING_MAP[$parking] ?? false) ? $parking : 0;

$type = $_GET["type"] ?? "";
$type = in_array($type, $OPTION["type"]) ? $type : "";

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
    $og_title = str_replace("不限年", "不限", $og_title);
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

<section><h1>實價登錄房價趨勢</h1><small id="pagetitle"></small></section>
<section id="input">

<select id="area" onchange="update_subareas()">
<option value="載入中" selected>載入中</option>
</select>

<select id="type">
<option value="">類型不拘</option>
</select>

<select id="parking"><option value="0">車位不拘</option><option value="1">有車位</option><option value="-1">無車位</option></select>

<span>
<input id="age_min" type="number" placeholder="屋齡下限" size="10"></input>
 ~
<input id="age_max" type="number" placeholder="屋齡上限" size="10"></input>
</span>

<br>

<fieldset id="subareas"></fieldset>

左 Y 軸
<select id="y_left">
<option value="unit_price_avg">平均單價</option>
<option value="price_avg">平均房價</option>
<option value="area_avg">平均坪數</option>
<option value="cnt">總交易筆數</option>
<option value="price_total">總交易額</option>
</select>

&nbsp;&nbsp;

右 Y 軸
<select id="y_right">
<option value="unit_price_avg">平均單價</option>
<option value="price_avg">平均房價</option>
<option value="area_avg">平均坪數</option>
<option value="cnt" selected>總交易筆數</option>
<option value="price_total">總交易額</option>
</select>

&nbsp;&nbsp;

<button onclick="update_chart()">產製圖表</button>

</section>

<section id="chart_wrapper">
<canvas id="chart"></canvas>
</section>

<footer>
<div>資料版本：<span id="dataver">不明</span><br>
程式碼：<a href="https://github.com/CQD/realprice">https://github.com/CQD/realprice</a><br>
以整體狀況為主，不在意單一建案，不適合用來作為「這一戶好嗎？該買嗎」的基準。</div>
<div id="footer_msg"></div>
</footer>


<script>
const options = {};
ASSET_VERSION = '<?=ASSET_VERSION?>';

</script>

</body>
</html>
