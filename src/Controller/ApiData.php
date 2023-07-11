<?php

namespace Q\RealPrice\Controller;

use PDO;

class ApiData extends ControllerBase
{
    protected $template = null;
    protected function logic()
    {
        header('Content-Type: application/json');
        header('Cache-control: public max-age=86400');
        echo json_encode([
            'data' => $this->getData(...$this->parseParams()),
        ]);
    }

    protected function getData(
        null|array $counties,
        null|array $districts,
        int $parking,
        null|string $type,
        array $ages,

    ) {
        $sql = <<<EOT
SELECT count(*) AS cnt,
       sum(price) AS price_total,
       avg(price / area) AS unit_price_avg,
       avg(price) AS price_avg,
       strftime("%Y/%m", transaction_date, 'unixepoch') as ym
FROM house_transactions
WHERE %CONDITIONS%
GROUP BY ym
ORDER BY ym ASC
EOT;

        $start_time = strtotime("2012-08-01") - 10;

        $conditions = [
            "(transaction_date - build_date)/86400/365 BETWEEN {$ages[0]} AND ({$ages[1]} + 1)",
            "transaction_date BETWEEN $start_time AND unixepoch()",
        ];

        if ($parking > 0) $conditions[] = "parking_area > 0";
        elseif ($parking < 0) $conditions[] = "parking_area = 0";

        if ($type) $conditions[] = "type = '$type'"; # XXX injection
        if ($counties) $conditions[] = "county in ('" . implode("','", $counties) . "')"; # XXX injection
        if ($districts) $conditions[] = "district in ('" . implode("','", $districts) . "')"; # XXX injection

        $sql = str_replace('%CONDITIONS%', implode(" AND ", $conditions), $sql);

        $result = [];
        foreach ($this->db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $result[$row["ym"]] = $row;
        }

        return $result;
    }

    protected function parseParams() {
        $truthy = ["true", "1", "yes"];
        $falsy = ["false", "0", "no"];

        $counties = ($_GET["area"] ?? false) ? explode(",", $_GET["area"]) : null;
        $districts = ($_GET["subarea"] ?? false) ? explode(",", $_GET["subarea"]) : null;
        $type = $_GET["type"] ?? null;

        $age_min = (float) ($_GET["age_min"] ?? -1);
        $age_max = (float) ($_GET["age_max"] ?? 9999);

        $parking = (int) ($_GET["parking"] ?? 0);

        return [
            $counties,
            $districts,
            $parking,
            $type,
            [$age_min, $age_max],
        ];
    }
}
