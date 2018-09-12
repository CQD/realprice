<?php

namespace Q\RealPrice\Controller;

class FourOFour extends ControllerBase
{
    protected function logic()
    {
        http_response_code(404);
        $this->template = null;
    }
}
