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

        // register the median function
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
