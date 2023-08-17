#!/usr/bin/env php
<?php


$file = $argv[1];
if (!file_exists($file)) last_words("檔案 {$file} 不存在！");

$json = json_decode(file_get_contents($file), true);
if (!$json) last_words("檔案 {$file} 不是合法的 JSON 格式！");

echo "<?php \nreturn ";
var_export($json);
echo ";\n";


///////////////////////////////////

function last_words($msg) {
    fputs(STDERR, $msg . "\n");
    exit(1);
}
