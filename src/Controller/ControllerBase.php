<?php

namespace Q\RealPrice\Controller;

abstract class ControllerBase
{
    private $path;
    protected $template = 'main.php';
    public function __construct()
    {
        $path = $_SERVER['REQUEST_URI'];
        if (false !== $pos = strpos($path, '?')) {
            $path = substr($path, 0, $pos);
        }
        $this->path = $path;
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
