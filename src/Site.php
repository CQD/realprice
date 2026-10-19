<?php

namespace Q\RealPrice;

/**
 * 讀取 bin/stats_build.py 產生的 build/site.php：區域清單、各區摘要、資料版本
 */
class Site
{
    private static ?array $data = null;

    public static function data(): array
    {
        return self::$data ??= require __DIR__ . '/../build/site.php';
    }

    /** 測試用 */
    public static function setData(array $data): void
    {
        self::$data = $data;
    }

    public static function region(string $id): ?array
    {
        return self::data()['regions'][$id] ?? null;
    }

    public static function countyId(string $name): ?string
    {
        $id = array_search($name, self::data()['counties'], true);
        return $id === false ? null : $id;
    }

    /** 回傳區域 ID：TW / 縣市代碼 / 縣市代碼-鄉鎮市區；找不到回傳 null */
    public static function regionId(?string $county, ?string $district = null): ?string
    {
        if (!$county) return 'TW';
        $cid = self::countyId($county);
        if (!$cid) return null;
        if (!$district) return $cid;
        $id = "{$cid}-{$district}";
        return isset(self::data()['regions'][$id]) ? $id : null;
    }

    public static function typeSlug(?string $name): string
    {
        if (!$name) return 'all';
        $slug = array_search($name, self::data()['types'], true);
        return $slug === false ? 'all' : $slug;
    }

    public static function typeName(string $slug): string
    {
        return self::data()['types'][$slug] ?? '全部住宅';
    }

    /** 縣市名 + 區名；只有一個區且同名的縣市 (嘉義市、新竹市) 不重複 */
    public static function fullName(string $id): string
    {
        $r = self::region($id);
        if (!$r) return $id;
        if ($r['level'] < 2) return $r['name'];
        $county = self::region($r['parent'])['name'];
        return $county === $r['name'] ? $county : $county . $r['name'];
    }

    /** 鄉鎮市區 ID 清單，依近 12 個月成交量排序；只有一個同名區的縣市回傳空陣列 */
    public static function districtsOf(string $cid): array
    {
        $ids = self::data()['districts'][$cid] ?? [];
        if (count($ids) === 1 && self::region($ids[0])['name'] === self::region($cid)['name']) {
            return [];
        }
        return $ids;
    }

    public static function url(string $id, string $typeSlug = 'all'): string
    {
        $r = self::region($id);
        $parts = [];
        if ($r && $r['level'] >= 1) {
            $county = $r['level'] === 1 ? $r : self::region($r['parent']);
            $parts[] = $county['name'];
            if ($r['level'] === 2 && $r['name'] !== $county['name']) $parts[] = $r['name'];
        }
        if ($typeSlug !== 'all') $parts[] = self::typeName($typeSlug);
        return '/' . implode('/', array_map('rawurlencode', $parts));
    }

    // ---------- 數字格式 ----------

    public static function wan(?float $v): string
    {
        return $v === null ? '—' : number_format($v / 10000, 1);
    }

    public static function pct(?float $v, int $digits = 0, bool $sign = true): string
    {
        if ($v === null) return '—';
        return ($sign && $v > 0 ? '+' : '') . number_format($v * 100, $digits) . '%';
    }

    public static function int(?float $v): string
    {
        return $v === null ? '—' : number_format($v);
    }
}
