<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../public/index_functions.php';

class ShortUrlTest extends TestCase
{
    private static array $options = [
        'area' => [
            '臺北市' => ['中正區', '大同區', '中山區', '松山區', '大安區', '萬華區', '信義區', '士林區', '北投區', '內湖區', '南港區', '文山區'],
            '新北市' => ['板橋區', '三重區', '中和區', '永和區'],
            '臺中市' => ['中區', '東區', '西區', '南區', '北區'],
        ],
        'type' => ['住宅大樓', '華廈', '透天厝', '公寓', '套房'],
    ];

    public static function validProvider(): array
    {
        return [
            '縣市' => ['/臺北市'],
            '縣市+區域' => ['/臺北市/大安區'],
            '縣市+類型' => ['/臺北市/住宅大樓'],
            '縣市+區域+類型' => ['/臺北市/大安區/住宅大樓'],
        ];
    }

    #[DataProvider('validProvider')]
    public function testValid(string $path)
    {
        $r = resolve_short_url($path, self::$options);
        $this->assertTrue($r['valid']);
        $this->assertNull($r['redirect']);
    }

    public static function invalidProvider(): array
    {
        return [
            '不存在的縣市' => ['/asdasd'],
            '不存在的區域' => ['/臺北市/asdasd'],
            '第三段不是類型' => ['/臺北市/大安區/asdasd'],
            '區域屬於其他縣市' => ['/臺北市/板橋區'],
            '超過三段' => ['/臺北市/大安區/住宅大樓/extra'],
            '空路徑' => ['/'],
            '第三段放區域' => ['/臺北市/大安區/中山區'],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalid(string $path)
    {
        $r = resolve_short_url($path, self::$options);
        $this->assertFalse($r['valid']);
    }

    public static function redirectProvider(): array
    {
        return [
            '台北市' => ['/台北市', '臺北市'],
            '台中市' => ['/台中市', '臺中市'],
            '台北市+區域' => ['/台北市/大安區', '臺北市'],
        ];
    }

    #[DataProvider('redirectProvider')]
    public function testRedirect(string $path, string $expectedContains)
    {
        $r = resolve_short_url($path, self::$options);
        $this->assertTrue($r['valid']);
        $this->assertNotNull($r['redirect']);
        $this->assertStringContainsString($expectedContains, urldecode($r['redirect']));
    }

    public function testInvalidTaiNoRedirect()
    {
        $r = resolve_short_url('/台北市/asdasd', self::$options);
        $this->assertFalse($r['valid']);
        $this->assertNull($r['redirect']);
    }
}
