<?php

namespace Q\RealPrice\Controller;

class Index extends ControllerBase
{
    protected $template = 'index.php';
    protected function logic()
    {
        global $PAGE;
        $PAGE['title'] = '首頁';
    }
}
