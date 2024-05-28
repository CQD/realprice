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
        window.history.pushState(params, null, "?" + new URLSearchParams(params));
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

    const subarea_eles = document.querySelectorAll("input[name='subarea']:checked");
    if (subarea_eles.length) {
        params.subarea = Array.from(subarea_eles).map(ele => ele.value).join(",");
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
    const params = Object.fromEntries((new URLSearchParams(window.location.search)).entries());

    if (!params.hasOwnProperty("area")) return;

    const DEFAULT_VALUES = {
        "y_left": "unit_price_median",
        "y_right": "cnt",
        "parking": "0",
    };
    for (const field of ["age_min", "age_max", "area", "parking", "type", "y_right", "y_left"]) {
        const field_ele = document.getElementById(field);
        if (!params.hasOwnProperty(field) || !params[field]) {
            field_ele.value = DEFAULT_VALUES[field] || "";
            continue;
        }
        document.getElementById(field).value = params[field];
    }

    for (const field in DEFAULT_VALUES) {
        params[field] = params[field] ?? DEFAULT_VALUES[field];
    }

    update_subareas();

    document.querySelectorAll("input[name='subarea']").forEach(ele => ele.checked = false);
    if (params.hasOwnProperty("subarea")) {
        params.subarea.split(",").forEach(subarea => {
            document.getElementById(`subarea_${subarea}`).checked = true;
        });
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

//////////////////////////////

window.addEventListener("popstate", (event) => {
    update_chart_with_query();
});


if (window.location.search) {
    update_chart_with_query();
} else {
    document.getElementById("area").value = '臺北市';
    document.getElementById("age_max").value = '3';
    click_gen_btn();
}
