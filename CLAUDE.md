# 實價登錄房價趨勢網站

台灣實價登錄房價趨勢圖表網站，從政府公開資料拉取實價登錄資料，存入 SQLite，以 PHP 提供網站服務呈現圖表。

## 目錄結構

```
├── bin/              # 資料處理腳本
├── build/            # 建置產出物 (線上服務需要)
│   ├── transactions.sqlite3   # 主資料庫
│   └── option.php             # 選項快取 (縣市/區域/類型)
├── data/             # 原始資料 (僅建置時需要，線上服務不用)
│   └── {季度}/       # 如 113S1, 113S2... 或日期如 20240101
├── public/           # Web 根目錄
│   ├── index.php     # 入口點與路由
│   ├── build/        # 靜態資料 (option.json)
│   └── s/            # 靜態資源 (main.css, main.js)
├── src/              # PHP 原始碼
│   ├── Controller/   # 控制器
│   └── Id/           # ID 轉換函式
├── view/             # 視圖模板
└── tests/            # 測試
```

## 路由 (public/index.php)

| 路徑 | Controller | 說明 |
|------|------------|------|
| `/` | Index | 首頁，渲染圖表介面 |
| `/{縣市}` | Index | 短網址 |
| `/{縣市}/{區域}` | Index | 短網址 |
| `/{縣市}/{類型}` | Index | 短網址 |
| `/{縣市}/{區域}/{類型}` | Index | 短網址 |
| `/api/option` | ApiOption | 取得選項 (縣市、區域、建物類型) |
| `/api/data` | ApiData | 查詢交易資料 |
| `/sitemap.xml` | ApiSitemap | Sitemap |

### 短網址

- 路徑式 URL: `/臺北市/大安區/住宅大樓` 取代舊的 `?area=臺北市&subarea=大安區&type=住宅大樓`
- 路徑驗證邏輯在 `public/index_functions.php` 的 `resolve_short_url()`，有對應 unit test
- 第二段歧義判斷：查表判斷是區域名還是類型名（兩者不重疊）
- `台` 字自動 301 轉址到 `臺`（如 `/台北市` → `/臺北市`）
- 舊格式 `?area=` URL 仍支援，JS 會 `replaceState` 為新格式
- 非預設值的篩選條件才出現在 query parameter（parking, age_min, age_max, y_left, y_right）

## 資料流

### 建置流程 (Makefile)

```
- bin/getall.sh   從政府網站下載 XML 資料包
- bin/process.sh   解壓縮、XML 轉 JSON (用 bin/xml2json)
- data/{季度}/   儲存 .json.gz 檔案
- bin/build_sqlite.php   讀取所有 JSON，寫入 SQLite
- build/transactions.sqlite3
- bin/build_option.php   從 DB 產生選項 JSON
- public/build/option.json + build/option.php
```

### 常用指令

```bash
make download    # 下載最新資料
make build       # 完整建置 (下載 + SQLite + option)
make server      # 啟動開發伺服器 (localhost:8080)
make deploy      # 部署到 Google App Engine
make test        # 執行測試
```

## 資料庫 Schema (SQLite)

```sql
-- 縣市
counties (id, name)

-- 鄉鎮市區
districts (id, county_id, name)

-- 建物類型
types (id, name)  -- 0:住宅大樓, 1:華廈, 2:透天厝, 3:公寓, 4:套房

-- 交易記錄
house_transactions (
    county_id,
    district_id,
    transaction_date,  -- 日數 (非 timestamp，為節省空間)
    type_id,
    age_day,           -- 屋齡天數
    area,              -- 坪數 * 1000 (整數)
    price,             -- 總價
    parking_area,      -- 車位坪數 * 1000
    parking_price      -- 車位價格
)
```

**注意**: `transaction_date` 和 `age_day` 以「日數」儲存而非 Unix timestamp，用以減小資料庫大小。計算方式: `(timestamp + 8*3600) / 86400`

## Controller 說明

### ControllerBase
- 初始化 SQLite 連線 (`build/transactions.sqlite3`)
- 註冊自訂 aggregate 函數: `median`, `p25`, `p75`, `parking_unit_price`

### ApiData
查詢參數:
- `area`: 縣市 (逗號分隔)
- `subarea`: 鄉鎮市區 (逗號分隔)
- `type`: 建物類型
- `parking`: 1=有車位, -1=無車位, 0=不限
- `age_min`, `age_max`: 屋齡範圍

回傳按月份分組的統計資料:
- `cnt`: 交易筆數
- `unit_price_median`: 中位數單價 (含車位)
- `no_parking_unit_price_median`: 中位數單價 (排除車位)
- `price_median`: 中位數房價
- `area_median`: 中位數坪數

## 前端

- Chart.js 繪製圖表
- 雙 Y 軸顯示不同指標
- `public/s/main.js` 處理 API 呼叫與圖表渲染
- 前端預設值定義在 `main.js` 的 `DEFAULT_VALUES`（type=住宅大樓, parking=0, age_max=3 等）
- Server 端只負責渲染頁面殼，實際查詢由 JS 打 `/api/data` API

### Cache 機制

- `ApiData` 在 `getCacheFile()` 中判斷是否命中預建 cache（`build/cache/` 下的 JSON 檔）
- Cache 條件：`type=住宅大樓`, `parking=0`, `age_max=3`, `age_min` 未指定, 單一縣市
- 前端預設值必須與 cache 條件對齊，避免預設查詢打到 SQLite

## 部署

Google App Engine (PHP 8.3)，設定在 `app.yaml`

## 本地開發

本地開發的時候用 `php -S 0.0.0.0:8080 -t public/` 啟動 PHP server
需注意內容大幅度依賴 javascript 來顯示

本地環境有這些 CLI 指令可供使用，可視情況選擇使用
- `jq`: 強大的 json 處理工具
- `ag`: silver searcher，在大批搜尋檔案內容時比 grep 更好用的檔案文字搜尋工具
- `awk`: 強大的文字 stream processor
- `sqlite`: sqlite db CLI

如果修改過程發現有對未來修改有幫助的知識，或是 CLAUDE.md 內的知識已經過時，需要一並更新 CLAUDE.md
