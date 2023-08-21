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
        $this->db->sqliteCreateAggregate(
            // the name of the function to declare
            'median',
            // method called for each row
            function($context, $row_number, $value){
                $context[] = $value;
                return $context;
            },
            // method called once all row have been iterated over
            function($context, $row_count){
                sort($context, SORT_NUMERIC);
                $count = count($context);
                $middle = floor($count/2);
                if (($count % 2) == 0) {
                    return ($context[$middle--] + $context[$middle])/2;
                } else {
                    return $context[$middle];
                }
            },
            1
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
