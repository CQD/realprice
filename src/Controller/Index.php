<?php

namespace Q\RealPrice\Controller;

use Q\RealPrice\Site;

/**
 * 全國 / 縣市 / 鄉鎮市區 三層頁面
 */
class Index extends ControllerBase
{
    protected $template = 'index.php';

    protected function logic()
    {
        global $ROUTE;
        $site = Site::data();

        $id = Site::regionId($ROUTE['area'], $ROUTE['subarea']);
        $region = $id ? Site::region($id) : null;
        if (!$region) {
            http_response_code(404);
            $this->template = null;
            return;
        }

        header('Cache-control: public, max-age=3600');

        $level = $region['level'];
        $type = $level === 0 ? 'all' : Site::typeSlug($ROUTE['type']);
        $typeName = Site::typeName($type);
        $countyId = $level === 2 ? $region['parent'] : ($level === 1 ? $id : null);
        $name = $level === 0 ? '全台' : Site::fullName($id);
        $bt = $region['by_type'][$type] ?? ['up' => null, 'n12' => 0, 'pre_prem' => null];
        $period = "{$site['month_end_start']}～{$site['month_end']}";

        $summary = sprintf(
            '%s近一年（%s）%s成屋單價中位數 %s 萬/坪，共 %s 筆成交',
            $name, $period, $type === 'all' ? '' : $typeName, Site::wan($bt['up']), Site::int($bt['n12'])
        );
        if ($region['chg10'] !== null || $region['chg5'] !== null) {
            $summary .= sprintf(
                '；%s品質調整後 10 年漲幅 %s、5 年漲幅 %s',
                $type === 'all' ? '' : '全部住宅',
                Site::pct($region['chg10']), Site::pct($region['chg5'])
            );
        }
        $summary .= '。';

        // 預售價差：「全部住宅」用住宅大樓比較，避免新成屋混進透天厝
        $premType = $type === 'all' ? 'tower' : $type;
        $prem = $region['by_type'][$premType] ?? [];
        $kpis = [
            ['近一年單價中位數', Site::wan($bt['up']), '萬/坪', "{$period}・成屋"],
            ['10 年漲幅', Site::pct($region['chg10']), '', '品質調整・全部住宅'],
            ['5 年漲幅', Site::pct($region['chg5']), '', '品質調整・全部住宅'],
            ['近一年成交', Site::int($bt['n12']), '筆', '成屋，已排除非市場交易'],
            ['預售 vs 新成屋', Site::pct($prem['pre_prem'] ?? null), '',
                isset($prem['pre_prem'])
                    ? sprintf('%s預售 %s / 新成屋 %s 萬', $type === 'all' ? '住宅大樓・' : '', Site::wan($prem['up_pre']), Site::wan($prem['up_new']))
                    : '樣本不足'],
            ['毛租金收益率', Site::pct($region['yield'] ?? null, 2, false), '',
                isset($region['rent']) ? "{$site['rent_year']}・月租 " . Site::int($region['rent']) . ' 元/坪' : '樣本不足'],
        ];

        $breadcrumbs = [['全台', '/']];
        if ($countyId) $breadcrumbs[] = [$site['counties'][$countyId], Site::url($countyId, $type)];
        if ($level === 2 && Site::districtsOf($countyId)) $breadcrumbs[] = [$region['name'], Site::url($id, $type)];

        $title = $level === 0
            ? '實價登錄房價趨勢｜全台房價地圖與漲幅排行'
            : sprintf('%s%s房價趨勢｜實價登錄房價趨勢', $name, $type === 'all' ? '' : " {$typeName}");

        return [
            'site' => $site,
            'id' => $id,
            'region' => $region,
            'level' => $level,
            'type' => $type,
            'typeName' => $typeName,
            'countyId' => $countyId,
            'districts' => $countyId ? Site::districtsOf($countyId) : [],
            'name' => $name,
            'summary' => $summary,
            'kpis' => $kpis,
            'breadcrumbs' => $breadcrumbs,
            'title' => $title,
            'canonical' => 'https://realprice.cqd.tw' . Site::url($id, $type),
            'pageConfig' => [
                'level' => $level,
                'id' => $id,
                'county' => $countyId,
                'type' => $type,
                'typeName' => $typeName,
                'types' => $site['types'],
                'data' => "/data/{$site['build_id']}/",
                'counties' => $site['counties'],
            ],
        ];
    }
}
