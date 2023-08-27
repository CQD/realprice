<?php

namespace Q\RealPrice\Controller;

abstract class ControllerBase
{
    private $path;
    protected $template = 'main.php';
    protected $db;

    public function __construct()
    {
        $path = $_SERVER['REQUEST_URI'];
        if (false !== $pos = strpos($path, '?')) {
            $path = substr($path, 0, $pos);
        }
        $this->path = $path;

        $data_file = __DIR__  . '/../../build/transactions.sqlite3';
        $this->db = new \PDO("sqlite:{$data_file}");

        // 註冊中位數函數
        // https://stackoverflow.com/posts/73635970/revisions
        $step_func = function($context, $row_number, $value) {
            $context[] = $value;
            return $context;
        };

        $percentile_func = fn($p) => function ($context, $row_count) use ($p) {
            sort($context, SORT_NUMERIC);
            $count = count($context);
            $middle = floor($count * $p);
            if (($count % 2) == 0) {
                return ($context[max($middle - 1, 0)] + $context[$middle])/2;
            }
            return $context[$middle];
        };

        $this->db->sqliteCreateAggregate('median', $step_func, $percentile_func(0.5), 1);
        $this->db->sqliteCreateAggregate('p25', $step_func, $percentile_func(0.25), 1);
        $this->db->sqliteCreateAggregate('p75', $step_func, $percentile_func(0.75), 1);

        // 註冊車位價格計算函數
        $this->db->sqliteCreateAggregate(
            'parking_unit_price',
            // step function
            function($context, $row_number, $parking_area, $parking_price, $area, $price){
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
            function ($context, $row_count) {
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

    public function run()
    {
        $result = $this->logic();
        if ($this->template) {
            $file = __DIR__ . '/../../view/' . $this->template;
            if (is_array($result)) extract($result);
            include $file;
        }
    }

    abstract protected function logic();
}
