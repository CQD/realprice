<?php

namespace Q\RealPrice\Controller;

use Q\RealPrice\Site;

class ApiSitemap extends ControllerBase
{
    protected $template = "sitemap.php";
    protected function logic()
    {
        header('Content-Type: text/xml');
        header('Cache-control: public, max-age=86400');

        $site = Site::data();
        $base = "https://realprice.cqd.tw";
        $urls = ["{$base}/"];
        foreach (array_keys($site['counties']) as $cid) {
            if (!Site::region($cid)) continue;
            $ids = array_merge([$cid], Site::districtsOf($cid));
            foreach ($ids as $id) {
                $urls[] = $base . Site::url($id);
                foreach (array_keys($site['types']) as $slug) {
                    $urls[] = $base . Site::url($id, $slug);
                }
            }
        }
        return [
            "urls" => $urls,
        ];
    }
}
