<?php

global $PAGE;

$names = include __DIR__ . '/../build/_names.php';
$dates = include __DIR__ . '/../build/_dates.php';

ob_start();
?>
<script>
let areas = {
<?php foreach($names as $mainArea => $subAreas):?>
    <?=e($mainArea, 'js')?>:[
<?php foreach($subAreas as $subArea):?>
        <?=e($subArea, 'js')?>,
<?php endforeach;?>
    ],
<?php endforeach;?>
}

let dates = <?= e($dates, 'js')?>
</script>

<section id="input">

地區：<select id="areas"><option value="" disabled>請選縣市</option></select>
<select id="subareas"><option value="" disabled>請選區域</option></select>
&nbsp;
&nbsp;
車位：<select id="parking"><option value="有車位">有車位</option><option value="無車位">無車位</option></select>
&nbsp;
&nbsp;
屋齡：<select id="age">
<option value="一年以下">一年以下</option>
<option value="一～五年">一～五年</option>
<option value="五～十年">五～十年</option>
<option value="十～十五年">十～十五年</option>
<option value="十五～二十年">十五～二十年</option>
<option value="二十～三十年">二十～三十年</option>
<option value="三十～四十年">三十～四十年</option>
<option value="四十年以上">四十年以上</option>
</select>
&nbsp;
&nbsp;
類型：<select id="type">
<option value="電梯大樓">電梯大樓</option>
<option value="公寓(5樓含以下無電梯)">公寓(5樓含以下無電梯)</option>
<option value="套房(1房1廳1衛)">套房(1房1廳1衛)</option>
<option value="透天厝">透天厝</option>
</select>
&nbsp;
&nbsp;

<button class="submit" data-target="單價">單價</button>
<button class="submit" data-target="總價">總價</button>
&nbsp;
&nbsp;

/ 總<select id="tt">
<option value="案量">案量</option>
<option value="金額">金額</option>
</select>

</section>

<div id='container'>
</div>

<?php
$PAGE['content'] = ob_get_clean();
$PAGE['script_files'][] = '/s/main.js';
$PAGE['script_files'][] = '/s/notification.js';
$PAGE['script_files'][] = ['src' => 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.3/Chart.min.js', 'defer' => false, 'async' => false];
include __DIR__ .'/main.php';
