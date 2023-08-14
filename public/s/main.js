const chart_canvas = document.getElementById("chart");
const ctx = chart_canvas.getContext("2d");

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
            }
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
    params = params || chart_params();

    let ordered_params = Object.keys(params).sort().reduce(
        (obj, key) => {
            obj[key] = params[key];
            return obj;
        },
        {}
    );
    params = ordered_params;

    const url = "/api/data?" + new URLSearchParams(params);

    ctx.clearRect(0, 0, chart_canvas.width, chart_canvas.height);
    ctx.font = "bold 20px sans-serif";
    ctx.fillText("載入中...", 30, 30);

    fetch(url)
    .then(resp => resp.json())
    .then(resp => {
        update_chart_with_data(resp.data, params);

        const footer_msg = document.getElementById("footer_msg");
        footer_msg.innerHTML = "";

        for (const key in resp) {
            if (key == "data") continue;
            footer_msg.appendChild(document.createElement("hr"));
            for (const txt of [key, resp[key]]) {
                const ele = document.createElement("div");
                ele.textContent = txt;
                footer_msg.appendChild(ele);
            }
        }
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
        [y_left.value, y_left.textContent],
        [y_right.value, y_right.textContent],
    ];

    for (const [field, field_name] of f) {
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
        }
        datasets.push(dataset);
    }

    datasets[0].type = "line";
    datasets[0].yAxisID = "y";
    datasets[1].type = "bar";
    datasets[1].yAxisID = "yr";

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
        "y_left": "unit_price_avg",
        "y_right": "cnt",
        "parking": "0",
    };
    for (const field of ["age_min", "age_max", "area", "parking", "type", "y_right", "y_left"]) {
        const field_ele = document.getElementById(field);
        if (!params.hasOwnProperty(field)) {
            field_ele.value = DEFAULT_VALUES[field] || "";
            continue;
        }
        document.getElementById(field).value = params[field];
    }

    update_subareas();

    document.querySelectorAll("input[name='subarea']").forEach(ele => ele.checked = false);
    if (params.hasOwnProperty("subarea")) {
        params.subarea.split(",").forEach(subarea => {
            document.getElementById(`subarea_${subarea}`).checked = true;
        });
    }

    params.v = ASSET_VERSION || (new Date().getDate());
    for (const field of ["y_right", "y_left"]) {
        if (params.hasOwnProperty(field)) {
            delete params[field];
        }
    }

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

(async () => {
    await fetch("/build/option.json")
    .then(resp => resp.json())
    .then(data => {
        for (let key in data) options[key] = data[key];

        const area = document.getElementById("area");

        area.querySelectorAll("option").forEach(ele => ele.remove());

        for (let area_name of Object.keys(data["area"])) {
            const opt = document.createElement("option");
            opt.textContent = area_name;
            opt.value = area_name;
            opt.id = `area_${area_name}`;
            area.append(opt);
        }

        area.value = Object.keys(data["area"])[0];
        update_subareas();

        const types = document.getElementById("type");
        for (let type of data["type"]) {
            const opt = document.createElement("option");
            opt.textContent = type;
            opt.value = type;
            types.append(opt);
        }
    });

    if (window.location.search) {
        update_chart_with_query();
    }

})();
