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
    }

    public function run()
    {
        $this->logic();
        if ($this->template) {
            $file = __DIR__ . '/../../view/' . $this->template;
            include $file;
        }
    }

    abstract protected function logic();
}
