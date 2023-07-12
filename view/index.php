<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>房價趨勢統計</title>
<link type="text/css" rel="stylesheet" href="/s/main.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.3.0/chart.umd.js"></script>
<script src="/s/main.js" defer></script>
</head>
<body>

<section id="input">

<select id="area" onchange="update_subareas()">
<option value="載入中" selected>載入中</option>
</select>

<select id="type"></select>

<select id="parking"><option value="0">車位不拘</option><option value="1">有車位</option><option value="-1">無車位</option></select>

<span>
<input id="age_min" type="number" placeholder="屋齡下限"></input>
 ~
<input id="age_max" type="number" placeholder="屋齡上限"></input>
</span>

<br>

<fieldset id="subareas"></fieldset>

左 Y 軸：
<select id="y_left">
<option value="unit_price_avg">單價</option>
<option value="price_avg">總價</option>
<option value="cnt">總案量</option>
<option value="price_total">總交易額</option>
</select>

&nbsp;&nbsp;

右 Y 軸：
<select id="y_right">
<option value="unit_price_avg">單價</option>
<option value="price_avg" selected>總價</option>
<option value="cnt">總案量</option>
<option value="price_total">總交易額</option>
</select>

&nbsp;&nbsp;

<button onclick="update_chart()">產製圖表</button>

</section>

<section id="chart_wrapper">
<canvas id="chart"></canvas>
</section>

<script>
const options = {};

fetch("/api/option")
.then(resp => resp.json())
.then(data => {
    for (let key in data) options[key] = data[key];

    const area = document.getElementById("area");

    area.querySelectorAll("option").forEach(ele => ele.remove());

    const opt_placeholder = document.createElement("option");
    opt_placeholder.textContent = "選擇縣市";
    opt_placeholder.disabled = true;
    opt_placeholder.selected = true;
    area.append(opt_placeholder);

    for (let area_name of Object.keys(data["area"])) {
        const opt = document.createElement("option");
        opt.textContent = area_name;
        opt.value = area_name;
        opt.id = `area_${area_name}`;
        area.append(opt);
    }

    document.getElementById(`area_${Object.keys(data["area"])[0]}`).selected = true;
    update_subareas();

    const types = document.getElementById("type");
    for (let type of data["type"]) {
        const opt = document.createElement("option");
        opt.textContent = type;
        opt.value = type;
        types.append(opt);
    }
});

function update_subareas() {
    const area = document.getElementById("area").value;

    const subareas = document.getElementById("subareas");
    subareas.querySelectorAll(".checkbox-wrapper").forEach(ele => ele.remove());

    for (let subarea of options["area"][area]) {
        const wrapper = document.createElement("div");
        wrapper.classList.add("checkbox-wrapper");

        const opt = document.createElement("input");
        opt.type = "checkbox";
        opt.textContent = subarea;
        opt.value = subarea;
        opt.name = `subarea`;
        opt.id = `subarea_${subarea}`;

        const lbl = document.createElement("label");
        lbl.textContent = `${subarea} `;
        lbl.htmlFor = opt.id;

        wrapper.append(opt);
        wrapper.append(lbl);
        subareas.append(wrapper);
    }
}

</script>

</body>
</html>