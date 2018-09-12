<?php

namespace Q\RealPrice\Controller;

class Api extends ControllerBase
{
    protected $template = null;
    protected function logic()
    {
        $area = $_GET['area'] ?? '臺北市' ;
        $hasParking = $_GET['has_parking'] ?? '無車位';
        $ageRange = $_GET['age_range'] ?? '一年以下';
        $type = $_GET['type'] ?? '透天厝';
        $target = $_GET['target'] ?? '單價';

        $map = include __DIR__ . '/../../build/_map.php';

        $dataFile = $map[$area][$hasParking][$ageRange][$type] ?? null;
        $dataFile = $dataFile ? __DIR__ . "/../../build/{$dataFile}" : null;
        $data = $dataFile && is_file($dataFile)
            ? include $dataFile
            : [];

        header('Content-Type: application/json');
        echo json_encode([
            'area' => $area,
            'has_parking' => $hasParking,
            'age_range' => $ageRange,
            'type' => $type,
            'data' => $data,
        ]);
    }
}
