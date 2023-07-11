#!/usr/bin/env php
<?php

date_default_timezone_set('Asia/Taipei');

$counties = [
    '臺北市' => 'a',
    '臺中市' => 'b',
    '基隆市' => 'c',
    '臺南市' => 'd',
    '高雄市' => 'e',
    '新北市' => 'f',
    '宜蘭縣' => 'g',
    '桃園縣' => 'h',
    '嘉義市' => 'i',
    '新竹縣' => 'j',
    '苗栗縣' => 'k',
    '南投縣' => 'm',
    '彰化縣' => 'n',
    '新竹市' => 'o',
    '雲林縣' => 'p',
    '嘉義縣' => 'q',
    '屏東縣' => 't',
    '花蓮縣' => 'u',
    '臺東縣' => 'v',
    '金門縣' => 'w',
    '澎湖縣' => 'x',
    '連江縣' => 'z',
];

$categories = [
    '不動產買賣' => 'a',
    '預售屋買賣' => 'b',
    '不動產租賃' => 'c',
];

$locNameOverrides = [
    '金&#27;fa4b鄉' => '金峰鄉',
    'fa72埔鄉' => '鹽埔鄉',
];

$data_file = __DIR__  . '/../build/transactions.sqlite3';

$db = new PDO("sqlite::memory:");

///////////////////////////////////////////

// 只有自己被執行才跑邏輯跑
// 不然大概是 unit test，只定義 function 不跑邏輯
if (__FILE__ === realpath($_SERVER['SCRIPT_NAME'])) {
    global $db;

    // TODO 檢查 jq 跟 gzip 是否已安裝

    @unlink($data_file);
    $db = new PDO("sqlite:{$data_file}");
    $db->exec('PRAGMA journal_mode=OFF');
    $db->exec(file_get_contents(__DIR__ . '/create_tables.sql'));

    ini_set('memory_limit', '512M');
    foreach ($counties as $county => $id) {
        process($id, $county);
    }
}


///////////////////////////////////////////

function process($countyId, $countyName)
{
    global $db;

    say("==== 處理 {$countyName} ===");

    $cmd = sprintf(
        'find "%s" -name "**%s_lvr_land_a.xml.json.gz" | xargs -I{} cat {} | gzip -d | grep -v "交易標的\":\"土地\"" | jq \'[
            .["鄉鎮市區"],
            .["交易年月日"],
            .["建物型態"],
            .["建築完成年月"],
            .["建物移轉總面積平方公尺"],
            .["總價元"],
            .["車位移轉總面積平方公尺"]
        ]\' -Mc | grep "住宅大樓\|華廈\|透天\|公寓\|套房"',
        __DIR__ . '/../data',
        $countyId
    );

    $row = 0;
    $insert = 0;
    $fp = popen($cmd, 'r');
    $db->exec('BEGIN TRANSACTION');
    $stmt = $db->prepare(
        "INSERT INTO house_transactions "
        ."(county, district, transaction_date, type, build_date, area, price, parking_area)"
        ."VALUES(?, ?, ?, ?, ?, ?, ?, ?)"
    );
    while ($line = fgets($fp)) {
        $row++;
        [
            $dist,
            $transactionDate,
            $type,
            $buildingDate,
            $area,
            $price,
            $parkingArea,
        ] = json_decode($line, true);

        if (!$dist) {
            say("沒有鄉鎮/區... " . trim($line));
            continue;
        }

        $dist = $locNameOverrides[$dist] ?? $dist;

        $now = time();

        $transactionDate = normalizeDate($transactionDate);
        if (!$transactionDate) {
            say("交易日格式異常... " . trim($line));
            continue;
        }
        if ($transactionDate > $now) {
            say("交易日在未來... " . trim($line));
            continue;
        }

        $buildingDate = normalizeDate($buildingDate) ?: 0;

        if (0 >= $area + $parkingArea) {
            say("沒有坪數... " . trim($line));
            continue;
        }

        $pos = mb_strpos($type, '(');
        if (false !== $pos) {
            $type = mb_substr($type, 0, $pos); # type 拔掉 ( 之後的東西
        }

        $stmt->execute([
            $countyName,
            $dist,
            $transactionDate,
            $type,
            $buildingDate,
            $area * 0.3025,
            $price,
            $parkingArea * 0.3025,
        ]);
        $insert++;
    }

    $dropped = $row - $insert;
    say("已讀取 {$row} 筆資料，寫入 {$insert} 筆資料，拋棄 {$dropped} 筆資料");
    $db->exec('COMMIT TRANSACTION');
    say("Transaction comitted");

}

function normalizeDate(string $orig)
{
    $in = (int) $orig;
    if (!$in || $in < 100) {
        return null;
    }

    if ($in < 1000) {
        $in = $in * 10000 + 101 + 19110000;
    } elseif ($in < 10000) {
        $in = $in * 100 + 1 + 19110000;
    } else {
        $in = $in + 19110000;
    }

    $y = floor($in / 10000);
    $m = floor($in / 100) % 100;
    $d = $in % 100;
    $time = strtotime("$y-$m-$d");
    return $time;
}

///////////////////////////////////////////

function say(string $msg) {
    fputs(STDERR, sprintf("\033[32m[%s]\033[m %s\n", date('Y-m-d H:i:s'), $msg));
}
