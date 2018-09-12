<?php

include __DIR__ . '/../vendor/autoload.php';

$map = [
    '/' => 'Index',
    '/api' => 'Api',
    '404' => 'FourOFour',
];


$path = $_SERVER['REQUEST_URI'];
if (false !== $pos = strpos($path, '?')) {
    $path = substr($path, 0, $pos);
}

$clazz = sprintf(
    '\Q\RealPrice\Controller\%s',
    $map[$path] ?? $map['404']
);


$PAGE = [];
(new $clazz)->run();

/////////////////////////////////////////////////////////

function e($str, $type = 'html')
{
    $funcs = [
        'html' => 'htmlspecialchars',
        'js' => 'json_encode',
    ];

    return $funcs[$type]($str);
}
