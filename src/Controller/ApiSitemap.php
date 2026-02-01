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

        $base = "https://realprice.cqd.tw";
        $urls = ["{$base}/"];
        foreach ($options["area"] as $area => $subareas) {
            $enc_area = urlencode($area);
            foreach ($options["type"] as $type) {
                $enc_type = urlencode($type);
                $urls[] = "{$base}/{$enc_area}/{$enc_type}";
                foreach ($subareas as $subarea) {
                    $urls[] = "{$base}/{$enc_area}/" . urlencode($subarea) . "/{$enc_type}";
                }
            }
        }
        return [
            "urls" => $urls,
        ];
    }
}
