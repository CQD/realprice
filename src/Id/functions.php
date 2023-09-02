<?php
namespace Q\RealPrice\Id;

class ID {
    public static $counties = [
        '臺北市' => 'a',
        '臺中市' => 'b',
        '基隆市' => 'c',
        '臺南市' => 'd',
        '高雄市' => 'e',
        '新北市' => 'f',
        '宜蘭縣' => 'g',
        '桃園市' => 'h',
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
}

function toCountyId(string $county): string
{
    return ID::$counties[$county] ?? $county;
}

function toCountyName(string $id): string
{
    $counties_reversed = array_flip(ID::$counties);
    return $counties_reversed[$id] ?? $id;
}

function listCountyIds(): array
{
    return array_values(ID::$counties);
}

function listCountyNames(): array
{
    return array_keys(ID::$counties);
}

function countyMap(): array
{
    return ID::$counties;
}

function typeIds(): array
{
    return [
        "住宅大樓" => 0,
        "華廈" => 1,
        "透天厝" => 2,
        "公寓" => 3,
        "套房" => 4,
    ];
}