<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>房價趨勢統計</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=3.0, user-scalable=1">
<link rel="icon" type="image/png" href="favicon.png" >
<link type="text/css" rel="stylesheet" href="/s/main.css?v=<?=ASSET_VERSION?>">
<link rel="index" href="https://realprice.cqd.tw/" title="房價趨勢統計">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.3.0/chart.umd.js"></script>
<script src="/s/main.js?v=<?=ASSET_VERSION?>" defer></script>

<meta property="og:image" content="https://realprice.cqd.tw/og.png">
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

<section><h1>房價趨勢統計</h1><small id="pagetitle"></small></section>
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
<div>資料版本：20230811</div>
<div id="footer_msg"></div>
</footer>


<script>
const options = {};
ASSET_VERSION = '<?=ASSET_VERSION?>';

</script>

</body>
</html>
