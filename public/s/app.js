"use strict";
(() => {
const P = window.PAGE;
const $ = s => document.querySelector(s);
const esc = s => String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const css = n => getComputedStyle(document.documentElement).getPropertyValue(n).trim();
const wan = v => v == null ? "—" : (v / 10000).toFixed(1);
const pct = (v, d = 0, sign = true) => v == null ? "—" : (sign && v > 0 ? "+" : "") + (v * 100).toFixed(d) + "%";
const int = v => v == null ? "—" : Math.round(v).toLocaleString("zh-TW");

const BANDS = [
  { k: "new", name: "新成屋", c: "--s1" },
  { k: "pre", name: "預售屋", c: "--s2" },
  { k: "mid", name: "中古屋", c: "--s3" },
  { k: "old", name: "老屋", c: "--s4" },
];
const SLOTS = ["--s1", "--s2", "--s3", "--s4"];
// 淡冷 → 強暖：便宜／漲少 = 淡冷色，貴／漲多 = 強暖色
const WARM = ["#e1ebf2", "#eef0e4", "#fbe1ab", "#f7b264", "#ec7b3a", "#d2472a", "#9c1f1c"];
const DIVERGE = ["#3f7fc2", "#a9c7e6", "#f2f0eb", "#f3a86b", "#cf4528"];
// 冷 → 暖，兩端都是飽和色：避免大片淡色跟「樣本不足」的灰色分不清楚
const COOL_WARM = ["#2b6cb0", "#3d93c4", "#6fbfa8", "#e3c14a", "#f0883a", "#d2472a", "#9c1f1c"];
const METRIC = {
  chg10: { name: "10 年漲幅", fmt: v => pct(v), ramp: () => WARM, lo: "漲少", hi: "漲多" },
  chg5: { name: "5 年漲幅", fmt: v => pct(v), ramp: () => WARM, lo: "漲少", hi: "漲多" },
  up: { name: "近一年單價中位數", fmt: v => wan(v) + " 萬/坪", ramp: () => WARM, lo: "便宜", hi: "貴" },
  pre_prem: { name: "預售 vs 新成屋價差", fmt: v => pct(v), ramp: () => DIVERGE, div: true, lo: "預售較便宜", hi: "預售較貴" },
  yield: { name: "毛租金收益率", fmt: v => pct(v, 2, false), ramp: () => COOL_WARM, lo: "收益率低", hi: "收益率高" },
};

let M, END, LAST, SUM = {}, LIST = [];

// ---------- 資料 ----------
const bundles = {};
const loadBundle = (c, t) => bundles[c + "/" + t] || (bundles[c + "/" + t] = fetch(`${P.data}c/${c}/${t}.json`).then(r => r.ok ? r.json() : {}));
// 回傳 {s: {band: {n,p25,p50,p75}}, allS, index, rent}；類型不是 all 時，指數與租金仍取自 all
async function getRegion(id, type) {
  const c = id.split("-")[0];
  const [all, typed] = await Promise.all([loadBundle(c, "all"), type === "all" ? null : loadBundle(c, type)]);
  const base = all[id] || { s: {} };
  return { s: (type === "all" ? base.s : typed[id]?.s) || {}, allS: base.s, index: base.index, rent: base.rent || [] };
}

function fullName(id) {
  const r = SUM[id];
  if (!r) return id;
  if (r.level < 2) return r.name;
  const p = SUM[r.parent];
  return p.name === r.name ? r.name : p.name + r.name;
}
// 跟 Site::url() 相同的短網址規則
function url(id, type = "all") {
  const r = SUM[id], parts = [];
  if (r && r.level >= 1) {
    const county = r.level === 1 ? r : SUM[r.parent];
    parts.push(county.name);
    if (r.level === 2 && r.name !== county.name) parts.push(r.name);
  }
  if (type !== "all") parts.push(P.types[type]);
  return "/" + parts.map(encodeURIComponent).join("/");
}
const districtsOf = cid => {
  const kids = LIST.filter(r => r.parent === cid);
  return kids.length > 1 ? kids : [];
};

// ---------- 計算 ----------
// n 加權移動平均；視窗內樣本不足 minN 視為空
function smooth(vals, ns, w = 12, minN = 5) {
  return vals.map((_, i) => {
    let s = 0, n = 0;
    for (let j = Math.max(0, i - w + 1); j <= i; j++) {
      if (vals[j] == null || !ns[j]) continue;
      s += vals[j] * ns[j]; n += ns[j];
    }
    return n >= minN ? s / n : null;
  });
}
const yearOf = i => +M[i].slice(0, 4);
function baseMean(vals, year) {
  const xs = vals.filter((v, i) => v != null && yearOf(i) === year);
  return xs.length ? xs.reduce((a, b) => a + b, 0) / xs.length : null;
}
function toIndex(logs, year) {
  if (!logs) return null;
  const b0 = baseMean(logs, year);
  return b0 == null ? null : logs.map(b => b == null ? null : Math.exp(b - b0) * 100);
}
// 申報中的月份樣本偏少且有偏誤，線圖只畫到 END
const cutEnd = arr => arr && arr.map((v, i) => i > END ? null : v);
const sparkIndex = (id, year) => cutEnd(toIndex(SUM[id]?.spark, year));
function wmean(arr, ns, lo, hi) {
  let s = 0, n = 0;
  for (let i = lo; i <= hi; i++) if (arr[i] != null && ns[i]) { s += arr[i] * ns[i]; n += ns[i]; }
  return n ? s / n : null;
}
const sumN = (s, lo, hi) => s ? s.n.slice(lo, hi + 1).reduce((a, b) => a + (b || 0), 0) : 0;
function quantile(xs, p) { const s = [...xs].sort((a, b) => a - b); return s[Math.max(0, Math.min(s.length - 1, Math.floor(p * (s.length - 1))))]; }
// 顏色範圍：裁掉頭尾 3%；價差用 0 為中心
function metricRange(rows, metric) {
  const vals = rows.map(r => r[metric]).filter(v => v != null);
  if (!vals.length) return [0, 1];
  let min = quantile(vals, .03), max = quantile(vals, .97);
  if (METRIC[metric].div) { const a = Math.max(Math.abs(min), Math.abs(max)); min = -a; max = a; }
  return min === max ? [min - 1e-6, max + 1e-6] : [min, max];
}
const clamp = (v, [min, max]) => v == null ? null : Math.min(max, Math.max(min, v));
// 跟地圖同一套色階，給表格用
function rampColor(v, [min, max], ramp) {
  const t = Math.min(1, Math.max(0, (v - min) / (max - min || 1))) * (ramp.length - 1);
  const i = Math.min(ramp.length - 2, Math.floor(t)), f = t - i;
  const h = s => [1, 3, 5].map(k => parseInt(s.slice(k, k + 2), 16));
  const a = h(ramp[i]), b = h(ramp[i + 1]);
  return a.map((x, k) => Math.round(x + (b[k] - x) * f));
}
const textOn = ([r, g, b]) => (r * 299 + g * 587 + b * 114) / 1000 < 140 ? "#fff" : "var(--ink)";

// ---------- 共用圖表樣式 ----------
const charts = {};
// 手機版 (跟 app.css 的 600px 斷點一致)：線尾標籤會吃掉右側 64px 以上，改靠圖例辨識，把寬度留給圖
const NARROW = matchMedia("(max-width: 600px)");
const gridRight = r => NARROW.matches ? 20 : r;
function chart(id) {
  if (!charts[id]) charts[id] = echarts.init(document.getElementById(id), null, { renderer: "canvas" });
  return charts[id];
}
function baseOpt() {
  const ink3 = css("--ink-3"), line = css("--line");
  return {
    animation: false, backgroundColor: "transparent",
    textStyle: { fontFamily: css("--font-body"), color: css("--ink-2") },
    tooltip: {
      trigger: "axis", confine: true,
      backgroundColor: css("--surface"), borderColor: line, textStyle: { color: css("--ink"), fontSize: 12 },
      axisPointer: { type: "line", lineStyle: { color: ink3, width: 1 } },
    },
    _axis: {
      axisLine: { lineStyle: { color: line } }, axisTick: { show: false },
      axisLabel: { color: ink3, fontSize: 11 }, splitLine: { lineStyle: { color: css("--line-faint") } },
    },
  };
}
function mAxis(o, showLabels = true) {
  return {
    type: "category", data: M, boundaryGap: true, ...o._axis, splitLine: { show: false },
    axisLabel: { ...o._axis.axisLabel, show: showLabels, interval: (i, v) => v.endsWith("-01") && +v.slice(0, 4) % (NARROW.matches ? 4 : 2) === 0, formatter: v => v.slice(0, 4) },
  };
}
// 月份 category 軸 + 疊在同一個 grid 的隱形數值軸 (0 ~ 月份數)
// 數值軸上的整數剛好是月份格子的邊界，「申報中」灰底掛在這條軸上才能對齊格子；category 軸收到小數會四捨五入
function mAxes(o, showLabels = true) {
  return [mAxis(o, showLabels), { type: "value", min: 0, max: M.length, show: false, axisPointer: { show: false, triggerTooltip: false } }];
}
const lineKey = color => `<i style="display:inline-block;width:12px;height:3px;border-radius:2px;background:${color};vertical-align:3px;margin-right:6px"></i>`;
function tipRows(params, fmt) {
  const head = esc(params[0].axisValueLabel || params[0].name);
  const rows = params.filter(p => p.seriesName && !p.seriesName.startsWith("_") && p.value != null)
    .map(p => `<div style="display:flex;justify-content:space-between;gap:16px"><span>${lineKey(p.color)}<span style="color:${css("--ink-2")}">${esc(p.seriesName)}</span></span><b style="font-family:${css("--font-data")}">${fmt(p.value, p)}</b></div>`);
  return `<div style="font-size:12px;color:${css("--ink-3")};margin-bottom:4px">${head}</div>` + (rows.join("") || "<span>無資料</span>");
}
function itemTip(fmtBody) {
  return { trigger: "item", confine: true, backgroundColor: css("--surface"), borderColor: css("--line"), textStyle: { color: css("--ink"), fontSize: 12 }, formatter: fmtBody };
}
// 「申報中」的月份畫灰底：從最後一個完整月份的右緣到圖的右緣；圖的 xAxis 要用 mAxes()
function provisionalSeries(showLabel = true) {
  if (END >= LAST) return [];
  return [{
    name: "_prov", type: "line", data: [], xAxisIndex: 1, silent: true,
    markArea: {
      silent: true, itemStyle: { color: "rgba(120,134,137,.25)" },
      // 標籤放底部，避免跟右上方的線尾標籤重疊
      label: { show: showLabel, position: "insideBottom", color: css("--ink-2"), fontSize: 11, formatter: "申報中" },
      data: [[{ xAxis: END + 1 }, { xAxis: LAST + 1 }]],
    },
  }];
}
function lineSeries(name, data, color, extra = {}) {
  const s = {
    name, type: "line", data, symbol: "none", connectNulls: true,
    lineStyle: { width: 2, color }, itemStyle: { color },
    endLabel: { show: true, formatter: name, color: css("--ink-2"), fontSize: 11, distance: 6 },
    labelLayout: { moveOverlap: "shiftY" },
    emphasis: { disabled: true }, ...extra,
  };
  if (NARROW.matches) s.endLabel = { show: false };
  return s;
}
const hundredLine = () => ({ silent: true, symbol: "none", label: { show: false }, lineStyle: { color: css("--ink-3"), type: "solid", width: 1, opacity: .5 }, data: [{ yAxis: 100 }] });
function fillBaseYears(sel, onChange) {
  const years = [...new Set(M.map(m => +m.slice(0, 4)))].filter(y => y >= 2013 && y <= yearOf(END));
  sel.innerHTML = years.map(y => `<option ${y === 2016 ? "selected" : ""}>${y}</option>`).join("");
  sel.onchange = () => onChange(+sel.value);
}

// ======================= 區域走勢 (縣市、鄉鎮市區頁) =======================
const A = { smooth: true, iqr: "new", base: 2016 };
let REGION = null;

async function renderRegion() {
  REGION = await getRegion(P.id, P.type);
  $("#f-smooth").onchange = e => { A.smooth = e.target.checked; renderBands(); renderIndex(); };
  $("#f-iqr").onchange = e => { A.iqr = e.target.value; renderBands(); };
  fillBaseYears($("#f-base"), y => { A.base = y; renderIndex(); });
  renderBands();
  renderYearTable();
  renderIndex();
  if ($("#c-rent")) renderRent();
}

function renderBands() {
  const S = k => REGION.s[k];
  // 單月樣本 < 5 筆不畫，避免小樣本的極端值
  const val = (s, f) => !s ? M.map(() => null) : cutEnd(A.smooth ? smooth(s[f], s.n, 12, 10) : s[f].map((v, i) => s.n[i] >= 5 ? v : null));
  const o = baseOpt();
  const series = [];
  if (A.iqr && S(A.iqr)) {
    const s = S(A.iqr), lo = val(s, "p25"), hi = val(s, "p75");
    const color = css(BANDS.find(b => b.k === A.iqr).c);
    series.push({ name: "_lo", type: "line", data: lo, stack: "iqr", symbol: "none", lineStyle: { opacity: 0 }, connectNulls: false, silent: true, emphasis: { disabled: true } });
    series.push({ name: "_band", type: "line", data: hi.map((v, i) => v == null || lo[i] == null ? null : v - lo[i]), stack: "iqr", symbol: "none", lineStyle: { opacity: 0 }, areaStyle: { color, opacity: 0.14 }, connectNulls: false, silent: true, emphasis: { disabled: true } });
  }
  for (const b of BANDS) {
    const s = S(b.k);
    // 樣本不足的月份留白，不要用直線硬接
    if (s) series.push(lineSeries(b.name, val(s, "p50"), css(b.c), { connectNulls: false, lineStyle: { width: A.smooth ? 2 : 1.5, color: css(b.c) } }));
  }
  const vis = series.filter(x => x.name !== "_band").flatMap(x => x.data).filter(v => v != null);
  const vMin = vis.length ? Math.min(...vis) : 0, vMax = vis.length ? Math.max(...vis) : 1;
  const rawStep = Math.max(1, (vMax - vMin * 0.9) / 5), mag = 10 ** Math.floor(Math.log10(rawStep));
  const yStep = [1, 2, 2.5, 5, 10].map(m => m * mag).find(x => x >= rawStep);
  const yMin = Math.floor(vMin * 0.9 / yStep) * yStep;
  const bandMax = Math.max(vMax, ...series.filter(x => x.name === "_band").flatMap(x => x.data.map((v, i) => v == null ? -Infinity : v + series[0].data[i])));
  const yMax = Math.ceil(bandMax * 1.02 / yStep) * yStep;
  chart("c-price").setOption({
    ...o, grid: { left: 44, right: gridRight(64), top: NARROW.matches ? 52 : 36, bottom: 8 },
    legend: { top: 0, left: 0, icon: "roundRect", itemWidth: 14, itemHeight: 3, textStyle: { color: css("--ink-2"), fontSize: 12 }, data: BANDS.filter(b => S(b.k)).map(b => b.name) },
    tooltip: { ...o.tooltip, formatter: p => tipRows(p, v => wan(v) + " 萬/坪") },
    xAxis: mAxes(o, false),
    yAxis: { type: "value", ...o._axis, min: yMin, max: yMax, interval: yStep, axisLabel: { ...o._axis.axisLabel, formatter: v => v / 10000 } },
    series: [...series, ...provisionalSeries()],
    graphic: vis.length ? [] : [{ type: "text", left: "center", top: "middle", style: { text: "樣本不足", fill: css("--ink-3"), fontSize: 13 } }],
  }, true);

  const ex = S("all"), pr = S("pre");
  chart("c-vol").setOption({
    ...o, grid: { left: 44, right: gridRight(64), top: 8, bottom: 22 },
    tooltip: { ...o.tooltip, axisPointer: { type: "shadow", shadowStyle: { color: "rgba(120,134,137,.12)" } }, formatter: p => tipRows(p, v => int(v) + " 筆") },
    xAxis: mAxes(o),
    yAxis: { type: "value", ...o._axis, splitNumber: 2, axisLabel: { ...o._axis.axisLabel, formatter: v => v >= 1000 ? v / 1000 + "k" : v } },
    series: [
      { name: "成屋", type: "bar", stack: "v", data: ex ? ex.n : [], itemStyle: { color: css("--muted-mark") }, barCategoryGap: "15%" },
      { name: "預售屋", type: "bar", stack: "v", data: pr ? pr.n : [], itemStyle: { color: css("--s2") } },
      ...provisionalSeries(false),
    ],
  }, true);
  echarts.connect([chart("c-price"), chart("c-vol")]);
}

function renderYearTable() {
  const S = k => REGION.s[k];
  const yrs = [...new Set(M.map(m => m.slice(0, 4)))];
  const bandsHere = BANDS.filter(b => S(b.k));
  $("#t-year").innerHTML = `<thead><tr><th>年</th>${bandsHere.map(b => `<th>${b.name}（萬/坪）</th>`).join("")}<th>成屋筆數</th></tr></thead><tbody>` +
    yrs.map(y => {
      const idx = M.map((m, i) => m.startsWith(y) ? i : -1).filter(i => i >= 0);
      const lo = idx[0], hi = idx[idx.length - 1];
      return `<tr><td>${y}</td>${bandsHere.map(b => `<td class="num">${wan(wmean(S(b.k).p50, S(b.k).n, lo, hi))}</td>`).join("")}<td class="num">${int(sumN(S("all"), lo, hi))}</td></tr>`;
    }).join("") + "</tbody>";
}

function renderIndex() {
  const o = baseOpt();
  const beta = REGION.index ? (A.smooth ? smooth(REGION.index.beta, REGION.index.n, 12, 20) : REGION.index.beta) : null;
  const ix = cutEnd(toIndex(beta, A.base));
  const allS = REGION.allS.all;
  let raw = null;
  if (allS) {
    const v = A.smooth ? smooth(allS.p50, allS.n) : allS.p50, b0 = baseMean(v, A.base);
    raw = b0 ? cutEnd(v.map(x => x == null ? null : x / b0 * 100)) : null;
  }
  chart("c-index").setOption({
    ...o, grid: { left: 40, right: gridRight(64), top: 36, bottom: 24 },
    legend: { top: 0, left: 0, itemWidth: 14, itemHeight: 3, textStyle: { color: css("--ink-2"), fontSize: 12 }, data: [{ name: "品質調整指數", icon: "roundRect" }, { name: "單純中位數", icon: "path://M0,0h4v2h-4z M7,0h4v2h-4z M14,0h4v2h-4z" }] },
    tooltip: { ...o.tooltip, formatter: p => tipRows(p, v => v.toFixed(1)) },
    xAxis: mAxes(o),
    yAxis: { type: "value", ...o._axis, scale: true },
    series: [
      lineSeries("品質調整指數", ix || [], css("--s1"), { lineStyle: { width: A.smooth ? 2 : 1.5, color: css("--s1") }, endLabel: { show: true, formatter: "調整後", color: css("--ink-2"), fontSize: 11, distance: 6 }, markLine: hundredLine() }),
      lineSeries("單純中位數", raw || [], css("--ink-3"), { lineStyle: { width: 1.25, type: [4, 3], color: css("--ink-3") }, endLabel: { show: true, formatter: "中位數", color: css("--ink-3"), fontSize: 11, distance: 6 } }),
      ...provisionalSeries(),
    ],
    graphic: ix ? [] : [{ type: "text", left: "center", top: "middle", style: { text: "樣本不足，無法估計指數", fill: css("--ink-3"), fontSize: 13 } }],
  }, true);
}

function renderRent() {
  const rent = REGION.rent.filter(x => x.n >= 15);
  const o = baseOpt();
  const years = rent.map(x => String(x.y));
  const opt = (name, data, fmt, color, axisFmt) => ({
    ...o, grid: { left: 44, right: 24, top: 28, bottom: 22 },
    title: { text: name, left: 0, top: 0, textStyle: { fontSize: 12, fontWeight: 500, color: css("--ink-2") } },
    tooltip: { ...o.tooltip, formatter: p => tipRows(p, fmt) },
    xAxis: { type: "category", data: years, boundaryGap: false, ...o._axis, splitLine: { show: false } },
    yAxis: { type: "value", ...o._axis, scale: true, splitNumber: 3, axisLabel: { ...o._axis.axisLabel, formatter: axisFmt } },
    series: [lineSeries(name, data, color, { symbol: "circle", symbolSize: 5, endLabel: { show: false } })],
    graphic: rent.length ? [] : [{ type: "text", left: "center", top: "middle", style: { text: "租賃樣本不足", fill: css("--ink-3"), fontSize: 13 } }],
  });
  chart("c-rent").setOption(opt("月租金中位數（元/坪）", rent.map(x => x.rent), v => int(v) + " 元/坪", css("--s1"), v => v), true);
  chart("c-yield").setOption(opt("毛租金收益率", rent.map(x => x.yield), v => (v * 100).toFixed(2) + "%", css("--s3"), v => (v * 100).toFixed(1) + "%"), true);
}

// 同縣市各區的小圖，所有小圖共用刻度
function renderMultiples(cid) {
  const box = $("#multiples");
  if (!box) return;
  const kids = districtsOf(cid);
  const sortSel = $("#f-sort");
  const draw = () => {
    const key = sortSel.value;
    const items = kids.map(k => ({ k, ix: sparkIndex(k.id, 2016) }));
    let lo = Infinity, hi = -Infinity;
    for (const it of items) if (it.ix) for (const v of it.ix) if (v != null) { lo = Math.min(lo, v); hi = Math.max(hi, v); }
    hi = Math.min(hi, 300); lo = Math.max(lo, 60);
    items.sort((a, b) => (b.k[key] ?? -1e9) - (a.k[key] ?? -1e9));
    const W = 160, H = 40;
    const yOf = v => (H - 2 - (Math.min(Math.max(v, lo), hi) - lo) / (hi - lo) * (H - 4)).toFixed(1);
    box.innerHTML = items.map(({ k, ix }) => {
      let path = "", pen = false;
      if (ix) ix.forEach((v, i) => {
        if (v == null) { pen = false; return; }
        path += (pen ? "L" : "M") + (i / (M.length - 1) * W).toFixed(1) + " " + yOf(v); pen = true;
      });
      const g = key === "up" ? `${wan(k.up)} 萬` : key === "n12" ? `${int(k.n12)} 筆` : pct(k[key]);
      return `<a class="mcard" href="${url(k.id, P.type)}"${k.id === P.id ? ' aria-current="page"' : ""}>
        <span class="row"><span class="nm">${esc(k.name)}</span><span class="g">${g}</span></span>
        <svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="none" aria-hidden="true">
          <line x1="0" x2="${W}" y1="${yOf(100)}" y2="${yOf(100)}" stroke="var(--line)" stroke-width="1" vector-effect="non-scaling-stroke"/>
          ${path ? `<path d="${path}" fill="none" stroke="var(--s1)" stroke-width="1.75" vector-effect="non-scaling-stroke"/>` : `<text x="${W / 2}" y="${H / 2 + 4}" text-anchor="middle" font-size="11" fill="var(--ink-3)">樣本不足</text>`}
        </svg>
        <span class="meta">${wan(k.up)} 萬/坪・10年 ${pct(k.chg10)}</span>
      </a>`;
    }).join("");
  };
  sortSel.onchange = draw;
  draw();
}

// ======================= 地圖 (全台、縣市頁) =======================
let GEO = null;
const CODE2RID = {};
async function loadGeo() {
  if (GEO) return GEO;
  const topo = await fetch("/geo/towns.json").then(r => r.json());
  GEO = topojson.feature(topo, topo.objects.towns);
  for (const f of GEO.features) {
    const p = f.properties, c = p.COUNTYID.toLowerCase();
    // 嘉義市、新竹市的資料只有一個同名區
    CODE2RID[p.TOWNCODE] = (c === "i" || c === "o") ? `${c}-${p.COUNTYNAME.replace(/^台/, "臺")}` : `${c}-${p.TOWNNAME}`;
    p.county = c; p.name = p.TOWNCODE;
  }
  return GEO;
}
// 水平色階：兩端文字 + 色條，色條長度依圖寬調整，避免窄螢幕右端被切掉；太窄時兩端只留數字
let measureCtx;
function textWidth(s) {
  measureCtx ||= document.createElement("canvas").getContext("2d");
  measureCtx.font = `11px ${css("--font-body")}`;
  return measureCtx.measureText(s).width;
}
function vmLayout(c, m, range) {
  const lo = m.fmt(range[0]), hi = m.fmt(range[1]);
  // 色條跟兩端文字各隔 10px (visualMap 預設 itemGap)，另留 8px 餘裕
  const barLen = text => c.getWidth() - textWidth(text[0]) - textWidth(text[1]) - 28;
  let text = [`${hi} ${m.hi}`, `${m.lo} ${lo}`];
  if (barLen(text) < 60) text = [hi, lo];
  return { text, itemHeight: Math.max(40, Math.min(160, barLen(text))) };
}
function visualMap(c, m, range, extra) {
  c._vm = [m, range]; // resize 時重算色條長度
  return {
    type: "continuous", min: range[0], max: range[1], calculable: false, orient: "horizontal", itemWidth: 10,
    ...vmLayout(c, m, range), textStyle: { color: css("--ink-3"), fontSize: 11 },
    inRange: { color: m.ramp() }, ...extra,
  };
}
// scope: "TW" 或縣市代碼
async function renderMap(scope, metric) {
  await loadGeo();
  const m = METRIC[metric], o = baseOpt();
  const inScope = f => scope === "TW" ? !["w", "z"].includes(f.properties.county) : f.properties.county === scope;
  const mapName = "tw-" + scope;
  if (!echarts.getMap(mapName)) echarts.registerMap(mapName, { type: "FeatureCollection", features: GEO.features.filter(inScope) });
  const rows = LIST.filter(r => r.level === 2 && (scope === "TW" || r.parent === scope));
  const range = metricRange(rows, metric);
  const data = GEO.features.filter(inScope).map(f => {
    const rid = CODE2RID[f.properties.TOWNCODE], v = SUM[rid]?.[metric];
    return { name: f.properties.TOWNCODE, value: clamp(v, range), raw: v ?? null, rid };
  });
  $("#map-title").textContent = scope === "TW" ? m.name : `各區地圖：${m.name}`;
  const ink3 = css("--ink-3"), dataFont = css("--font-data");
  const c = chart("c-map");
  c.setOption({
    ...o,
    tooltip: itemTip(p => {
      const r = SUM[p.data?.rid];
      if (!r) return "";
      return `<b>${esc(fullName(r.id))}</b><br>${m.name}：<b style="font-family:${dataFont}">${p.data.raw == null ? "樣本不足" : m.fmt(p.data.raw)}</b><br><span style="color:${ink3}">近一年 ${wan(r.up)} 萬/坪・${int(r.n12)} 筆</span>`;
    }),
    visualMap: visualMap(c, m, range, { left: 0, bottom: 0 }),
    series: [{
      type: "map", map: mapName, roam: true, scaleLimit: { min: 1, max: 12 }, data,
      ...(scope === "TW" ? { boundingCoords: [[119.3, 25.35], [122.05, 21.85]] } : {}),
      itemStyle: { areaColor: css("--missing"), borderColor: "#ffffff", borderWidth: .6 },
      emphasis: { label: { show: false }, itemStyle: { areaColor: null, borderColor: css("--ink"), borderWidth: 1.5 } },
      select: { disabled: true },
    }],
  }, true);
  c.off("click");
  c.on("click", p => { if (SUM[p.data?.rid]) location.href = url(p.data.rid, P.type); });
}

// ======================= 全台頁 =======================
const B = { metric: "chg10", base: 2016, cmp: [], scope: "county", sortKey: "chg10", sortDir: -1, q: "" };
const DEFAULT_CMP = ["a-大安區", "f-板橋區", "j-竹北市", "b-西屯區"];

function readCmpFromUrl() {
  const q = new URLSearchParams(location.search).get("cmp");
  if (q === null) return DEFAULT_CMP.filter(id => SUM[id]);
  const byName = Object.fromEntries(LIST.map(r => [fullName(r.id), r.id]));
  return q.split(",").map(n => byName[n]).filter(Boolean).slice(0, 4);
}
function writeCmpToUrl() {
  const u = new URL(location.href);
  const ids = B.cmp.filter(Boolean);
  if (ids.join() === DEFAULT_CMP.join()) u.searchParams.delete("cmp"); else u.searchParams.set("cmp", ids.map(fullName).join(","));
  history.replaceState(null, "", u.pathname + u.search);
}
function addCmp(id) {
  if (!SUM[id] || B.cmp.includes(id)) return;
  const free = [0, 1, 2, 3].find(i => !B.cmp[i]);
  if (free === undefined) { B.cmp.shift(); B.cmp.push(id); } else B.cmp[free] = id;
  writeCmpToUrl(); renderCmp(); renderScatter(); renderRank();
}

async function renderNational() {
  B.cmp = readCmpFromUrl();
  $("#f-metric").onchange = e => { B.metric = e.target.value; B.sortKey = B.metric; B.sortDir = -1; renderMap("TW", B.metric); renderScatter(); renderRank(); };
  fillBaseYears($("#f-base"), y => { B.base = y; renderCmp(); });
  document.querySelectorAll(".seg button").forEach(b => b.onclick = () => {
    B.scope = b.dataset.scope;
    document.querySelectorAll(".seg button").forEach(x => x.setAttribute("aria-pressed", x === b));
    renderRank();
  });
  $("#f-q").oninput = e => { B.q = e.target.value.trim(); renderRank(); };
  $("#cmp-chips").onclick = e => {
    const i = e.target.dataset.i;
    if (i === undefined) return;
    B.cmp[+i] = null;
    while (B.cmp.length && !B.cmp[B.cmp.length - 1]) B.cmp.pop();
    writeCmpToUrl(); renderCmp(); renderScatter(); renderRank();
  };
  renderScatter(); renderCmp(); renderRank();
  await renderMap("TW", B.metric);
}

function renderScatter() {
  const o = baseOpt(), m = METRIC[B.metric];
  const yk = B.metric === "chg5" ? "chg5" : "chg10";
  const yName = yk === "chg5" ? "5 年漲幅" : "10 年漲幅";
  $("#scatter-sub").textContent = `每個點是一個鄉鎮市區。橫軸是近一年單價（對數刻度），縱軸是${yName}（品質調整），點的大小代表近一年成交量，顏色跟地圖一樣是「${m.name}」。點一下加入比較。`;
  const pts = LIST.filter(r => r.level === 2 && r.up && r[yk] != null && r.n12 >= 20);
  const range = metricRange(LIST.filter(r => r.level === 2), B.metric);
  const mk = r => ({ value: [r.up / 10000, r[yk] * 100, clamp(r[B.metric], range)], rid: r.id, symbolSize: Math.max(5, Math.min(28, Math.sqrt(r.n12) * .55)) });
  const ink3 = css("--ink-3"), ink = css("--ink"), dataFont = css("--font-data");
  const c = chart("c-scatter");
  c.setOption({
    ...o, grid: { left: 48, right: 20, top: 16, bottom: 72 },
    tooltip: itemTip(p => {
      const r = SUM[p.data.rid];
      return `<b>${esc(fullName(r.id))}</b><br>近一年 <b style="font-family:${dataFont}">${wan(r.up)} 萬/坪</b><br>${yName} <b style="font-family:${dataFont}">${pct(r[yk])}</b>` +
        (B.metric !== yk && B.metric !== "up" ? `<br>${m.name} <b style="font-family:${dataFont}">${m.fmt(r[B.metric])}</b>` : "") +
        `<br><span style="color:${ink3}">${int(r.n12)} 筆・點一下加入比較</span>`;
    }),
    visualMap: visualMap(c, m, range, { seriesIndex: 0, dimension: 2, left: "center", bottom: 0 }),
    xAxis: { type: "log", logBase: 2, ...o._axis, name: "近一年單價（萬/坪）", nameLocation: "middle", nameGap: 22, nameTextStyle: { color: ink3, fontSize: 11 }, min: 4, max: 256 },
    yAxis: { type: "value", ...o._axis, axisLabel: { ...o._axis.axisLabel, formatter: v => v + "%" } },
    series: [
      { name: "鄉鎮市區", type: "scatter", data: pts.filter(r => r[B.metric] != null).map(mk), itemStyle: { opacity: .9, borderColor: "rgba(16,32,34,.35)", borderWidth: .75 }, emphasis: { scale: 1.3 } },
      { name: "無資料", type: "scatter", data: pts.filter(r => r[B.metric] == null).map(mk), itemStyle: { color: css("--missing"), borderColor: "rgba(16,32,34,.35)", borderWidth: .75 } },
      { name: "比較中", type: "scatter", data: pts.filter(r => B.cmp.includes(r.id)).map(r => ({ ...mk(r), label: { show: true, formatter: SUM[r.id].name, position: "right", color: ink, fontSize: 11, fontWeight: 500 } })), itemStyle: { color: "transparent", borderColor: ink, borderWidth: 2 }, z: 5 },
    ],
  }, true);
  c.off("click");
  c.on("click", p => p.data?.rid && addCmp(p.data.rid));
}

function renderCmp() {
  $("#cmp-chips").innerHTML = B.cmp.map((id, i) => id ? `<span class="cmp-chip"><i style="background:var(${SLOTS[i]})"></i><a href="${url(id)}">${esc(fullName(id))}</a><button type="button" aria-label="移除 ${esc(fullName(id))}" data-i="${i}">×</button></span>` : "").join("") || `<span class="muted small">尚未選擇區域</span>`;
  const o = baseOpt();
  const series = B.cmp.map((id, i) => id ? lineSeries(fullName(id), sparkIndex(id, B.base) || [], css(SLOTS[i])) : null).filter(Boolean);
  if (series.length) series[0].markLine = hundredLine();
  chart("c-cmp").setOption({
    ...o, grid: { left: 40, right: gridRight(110), top: 16, bottom: 24 },
    tooltip: { ...o.tooltip, formatter: p => tipRows(p, v => v.toFixed(1)) },
    xAxis: mAxes(o),
    yAxis: { type: "value", ...o._axis, scale: true, name: `${B.base} = 100`, nameTextStyle: { color: css("--ink-3"), fontSize: 11, align: "left" } },
    series: [...series, ...provisionalSeries()],
  }, true);
}

// ======================= 排行表 (全台頁：縣市/鄉鎮市區；縣市頁：轄下各區) =======================
const COLS = [
  { k: "name", t: "區域" }, { k: "up", t: "近一年單價（萬/坪）", f: wan }, { k: "chg5", t: "5 年漲幅", f: v => pct(v) },
  { k: "chg10", t: "10 年漲幅", f: v => pct(v) }, { k: "pre_prem", t: "預售價差", f: v => pct(v) }, { k: "yield", t: "租金收益率", f: v => pct(v, 2, false) },
  { k: "n12", t: "近一年筆數", f: int },
];
function renderRank() {
  const table = $("#t-rank");
  if (!table) return;
  const national = P.level === 0;
  let rows = national
    ? LIST.filter(r => (B.scope === "county" ? r.level === 1 : r.level === 2) && r.n12 > 0)
    : districtsOf(P.id);
  if (national) $("#table-title").textContent = B.scope === "county" ? "縣市排行" : "鄉鎮市區排行";
  if (B.q) rows = rows.filter(r => fullName(r.id).includes(B.q));
  const k = B.sortKey;
  rows.sort((a, b) => k === "name" ? fullName(a.id).localeCompare(fullName(b.id), "zh-TW") * -B.sortDir : ((a[k] ?? -1e9) - (b[k] ?? -1e9)) * B.sortDir);
  const range = metricRange(rows, B.metric), ramp = METRIC[B.metric].ramp();
  const cols = national ? [...COLS, { k: "_add", t: "" }] : COLS;
  const cell = (r, c) => {
    if (c.k === "name") return `<td><a href="${url(r.id, P.type)}">${esc(national ? fullName(r.id) : r.name)}</a></td>`;
    if (c.k === "_add") return `<td><button type="button" class="add" data-id="${r.id}" ${B.cmp.includes(r.id) ? "disabled" : ""}>${B.cmp.includes(r.id) ? "比較中" : "加入比較"}</button></td>`;
    if (c.k === B.metric && r[c.k] != null) { const rgb = rampColor(r[c.k], range, ramp); return `<td class="num heat"><span style="background:rgb(${rgb.join(",")});color:${textOn(rgb)}">${c.f(r[c.k])}</span></td>`; }
    return `<td class="num">${c.f(r[c.k])}</td>`;
  };
  table.innerHTML = `<thead><tr>${cols.map(c => `<th${c.k === "_add" ? "" : ` data-k="${c.k}"`}${c.k === k ? ` aria-sort="${B.sortDir < 0 ? "descending" : "ascending"}"` : ""}>${c.t}</th>`).join("")}</tr></thead><tbody>` +
    rows.map(r => `<tr>${cols.map(c => cell(r, c)).join("")}</tr>`).join("") + "</tbody>";
}
document.addEventListener("click", e => {
  const th = e.target.closest("#t-rank th[data-k]");
  if (th) { const k = th.dataset.k; B.sortDir = B.sortKey === k ? -B.sortDir : -1; B.sortKey = k; renderRank(); return; }
  const b = e.target.closest("#t-rank button.add");
  if (b) addCmp(b.dataset.id);
});

// ======================= 初始化 =======================
function setupNav() {
  const county = $("#f-county"), district = $("#f-district");
  county.onchange = () => { location.href = url(county.value, county.value === "TW" ? "all" : P.type); };
  if (district) district.onchange = () => { location.href = url(district.value, P.type); };
}

(async function init() {
  setupNav();
  const meta = await fetch(P.data + "regions.json").then(r => r.json());
  M = meta.m; END = meta.end; LAST = meta.last; LIST = meta.regions;
  for (const r of LIST) SUM[r.id] = r;

  if (P.level === 0) {
    await renderNational();
  } else {
    const county = P.county;
    await renderRegion();
    renderMultiples(county);
    if ($("#c-map")) {
      const sel = $("#f-metric");
      sel.onchange = () => { B.metric = sel.value; B.sortKey = sel.value; B.sortDir = -1; renderMap(county, B.metric); renderRank(); };
      renderRank();
      await renderMap(county, B.metric);
    }
  }
  addEventListener("resize", () => Object.values(charts).forEach(c => {
    c.resize();
    if (c._vm) c.setOption({ visualMap: vmLayout(c, ...c._vm) });
  }));
  // 跨過手機斷點時重畫有線尾標籤的圖
  NARROW.addEventListener("change", () => {
    if (P.level === 0) renderCmp();
    else { renderBands(); renderIndex(); }
  });
})();
})();
