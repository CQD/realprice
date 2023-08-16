<?php

include __DIR__ . '/../vendor/autoload.php';

$map = [
    '/' => 'Index',
    '/api/option' => 'ApiOption',
    '/api/data' => 'ApiData',
    '404' => 'FourOFour',
];

define("ASSET_VERSION", asset_ver());

$path = $_SERVER['REQUEST_URI'];
if (false !== $pos = strpos($path, '?')) {
    $path = substr($path, 0, $pos);
}

$clazz = sprintf(
    '\Q\RealPrice\Controller\%s',
    $map[$path] ?? $map['404']
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

    $base = "";
    foreach (["GAE_DEPLOYMENT_ID", "GAE_VERSION", "GAE_INSTANCE"] as $key) {
        if (defined($key)) {
            $base = constant($key);
        }
        if ($base) {
            break;
        }
    }

    $base = $base ?? date("Ymd");
    $hash = md5($base);

    return substr($hash, 2, 12);
}
