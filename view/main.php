<?php
global $PAGE;
extract($PAGE);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= isset($title) ? e($title) . ' - ' : ''?>房價趨勢統計</title>
<link type="text/css" rel="stylesheet" href="/s/main.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<?php foreach ($script_files as $script_file):

    if (is_array($script_file)) {
        $src = $script_file['src'] ?? '';
        $async = $script_file['async'] ?? 'async';
        $defer = $script_file['defer'] ?? 'defer';
    } else {
        $src = $script_file;
        $async = 'async';
        $defer = 'defer';
    }
?>
<script src="<?=e($src)?>" <?= $async ?> <?= $defer ?>></script>
<?php endforeach;?>
</head>
<body>
<?= $content ?? '<section>內容物正在載入中<small>...應該...大概...</small></section>' ?>
</body>
</html>
