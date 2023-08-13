<?php

namespace Q\RealPrice\Controller;

class Index extends ControllerBase
{
    protected $template = 'index.php';
    protected function logic()
    {
        global $PAGE;
        header('Cache-control: public, max-age=3600');
        $PAGE['title'] = '首頁';
    }
}
