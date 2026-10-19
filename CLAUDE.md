# 實價登錄房價趨勢網站

台灣實價登錄房價趨勢網站，目標是「理解區域的房價長期變化」。從政府公開資料下載實價登錄資料，離線清洗、彙總成靜態 JSON，以 PHP 輸出頁面殼與摘要文字，圖表由前端 ECharts 繪製。

## 目錄結構

```
├── bin/              # 資料處理腳本 (不部署)
│   ├── stats_load.py          # 原始 JSON → data/raw.duckdb
│   └── stats_build.py         # 清洗 + 彙總 → public/data/{build_id}/ + build/site.json
├── build/            # 建置產出物 (線上服務需要)
│   └── site.php               # 區域清單、各區摘要、資料版本 (由 site.json 轉成)
├── data/             # 原始資料 (僅建置時需要，不部署)
│   ├── {季度}/       # 如 113S1, 113S2... 或日期如 20240101
│   └── raw.duckdb    # stats_load.py 產生
├── public/           # Web 根目錄
│   ├── index.php     # 入口點與路由
│   ├── index_functions.php    # 短網址解析 (有 unit test)
│   ├── data/{build_id}/       # 統計資料 (stats_build.py 產生，保留最近兩版)
│   ├── geo/towns.json         # 鄉鎮市區界線 TopoJSON
│   └── s/            # 靜態資源 (app.css, app.js)
├── src/
│   ├── Site.php      # 讀 build/site.php：區域查詢、短網址組成、數字格式
│   └── Controller/   # 控制器
├── view/             # 視圖模板
└── tests/            # 測試
```

## 頁面 (三層)

| 路徑 | 內容 |
|------|------|
| `/` | 全台：指標列、鄉鎮市區地圖、單價 vs 漲幅散佈圖、多區比較 (`?cmp=臺北市大安區,...`)、縣市／鄉鎮市區排行 |
| `/{縣市}[/{類型}]` | 縣市：指標列、屋齡分層走勢、品質調整指數、縣內地圖、各區小圖、各區排行 |
| `/{縣市}/{區域}[/{類型}]` | 鄉鎮市區：指標列、屋齡分層走勢、品質調整指數、租金、同縣市各區小圖 |
| `/sitemap.xml` | Sitemap |

- 嘉義市、新竹市的資料只有一個同名區：`/新竹市/新竹市` 301 到 `/新竹市`，縣市頁改用鄉鎮市區頁的版型
- 鄉鎮市區選單依近 12 個月成交量排序 (`site.php` 的 `districts`)
- 類型在網址用中文 (`住宅大樓`)，程式與資料檔用 slug (`tower`, `mansion`, `apartment`, `house`, `suite`)
- 類型只影響屋齡分層走勢與單價、筆數、預售價差；品質調整指數、漲幅、租金一律是全部住宅類型

### 短網址

- 路徑驗證在 `resolve_short_url()`，解析在 `short_url_params()`，都在 `public/index_functions.php`
- 第二段歧義判斷：查表判斷是區域名還是類型名（兩者不重疊）
- `台` 字自動 301 轉址到 `臺`（如 `/台北市` → `/臺北市`）
- 舊格式 `/?area=&subarea=&type=` 由 `legacy_query_url()` 301 到短網址；舊的 parking、age_min 等條件直接丟掉

## 資料流

### 建置流程 (Makefile)

```
- bin/getall.sh         從政府網站下載 XML 資料包
- bin/process.sh        解壓縮、XML 轉 JSON (用 bin/xml2json) → data/{季度}/*.json.gz
- bin/stats_load.py     a (成屋)、b (預售)、c (租賃) 全部載入 data/raw.duckdb
- bin/stats_build.py    清洗、彙總、品質調整指數、租金 → public/data/{build_id}/、build/site.json
- bin/json2php.php      build/site.json → build/site.php
```

Python 套件裝在 repo 內的 `venv/`（Makefile 的 `PY_VENV`，`make` 會依 `requirements.txt` 自動建立），不要裝到系統。

### 清洗規則 (stats_build.py)

- 只取 房地／房地+車位、單一建物、住宅用途、五種建物類型
- 排除：特殊關係交易、政府配售／眷改／國宅、預售屋交屋登記 (備註含「預售屋」，價格是簽約價且跟預售檔重複)、預售解約
- 整批配售常常沒寫備註（例：北投區段徵收配售、萬華德昌街）：同門牌同月 ≥20 筆且單價 < 同區同年同屋齡層 6 成 → 排除
- 單價一律扣車位：車位沒單獨標價時用同區同年車位單價中位數估
- 屋齡分層：預售 / 新成屋 ≤3 年 / 中古 3–20 年 / 老屋 >20 年
- 月份索引 0 = 2012-08。最後幾個月申報不完整，依筆數自動決定 `end`（筆數 ≥ 前 12 個月中位數 80% 的最後一個月）；前端線圖只畫到 `end`
- 品質調整指數：ln(單價) = 組別固定效果 + 區域×月固定效果，組別 = 區 × 類型 × 屋齡級距 × 坪數級距；5／10 年漲幅 = 近 12 個月 vs 5／10 年前同期 (12 個月移動平均)

### 輸出檔案

- `public/data/{build_id}/regions.json`：各區摘要 + `spark`（12 個月移動平均的 log 指數，小圖與比較圖用）
- `public/data/{build_id}/c/{縣市代碼}/{類型slug|all}.json`：該縣市與轄下各區的月資料 (n, p25, p50, p75)；`all.json` 另含 `index` 與 `rent`
- `build/site.json` → `build/site.php`：PHP 用，含 `area`/`type`（格式沿用 resolve_short_url）、`districts`、`regions`（含 `by_type` 各類型近 12 個月摘要）

### 常用指令

```bash
make download    # 下載最新資料
make build       # 完整建置 (下載 + 統計資料)
make stats       # 只重建統計資料
make server      # 啟動開發伺服器 (localhost:8080)
make deploy      # 部署到 Google App Engine
make test        # 執行測試
```

實務上每 2–3 週手動 `make build && make deploy`。

## Controller 說明

- `ControllerBase`：路徑解析 + 模板輸出
- `Index`：三層頁面，準備標題、摘要文字、指標列、選單等給 `view/index.php`
- `ApiSitemap`：sitemap.xml
- 2026-10 改版前的 SQLite + `/api/data` 任意條件查詢已移除，需要參考時看 git 歷史

## 前端

- `public/s/app.js`：ECharts 繪圖 (jsDelivr CDN)；地圖用 topojson-client
- `window.PAGE` 由 PHP 輸出：`level`、`id`、`county`、`type`、`data` (統計資料路徑) 等
- 指標列、摘要文字由 PHP 直接輸出 (SEO、無 JS 也看得到)，圖表由 JS 繪製
- 每張圖只有一個 Y 軸；成交量是另一張共用時間軸的長條圖
- 只有淺色主題
- 手機 (≤600px，JS 的 `NARROW` 跟 app.css 斷點一致)：線圖不畫線尾標籤、右邊界縮小、年份標籤每 4 年一個；指標列與類型 chips 改成橫向捲動單列，避免主圖表被擠到很下面。圖表卡片 (`.panel`) 貼齊螢幕左右、圖表再比內距往外延伸 12px，靠左的圖例／色階用 `edgeLeft()` 縮回來對齊文字。注意 Android Firefox 調大字型會讓 CSS viewport 變窄 (從截圖推算約 270px)
- 地圖、散佈圖色階：貴／漲多 = 強暖色，便宜／漲少 = 淡冷色；租金收益率用飽和的冷色 (低) → 暖色 (高)，避免淡色跟樣本不足的灰色混淆；預售價差用藍 (預售較便宜) ↔ 紅 (預售較貴)

## 部署與快取

- Google App Engine (PHP 8.5)，設定在 `app.yaml`
- `/data` 帶 build_id 永不變動，`expiration: 365d`；`/geo` 30 天
- Cloudflare 在前面，但預設只快取 js/css；HTML、`.json` 都是 `cf-cache-status: DYNAMIC`，要另外設 Cache Rule（`/data/*` 長快取、HTML 1 天 + deploy 後 purge）

## 本地開發

本地開發的時候用 `php -S 0.0.0.0:8080 -t public/` 啟動 PHP server（需要先跑過 `make stats`）
需注意內容大幅度依賴 javascript 來顯示

本地環境有這些 CLI 指令可供使用，可視情況選擇使用
- `jq`: 強大的 json 處理工具
- `ag`: silver searcher，在大批搜尋檔案內容時比 grep 更好用的檔案文字搜尋工具
- `awk`: 強大的文字 stream processor

如果修改過程發現有對未來修改有幫助的知識，或是 CLAUDE.md 內的知識已經過時，需要一並更新 CLAUDE.md
