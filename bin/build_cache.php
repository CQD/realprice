#!/usr/bin/env php
<?php

require __DIR__ . "/../vendor/autoload.php";

date_default_timezone_set('Asia/Taipei');

// 只有直接執行才跑邏輯
if (__FILE__ !== realpath($_SERVER['SCRIPT_NAME'])) {
    return;
}

$cache_dir = __DIR__ . '/../build/cache';

// 清除舊 cache
say("清除舊 cache...");
if (is_dir($cache_dir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cache_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
}

// 設定查詢環境
$_SERVER['REQUEST_URI'] = '/api/data';
$_SERVER['HTTP_HOST'] = 'build';

// 固定的 cache 條件
$_GET['type'] = '住宅大樓';
$_GET['parking'] = '0';
$_GET['age_max'] = '3';

$options = require __DIR__ . '/../build/option.php';

// 產生每個縣市的 cache
foreach ($options['county_ids'] as $county_name => $county_id) {
    say("處理縣市: {$county_name} (ID: {$county_id})");

    $county_dir = "{$cache_dir}/{$county_id}";
    if (!is_dir($county_dir)) {
        mkdir($county_dir, 0755, true);
    }

    // 縣市整體
    $_GET['area'] = $county_name;
    unset($_GET['subarea']);

    $controller = new \Q\RealPrice\Controller\ApiData();
    ob_start();
    $controller->run();
    $json = ob_get_clean();

    file_put_contents("{$county_dir}/county.json", $json);
    say("  -> county.json");

    // 該縣市下的每個鄉鎮市區
    $districts = $options['district_ids'][$county_id] ?? [];
    foreach ($districts as $district_name => $district_id) {
        $_GET['area'] = $county_name;
        $_GET['subarea'] = $district_name;

        $controller = new \Q\RealPrice\Controller\ApiData();
        ob_start();
        $controller->run();
        $json = ob_get_clean();

        file_put_contents("{$county_dir}/{$district_id}.json", $json);
    }
    say("  -> " . count($districts) . " 個鄉鎮市區");
}

say("Cache 建立完成");

function say(string $msg) {
    fputs(STDERR, sprintf("\033[32m[%s]\033[m %s\n", date('Y-m-d H:i:s'), $msg));
}
