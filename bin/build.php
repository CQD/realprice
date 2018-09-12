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

///////////////////////////////////////////

// 只有自己被執行才跑邏輯跑
// 不然大概是 unit test，只定義 function 不跑邏輯
if (__FILE__ === realpath($_SERVER['SCRIPT_NAME'])) {
    // TODO 檢查 jq 跟 gzip 是否已安裝

    ini_set('memory_limit', '512M');
    foreach ($counties as $county => $id) {
        process($id, $county);
    }
}


///////////////////////////////////////////

function process($countyId, $countyName)
{
    say("==== 處理 {$countyName} ===");

    $now = time();
    $totalPrices = [];
    $unitPrices = [];

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

    $ageRanges = [
        1  => '一年以下',
        5  => '一～五年',
        10 => '五～十年',
        15 => '十～十五年',
        20 => '十五～二十年',
        30 => '二十～三十年',
        40 => '三十～四十年',
        100000 => '四十年以上',
    ];

    say('撈資料');
    $row = 0;
    $fp = popen($cmd, 'r');
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

        if (false !== strpos($type, '有電梯')) {
            $type = '電梯大樓';
        }

        $transactionDate = normalizeDate($transactionDate);
        $transactionYM = substr($transactionDate, 0, 6);
        if ($transactionDate > 21000101) {
            say("交易日異常... " . trim($line));
            continue;
        }

        $buildingDate = normalizeDate($buildingDate);
        if (!$buildingDate) {
            $buildingAgeRange = '未知';
        } else {
            $buildingAge = (strtotime($transactionDate) - strtotime($buildingDate)) / 86400 / 365;
            foreach ($ageRanges as $max => $name) {
                if ($buildingAge < $max) {
                    $buildingAgeRange = $name;
                    break;
                }
            }
        }

        $hasParking = ($parkingArea > 0) ? '有車位' : '無車位';

        $totalPrices[$countyName][$hasParking][$buildingAgeRange][$type][$transactionYM][] = $price;
        $totalPrices[$dist][$hasParking][$buildingAgeRange][$type][$transactionYM][] = $price;

        if (0 == $area + $parkingArea) {
            say("建坪為0... " . trim($line));
            continue;
        }
        $unitPrice = $price / ($area + $parkingArea);
        $unitPrice *= 400 / 121; // 每平方公尺單價換算成每坪單價
        $unitPrices[$countyName][$hasParking][$buildingAgeRange][$type][$transactionYM][] = $unitPrice;
        $unitPrices[$dist][$hasParking][$buildingAgeRange][$type][$transactionYM][] = $unitPrice;
    }

    say("已讀取 {$row} 筆資料。開始排序");
    sortPrices($totalPrices);
    sortPrices($unitPrices);

    say("開始合併資料");
    $result = mergePrices($totalPrices, $unitPrices);

    say("輸出 build 完的結果");
    output($countyId, $countyName, $result);


    say(sprintf('完成。記憶體 peak: %.3fM; real peak: %.3fM', memory_get_peak_usage() / (1024 * 1024), memory_get_peak_usage(true) / (1024 * 1024)));
}

function output($countyId, $countyName, $result)
{
    $dir = __DIR__ . "/../build/{$countyId}";
    @mkdir($dir);

    static $map = [];
    static $locationNames = [];
    static $dates = ['startYM' => '201207', 'endYM' => '201809'];

    $locationNames[$countyName][] = $countyName;
    foreach ($result as $areaName => $a) {
        foreach ($a as $hasParking => $b) {
            foreach ($b as $buildingAgeRange => $c) {
                foreach ($c as $type => $d) {
                    $key = "{$areaName}-{$hasParking}-{$buildingAgeRange}-{$type}";
                    $fileName = sha1($key);
                    $map[$areaName][$hasParking][$buildingAgeRange][$type] = "{$countyId}/{$fileName}.php";
                    file_put_contents("{$dir}/{$fileName}.php", "<?php return " . preg_replace('@\n *@', ' ', var_export($d, true)) . ";");
                    $locationNames[$countyName][] = $areaName;

                    $dates['endYM'] = (string) max($dates['endYM'], max(array_keys($d)));
                }
            }
        }
    }


    $locationNames[$countyName] = array_values(array_filter((array_unique($locationNames[$countyName]))));
    file_put_contents("$dir/../_map.php", "<?php return " . var_export($map, true) . ";");
    file_put_contents("$dir/../_names.php", "<?php return " . var_export($locationNames, true) . ";");
    file_put_contents("$dir/../_dates.php", "<?php return " . var_export($dates, true) . ";");
}

function mergePrices(array $totalPrices, array $unitPrices) : array
{
    $result = [];

    foreach ($totalPrices as $areaName => $a) {
        foreach ($a as $hasParking => $b) {
            foreach ($b as $buildingAgeRange => $c) {
                foreach ($c as $type => $d) {
                    foreach ($d as $transactionYM => $e) {
                        $result[$areaName][$hasParking][$buildingAgeRange][$type][$transactionYM] = [
                            't' => fiveMajor($e),
                            'u'  => fiveMajor($unitPrices[$areaName][$hasParking][$buildingAgeRange][$type][$transactionYM] ?? []),
                            'c' => count($e),
                            'tt' => array_sum($e),
                        ];
                    }
                }
            }
        }
    }

    return $result;
}

/**
 * 計算五個指標
 *
 * - top   5%
 * - top  50%
 * - 中位數
 * - btn  50%
 * - btn   5%
 */
function fiveMajor(array $list) : ?array
{
    if (!$list) return null;

    $length = count($list);
    $halfLen = floor($length / 2);

    if ($length % 2 > 0) { // odd length
        $median = $list[$halfLen];
    } else { // even length
        $median = ($list[$halfLen] + $list[$halfLen - 1]) / 2;
    }

    $fiftyLength = max(1, $halfLen);
    $fiveLength = max(1, floor($length / 20));

    return [
        'b5' => round(array_sum(array_slice($list, 0, $fiveLength)) / $fiveLength),
        'b50' => round(array_sum(array_slice($list, 0, $fiftyLength)) / $fiftyLength),
        'm' => round($median),
        't50' => round(array_sum(array_slice($list, $length - $fiftyLength , $fiftyLength)) / $fiftyLength),
        't5' => round(array_sum(array_slice($list, $length - $fiveLength , $fiveLength)) / $fiveLength),
    ];
}

function sortPrices(array &$prices)
{
    foreach ($prices as $areaName => $a) {
        foreach ($a as $hasParking => $b) {
            foreach ($b as $buildingAgeRange => $c) {
                foreach ($c as $type => $d) {
                    foreach ($d as $transactionYM => $e) {
                        sort($prices[$areaName][$hasParking][$buildingAgeRange][$type][$transactionYM]);
                    }
                    ksort($prices[$areaName][$hasParking][$buildingAgeRange][$type]);
                }
            }
        }
    }
}

function normalizeDate(string $orig)
{
    $in = (int) $orig;
    if (!$in) {
        return null;
    }

    if ($in < 1000) {
        $in = $in * 10000 + 101 + 19110000;
    } elseif ($in < 10000) {
        $in = $in * 100 + 1 + 19110000;
    } else {
        $in = $in + 19110000;
    }

    return $in;
}

///////////////////////////////////////////

function say(string $msg) {
    fputs(STDERR, sprintf("\033[32m[%s]\033[m %s\n", date('Y-m-d H:i:s'), $msg));
}
