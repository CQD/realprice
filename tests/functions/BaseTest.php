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
            "ZZ" => -1,
            "ZY" => -2,
            "v0" => -1922,
            "uZ" => 1921,
        ];
        foreach ($values as $str => $num) {
            $this->assertEquals($str, toBase62($num), "toBase62({$num}) 要等於 {$str}");
            $this->assertEquals($num, fromBase62((string)($str)), "fromBase62({$str}) 要等於 {$num}");
        }
    }

    public function testPadBase32()
    {
        $values = [
            "00" => 0,
            "01" => 1,
            "09" => 9,
            "0a" => 10,
            "0z" => 35,
            "0A" => 36,
            "0Z" => 61,
            "10" => 62,
            "19" => 71,
            "1a" => 72,
            "1z" => 97,
            "1A" => 98,
            "1Z" => 123,
            "20" => 124,
            "ZZ" => -1,
            "ZY" => -2,
            "v0" => -1922,
            "uZ" => 1921,
        ];
        foreach ($values as $str => $num) {
            $this->assertEquals($str, toBase62($num, pad: 2), "toBase62({$num}, pad:2) 要等於 {$str}");
            $this->assertEquals($num, fromBase62((string)($str)), "fromBase62({$str}) 要等於 {$num}");
        }
    }
}
