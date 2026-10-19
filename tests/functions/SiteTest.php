<?php

use PHPUnit\Framework\TestCase;
use Q\RealPrice\Site;

class SiteTest extends TestCase
{
    protected function setUp(): void
    {
        Site::setData([
            'types' => ['tower' => '住宅大樓', 'suite' => '套房'],
            'counties' => ['a' => '臺北市', 'o' => '新竹市'],
            'districts' => ['a' => ['a-中山區', 'a-大安區'], 'o' => ['o-新竹市']],
            'regions' => [
                'TW' => ['name' => '全國', 'level' => 0, 'parent' => null],
                'a' => ['name' => '臺北市', 'level' => 1, 'parent' => 'TW'],
                'a-大安區' => ['name' => '大安區', 'level' => 2, 'parent' => 'a'],
                'a-中山區' => ['name' => '中山區', 'level' => 2, 'parent' => 'a'],
                'o' => ['name' => '新竹市', 'level' => 1, 'parent' => 'TW'],
                'o-新竹市' => ['name' => '新竹市', 'level' => 2, 'parent' => 'o'],
            ],
        ]);
    }

    public function testRegionId()
    {
        $this->assertSame('TW', Site::regionId(null));
        $this->assertSame('a', Site::regionId('臺北市'));
        $this->assertSame('a-大安區', Site::regionId('臺北市', '大安區'));
        $this->assertNull(Site::regionId('臺北市', '板橋區'));
        $this->assertNull(Site::regionId('asd'));
    }

    public function testUrl()
    {
        $this->assertSame('/', Site::url('TW'));
        $this->assertSame('/臺北市', rawurldecode(Site::url('a')));
        $this->assertSame('/臺北市/大安區/住宅大樓', rawurldecode(Site::url('a-大安區', 'tower')));
        // 只有一個同名區的縣市，網址不重複區名
        $this->assertSame('/新竹市/套房', rawurldecode(Site::url('o-新竹市', 'suite')));
    }

    public function testFullName()
    {
        $this->assertSame('臺北市大安區', Site::fullName('a-大安區'));
        $this->assertSame('新竹市', Site::fullName('o-新竹市'));
    }

    public function testDistrictsOf()
    {
        // 順序沿用 site.php (近 12 個月成交量排序)
        $this->assertSame(['a-中山區', 'a-大安區'], Site::districtsOf('a'));
        $this->assertSame([], Site::districtsOf('o'));
    }

    public function testTypeSlug()
    {
        $this->assertSame('tower', Site::typeSlug('住宅大樓'));
        $this->assertSame('all', Site::typeSlug(null));
        $this->assertSame('all', Site::typeSlug('asd'));
        $this->assertSame('全部住宅', Site::typeName('all'));
    }

    public function testFormat()
    {
        $this->assertSame('81.8', Site::wan(818013));
        $this->assertSame('+38%', Site::pct(0.3812));
        $this->assertSame('-5%', Site::pct(-0.05));
        $this->assertSame('1.71%', Site::pct(0.0171, 2, false));
        $this->assertSame('—', Site::pct(null));
        $this->assertSame('8,878', Site::int(8878));
    }
}
