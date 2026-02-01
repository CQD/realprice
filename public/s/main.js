const chart_canvas = document.getElementById("chart");
const ctx = chart_canvas.getContext("2d");
const tooltipEnabled = (window.screen.width > 640);

const gen_btn = document.getElementById("gen_btn");
const loading_spinner = document.getElementById("loading");

function chart_msg(txt) {
    ctx.clearRect(0, 0, chart_canvas.width, chart_canvas.height);
    ctx.font = "bold 20px sans-serif";
    ctx.fillText(txt, 30, 30);
}

function show_loading() {
    loading_spinner.classList.add("show");
}
function hide_loading() {
    loading_spinner.classList.remove("show");
}

function ym_list() {
    const ym_list = [];

    const chart_end_date = new Date();
    const end_y = chart_end_date.getFullYear();
    const end_m = chart_end_date.getMonth() + 1;

    let y = 2012;
    let m = 8;
    while (y < end_y || (y == end_y && m <= end_m)) {
        ym_list.push((m < 10) ? `${y}/0${m}` : `${y}/${m}`);
        m += 1;
        if (m > 12) {
            y += 1;
            m = 1;
        }

    }
    return ym_list;
}

const data = {
    labels:ym_list(),
    datasets:[],
}

function ytick(value, index, values) {
    let foo = ['', '萬', '億', '兆', '京'];
    let idx = 0;
    while (value >= 10000) {
        value /= 10000;
        idx++;
    }
    return value + foo[idx];
}

const chart_config = {
    type: 'bar',
    data: data,
    options: {
        interaction: {
            intersect: false,
            mode: 'index',
        },
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: '',
            },
            tooltip: {
                enabled: tooltipEnabled,
            },
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                grid: {
                    drawOnChartArea: false,
                },
                ticks: {
                    beginAtZero: true,
                    callback: ytick,
                },
            },
            yr: {
                type: 'linear',
                display: true,
                position: 'right',
                grid: {
                    drawOnChartArea: false,
                },
                ticks: {
                    beginAtZero: true,
                    callback: ytick,
                },
            },
        },
    }
};

const chart = new Chart(chart_canvas, chart_config);

function update_chart(params, push_history=true) {
    console.log("update_chart", params, push_history)
    params = params || chart_params();

    // 依照 key 排序，確保 URL 參數的順序一致，以提高 cache 命中率
    let ordered_params = Object.keys(params).sort().reduce(
        (obj, key) => {
            obj[key] = params[key];
            return obj;
        },
        {}
    );
    params = ordered_params;

    const url = "/api/data?" + new URLSearchParams(params);

    show_loading();
    chart_msg("載入中...");

    const start_time = new Date();

    fetch(url)
    .then(resp => {
        console.log("Api response time:", new Date() - start_time, "ms")
        return resp.json()
    })
    .then(resp => {
        chart_msg("繪製中...");
        update_chart_with_data(resp.data, params);

        for (const key in resp) {
            if (key == "data") continue;
            console.log(key, resp[key])
        }

        // 如果圖太下面，就捲動到能看清圖的位置
        // 但連結進來不捲動
        const chart_top = chart_canvas.getBoundingClientRect().top;
        if (push_history && chart_top > window.innerHeight * 0.5) {
            document.getElementById("y_left").scrollIntoView({behavior: 'smooth'});
        }
    })
    .catch(err => {
        console.log(err);
        chart_msg("載入失敗，請稍後重試 /_\\");
    }).finally(() => {
        gen_btn.classList.remove("disabled");
        hide_loading();
    });

    params.y_left = document.getElementById("y_left").value;
    params.y_right = document.getElementById("y_right").value;
    delete params.v;

    const title = title_from_params(params);
    document.getElementById("pagetitle").textContent = title;
    document.title = `${title} | 實價登錄房價趨勢`;
    if (push_history) {
        window.history.pushState(params, null, build_short_url(params));
    }
}

function click_gen_btn() {
    if (gen_btn.classList.contains("disabled")) return;
    gen_btn.classList.add("disabled");
    update_chart();
}

function title_from_params(params) {
    const PARKING_MAP = {
        "0": "車位不拘",
        "1": "有車位",
        "-1": "無車位",
    };

    let title = [
        params.area + ((params.subarea) ? ` (${params.subarea})` : ""),
        (params.type) ? ` ${params.type}` : " 建築類型不拘",
        PARKING_MAP[params.parking],
        `屋齡 ${params.age_min}年 ~ ${params.age_max}年`,
    ]
    .join(" - ")
    .replaceAll("undefined年", "不限")
    .replaceAll(" 不限 ~ 不限", "不限")

    return title;
}

function update_chart_with_data(data, params) {
    const datasets = [];

    const y_left = document.getElementById("y_left").selectedOptions[0];
    const y_right = document.getElementById("y_right").selectedOptions[0];
    const f = [
        [y_left.value,  y_left.textContent,  "rgba(255, 128, 18, 1)", {}],
        [y_right.value, y_right.textContent, "#aaccff", {type: "bar", yAxisID: "yr"}],
    ];

    for (const [field, field_name, color, conf] of f) {
        const values = [];
        for (const ym of ym_list()) {
            if (data[ym]) {
                values.push(data[ym][field] || null);
            } else {
                values.push(null);
            }
        }

        const dataset = {
            label: field_name,
            data: values,
            borderColor: color,
            backgroundColor: color,
            type: "line",
            yAxisID: "y",
            pointRadius: 0,
        }
        for (let field in conf) {
            dataset[field] = conf[field];
        }

        datasets.push(dataset);
    }

    chart.data.datasets = datasets;
    chart.options.plugins.title.text = title_from_params(params);
    chart.update();
}

function chart_params() {
    const params = {
        "v": ASSET_VERSION || (new Date().getDate()),
        "area": document.getElementById("area").value,
        "parking": document.getElementById("parking").value,
    };

    const type = document.getElementById("type").value;
    if (type) params.type = type;

    const subarea_ele = document.querySelector("input[name='subarea']:checked");
    const subarea = subarea_ele ? subarea_ele.value : null;
    if (subarea && subarea != "全區域") {
        params.subarea = subarea;
    }

    for (const age_field of ["age_min", "age_max"]) {
        const age = document.getElementById(age_field).value;
        if (age) {
            params[age_field] = age;
        }
    }

    return params;
}

function update_chart_with_query() {
    let params;
    const qs = new URLSearchParams(window.location.search);

    if (qs.has("area")) {
        // 舊格式 query parameter，向下相容
        params = Object.fromEntries(qs.entries());
        for (const key in DEFAULT_VALUES) {
            if (!params[key] && !qs.has(key)) params[key] = DEFAULT_VALUES[key];
        }
        // replaceState 為新格式短網址
        const short_url = build_short_url(params);
        window.history.replaceState(params, null, short_url);
    } else if (SHORT_URL_PARAMS) {
        // 短網址：PHP 已解析好路徑參數
        params = Object.assign({}, SHORT_URL_PARAMS);
        // 合併 query parameter
        for (const [key, val] of qs.entries()) {
            params[key] = val;
        }
        for (const key in DEFAULT_VALUES) {
            if (!params[key] && !qs.has(key)) params[key] = DEFAULT_VALUES[key];
        }
    } else {
        // 從路徑解析 (popstate)
        params = parse_short_url();
        if (!params) return;
    }

    for (const field of ["age_min", "age_max", "area", "parking", "type", "y_right", "y_left"]) {
        const field_ele = document.getElementById(field);
        if (!params[field]) {
            field_ele.value = DEFAULT_VALUES[field] || "";
            continue;
        }
        field_ele.value = params[field];
    }

    update_subareas();

    if (params.subarea) {
        const el = document.querySelector(`#subarea_${params.subarea}`);
        if (el) el.checked = true;
    } else {
        document.querySelector("#subarea_全區域").checked = true;
    }

    params.v = ASSET_VERSION || (new Date().getDate());

    delete params["y_left"];
    delete params["y_right"];
    update_chart(params, push_history=false);
}

function update_subareas() {
    const area = document.getElementById("area").value;

    const subareas = document.getElementById("subareas");
    subareas.querySelectorAll(".checkbox-wrapper").forEach(ele => ele.remove());

    for (let subarea of ["全區域", ...options["area"][area]]) {
        const wrapper = document.createElement("div");
        wrapper.classList.add("checkbox-wrapper");

        const opt = document.createElement("input");
        opt.type = "radio";
        opt.textContent = subarea;
        opt.value = subarea;
        opt.name = `subarea`;
        opt.id = `subarea_${subarea}`;

        if (subarea == "全區域") {
            opt.checked = true;
        }

        const lbl = document.createElement("label");
        lbl.textContent = `${subarea} `;
        lbl.htmlFor = opt.id;

        wrapper.append(opt);
        wrapper.append(lbl);
        subareas.append(wrapper);
    }
}

const DEFAULT_VALUES = {
    "type": "住宅大樓",
    "y_left": "no_parking_unit_price_median",
    "y_right": "cnt",
    "parking": "0",
    "age_max": "3",
};

function build_short_url(params) {
    let path = "/" + encodeURIComponent(params.area);
    if (params.subarea) {
        path += "/" + encodeURIComponent(params.subarea);
    }
    if (params.type) {
        path += "/" + encodeURIComponent(params.type);
    }

    const query_params = {};
    for (const key of ["type", "parking", "age_min", "age_max", "y_left", "y_right"]) {
        const val = params[key];
        // type 在路徑中處理，但「類型不拘」(空值) 需要明確標記
        if (key === "type") {
            if (val === "" || val === undefined || val === null) {
                query_params["type"] = "";
            }
            continue;
        }
        if (val !== undefined && val !== null && val !== "" && val !== DEFAULT_VALUES[key]) {
            query_params[key] = val;
        }
    }

    const qs = new URLSearchParams(query_params).toString();
    return qs ? path + "?" + qs : path;
}

function parse_short_url() {
    const path = decodeURIComponent(window.location.pathname);
    const segments = path.split("/").filter(s => s !== "");
    if (segments.length === 0) return null;

    const params = {};
    params.area = segments[0];

    if (segments.length >= 2) {
        if (TYPES.includes(segments[1])) {
            params.type = segments[1];
        } else {
            params.subarea = segments[1];
        }
    }
    if (segments.length >= 3) {
        if (TYPES.includes(segments[2])) {
            params.type = segments[2];
        }
    }

    // query parameter 覆蓋
    const qs = new URLSearchParams(window.location.search);
    for (const [key, val] of qs.entries()) {
        params[key] = val;
    }

    // 套用預設值（但 query param 明確設為空值的不覆蓋）
    for (const key in DEFAULT_VALUES) {
        if (!params[key] && !qs.has(key)) params[key] = DEFAULT_VALUES[key];
    }

    return params;
}

//////////////////////////////

window.addEventListener("popstate", (event) => {
    update_chart_with_query();
});


if (window.location.search || SHORT_URL_PARAMS) {
    update_chart_with_query();
} else if (window.location.pathname !== "/" && parse_short_url()) {
    update_chart_with_query();
} else {
    document.getElementById("area").value = '臺北市';
    document.getElementById("type").value = '住宅大樓';
    document.getElementById("age_max").value = '3';
    update_subareas();
    click_gen_btn();
}
