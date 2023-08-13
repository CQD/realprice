const ctx = document.getElementById("chart");

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

const chart = new Chart(ctx, chart_config);

function update_chart() {
    const params = chart_params();
    const url = "/api/data?" + new URLSearchParams(params);

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
    })
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

    chart.data.datasets = datasets;
    chart.options.plugins.title.text = title;
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

    const subarea_eles = document.querySelectorAll("input[name=subarea]:checked");
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