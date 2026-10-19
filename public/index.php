<?php

use Q\RealPrice\Site;

include __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/index_functions.php';

$map = [
    '/' => 'Index',
    '/api/option' => 'ApiOption',
    '/api/data' => 'ApiData',
    '/sitemap.xml' => 'ApiSitemap',
    '/sitemap' => 'ApiSitemap',
    '404' => 'FourOFour',
];

define("ASSET_VERSION", asset_ver());

$path = $_SERVER['REQUEST_URI'];
if (false !== $pos = strpos($path, '?')) {
    $path = substr($path, 0, $pos);
}

// 頁面路由的參數：縣市、鄉鎮市區、類型 (全國頁三者皆為 null)
$ROUTE = ['area' => null, 'subarea' => null, 'type' => null];

// 舊格式 ?area=... 一律轉到短網址
if ($path === '/' && isset($_GET['area'])) {
    $redirect = legacy_query_url($_GET, Site::data());
    if ($redirect) {
        header("Location: $redirect", true, 301);
        exit;
    }
}

// 短網址路由：驗證每一段路徑都是合法的縣市/區域/類型
$controller = $map[$path] ?? null;
if (!$controller && !str_starts_with($path, '/api/') && !str_starts_with($path, '/s/') && !str_starts_with($path, '/data/') && $path !== '/favicon.png' && $path !== '/og.png') {
    $options = Site::data();
    $result = resolve_short_url($path, $options);

    if ($result['valid']) {
        $ROUTE = short_url_params($path, $options);

        // 只有一個同名區的縣市 (嘉義市、新竹市)：/新竹市/新竹市 → /新竹市
        if ($ROUTE['subarea'] === $ROUTE['area']) {
            $result['redirect'] = build_short_url($ROUTE['area'], null, $ROUTE['type']);
        }
    }

    if ($result['redirect']) {
        // 舊版的篩選 query (parking、age_min…) 新版不支援，轉址時一併拿掉
        header("Location: {$result['redirect']}", true, 301);
        exit;
    }

    if ($result['valid']) {
        $controller = 'Index';
    }
}
$controller = $controller ?? $map['404'];

$clazz = sprintf(
    '\Q\RealPrice\Controller\%s',
    $controller
);


$PAGE = [];
(new $clazz)->run();

/////////////////////////////////////////////////////////

function e($str, $type = 'html')
{
    $funcs = [
        'html' => 'htmlspecialchars',
        'js' => 'json_encode',
    ];

    return $funcs[$type]($str);
}

function asset_ver() {
    if (($_SERVER["HTTP_HOST"] ?? "") === "localhost:8080") {
        return date("YmdHis");
    }

    $base = getenv("GAE_VERSION") ?: getenv("GAE_INSTANCE") ?: date("Ymd");
    $hash = md5($base);
    return substr($hash, 2, 12);
}
