<?php

namespace Q\RealPrice\Controller;

use PDO;

class ApiOption extends ControllerBase
{
    protected $template = null;
    protected function logic()
    {
        header('Content-Type: application/json');
        header('Cache-control: public max-age=86400');
        echo json_encode([
            'area' => $this->getArea(),
            'type' => $this->getType(),
        ]);
    }

    protected function getType() {
        $sql = "SELECT type FROM house_transactions GROUP BY type ORDER BY count(*) DESC";

        $types = [];
        foreach ($this->db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $types[] = $row["type"];
        }
        return $types;
    }

    protected function getArea() {
        $twoYearAgo = date("Ymd", strtotime("2 year ago"));

        $sql = <<<EOT
SELECT county, district
FROM house_transactions
GROUP BY county, district
ORDER BY count(IIF(transaction_date > {$twoYearAgo}, 1, NULL)) DESC
EOT;

        $result = [];
        foreach ($this->db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $county = $row['county'];
            $district = $row['district'];

            $result[$county] = $result[$county] ?? [];
            $result[$county][] = $district;
        }

        return $result;
    }
}
