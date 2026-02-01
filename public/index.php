<?php

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

// 短網址路由：驗證每一段路徑都是合法的縣市/區域/類型
$controller = $map[$path] ?? null;
if (!$controller && !str_starts_with($path, '/api/') && !str_starts_with($path, '/s/') && $path !== '/favicon.png' && $path !== '/og.png') {
    $options = require __DIR__ . '/../build/option.php';
    $result = resolve_short_url($path, $options);

    if ($result['redirect']) {
        $redirect = $result['redirect'];
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        if ($qs) $redirect .= '?' . $qs;
        header("Location: $redirect", true, 301);
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
