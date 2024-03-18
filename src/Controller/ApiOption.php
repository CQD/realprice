<?php

namespace Q\RealPrice\Controller;

use PDO;
use function Q\RealPrice\Id\typeIds;

class ApiOption extends ControllerBase
{
    protected $template = null;
    protected function logic()
    {
        header('Content-Type: application/json');
        header('Cache-control: public, max-age=86400');
        echo json_encode([
            'area' => $this->getArea(),
            'type' => $this->getType(),
            'dataver' => $this->getDataver(),
            'county_ids' => $this->getCountyIds(),
            'district_ids' => $this->getDistrictIds(),
            'type_ids' => typeIds(),
        ]);
    }

    protected function getType() {
        $sql = "SELECT name FROM types";

        $types = [];
        foreach ($this->db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $types[] = $row["name"];
        }
        return $types;
    }

    protected function getArea() {
        $twoYearAgo = date("Ymd", strtotime("2 year ago"));

        $sql = <<<EOT
SELECT c.name as county, d.name as district
FROM house_transactions AS h
JOIN districts AS d ON d.id = h.district_id
JOIN counties AS c ON c.id = d.county_id
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

    protected function getDataver() {
        $dirs = scandir(__DIR__ . '/../../data/');
        $dirs = array_filter($dirs, function($dir) {
            return preg_match('/^\d+$/', $dir);
        });

        if (!$dirs) return "不明";
        return max($dirs);
    }

    protected function getCountyIds() {
        $sql = "SELECT id, name FROM counties";

        $result = [];
        foreach ($this->db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $result[$row["name"]] = $row["id"];
        }
        return $result;
    }

    protected function getDistrictIds() {
        $sql = "SELECT id, county_id, name FROM districts";

        $result = [];
        foreach ($this->db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $county_id = $row["county_id"];
            $result[$county_id] = $result[$county_id] ?? [];
            $result[$county_id][$row["name"]] = $row["id"];
        }
        return $result;
    }
}
