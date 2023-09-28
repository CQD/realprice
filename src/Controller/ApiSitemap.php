<?php

namespace Q\RealPrice\Controller;

class ApiSitemap extends ControllerBase
{
    protected $template = "sitemap.php";
    protected function logic()
    {
        header('Content-Type: text/xml');
        header('Cache-control: public, max-age=86400');

        $options = require __DIR__ . "/../../build/option.php";

        $urls = [
            "https://realprice.cqd.tw/",
        ];
        foreach ($options["area"] as $area => $subareas) {
            $area = urlencode($area);
            $urls[] = "https://realprice.cqd.tw/?area={$area}&parking=0&y_left=no_parking_unit_price_median&y_right=cnt";
            foreach ($subareas as $subarea) {
                $subarea = urlencode($subarea);
                $urls[] = "https://realprice.cqd.tw/?area={$area}&parking=0&subarea={$subarea}&y_left=no_parking_unit_price_median&y_right=cnt";
            }
        }
        return [
            "urls" => $urls,
        ];
    }
}
