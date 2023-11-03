<?php

use PHPUnit\Framework\TestCase;
use function Q\RealPrice\Id\fromBase62;
use function Q\RealPrice\Id\toBase62;

class BaseTest extends TestCase
{
    public function testBase32()
    {
        $values = [
            "0" => 0,
            "1" => 1,
            "9" => 9,
            "a" => 10,
            "z" => 35,
            "A" => 36,
            "Z" => 61,
            "10" => 62,
            "19" => 71,
            "1a" => 72,
            "1z" => 97,
            "1A" => 98,
            "1Z" => 123,
            "20" => 124,
            "ZZ" => 3843,
        ];
        foreach ($values as $str => $num) {
            $this->assertEquals($str, toBase62($num));
            $this->assertEquals($num, fromBase62((string)($str)));
        }
    }
}
