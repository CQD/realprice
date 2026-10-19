<?php

namespace Q\RealPrice\Controller;

/**
 * 需要 SQLite 的舊 API (/api/data、/api/option) 用
 */
abstract class DbControllerBase extends ControllerBase
{
    protected $db;

    public function __construct()
    {
        parent::__construct();

        $data_file = __DIR__  . '/../../build/transactions.sqlite3';
        $this->db = new \Pdo\Sqlite("sqlite:{$data_file}");

        // 註冊中位數函數
        // https://stackoverflow.com/posts/73635970/revisions
        $step_func = static function($context, $row_number, $value) {
            $context[] = $value;
            return $context;
        };

        $percentile_func = static fn($p) => static function ($context, $row_count) use ($p) {
            sort($context, SORT_NUMERIC);
            $count = count($context);
            $middle = floor($count * $p);
            if (($count % 2) == 0) {
                return ($context[max($middle - 1, 0)] + $context[$middle])/2;
            }
            return $context[$middle];
        };

        $this->db->createAggregate('median', $step_func, $percentile_func(0.5), 1);
        $this->db->createAggregate('p25', $step_func, $percentile_func(0.25), 1);
        $this->db->createAggregate('p75', $step_func, $percentile_func(0.75), 1);

        // 註冊車位價格計算函數
        $this->db->createAggregate(
            'parking_unit_price',
            // step function
            static function($context, $row_number, $parking_area, $parking_price, $area, $price){
                $context = $context ? $context : [
                    "parking_total_area" => 0,
                    "parking_total_price" => 0,
                    "total_area" => 0,
                    "total_price" => 0,
                ];

                $context["parking_total_price"] += $parking_price;
                $context["parking_total_area"] += $parking_area;
                $context["total_price"] += $price;
                $context["total_area"] += $area;
                return $context;
            },

            // final function
            static function ($context, $row_count) {
                if (!$context) return 0;
                if ($context["parking_total_area"] && $context["parking_total_price"]) {
                    return $context["parking_total_price"] / $context["parking_total_area"];
                }
                if ($context["total_area"]) {
                    return $context["total_price"] / $context["total_area"];
                }

                return 0;
            },

            // 參數數量
            4,
        );
    }
}
