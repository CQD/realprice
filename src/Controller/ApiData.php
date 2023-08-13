<?php

namespace Q\RealPrice\Controller;

use PDO;

class ApiData extends ControllerBase
{
    protected $template = null;
    protected function logic()
    {
        header('Content-Type: application/json');
        // header('Cache-control: public, max-age=86400');
        [$sql, $data] = $this->getData(...$this->parseParams());

        $response = [
            'data' => $data,
        ];

        if ($_SERVER["HTTP_HOST"] === "localhost:8080") {
            $response["sql"] = $sql;
        }

        echo json_encode($response);
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
       avg(area) AS area_avg,
       strftime("%Y/%m", transaction_date, 'unixepoch') as ym
FROM house_transactions
WHERE %CONDITIONS%
GROUP BY ym
ORDER BY ym ASC
EOT;

        $start_time = strtotime("2012-08-01") - 10;
        $end_time = time();

        $conditions = [
            "(transaction_date - build_date)/86400/365 BETWEEN CAST(:agemin AS number) AND CAST(:agemax AS number)",
            "transaction_date BETWEEN $start_time AND $end_time",
        ];

        $condition_params = [
            "agemin" => (float) $ages[0],
            "agemax" => (float) $ages[1],
        ];

        if ($parking > 0) $conditions[] = "parking_area > 0";
        elseif ($parking < 0) $conditions[] = "parking_area = 0";

        if ($type) {
            $conditions[] = "type = :type";
            $condition_params["type"] = $type;
        }

        if ($counties) {
            $marks = [];
            foreach ($counties as $i => $county) {
                $condition_params["county$i"] = $county;
                $marks[] = ":county$i";
            }
            $conditions[] = "county in (" . implode(",", $marks) . ")";
        }

        if ($districts) {
            $marks = [];
            foreach ($districts as $i => $district) {
                $condition_params["district$i"] = $district;
                $marks[] = ":district$i";
            }
            $conditions[] = "district in (" . implode(",", $marks) . ")";
        }

        $sql = str_replace('%CONDITIONS%', implode(" AND ", $conditions), $sql);

        $result = [];
        $stmt = $this->db->prepare($sql);

        $stmt->execute($condition_params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row["ym"]] = $row;
        }

        foreach ($condition_params as $key => $value) {
            if (is_string($value)) {
                $value = "'$value'";
            }
            $sql = str_replace(":$key", $value, $sql);
        }
        $sql = str_replace("\n", " ", $sql);

        return [$sql, $result];
    }

    protected function parseParams() {
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
