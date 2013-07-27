<?php
$mobileUrl = "http://maps.googleapis.com/maps/api/staticmap?size=300x300{$points}&sensor=false";
$tabletUrl = "http://maps.googleapis.com/maps/api/staticmap?size=600x400{$points}&sensor=false";
?>
<!DOCTYPE html>
<html>
<head>
    <title></title>
    <link type="text/css" rel="stylesheet" href="<?=$baseUrl?>/css/polling.css"/>
    <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?sensor=false"></script>
    <script>var points = [<?= $jsPoints ?>];</script>
</head>
<body>
    <h1>Polling Stations</h1>
    <h2>
        <a href='<?=$baseUrl?>/polling.php/<?= $province ?>'><?= $province ?></a>
        <a href='<?=$baseUrl?>/polling.php/<?= $province ?>/<?= $constituency ?>'><?= $constituency ?></a>
        Ward <?= $ward ?>
    </h2>

    <div id='mapcontainer'>
        <img class='resp' src='<?=$mobileUrl?>' data-src-t="<?=$tabletUrl?>" data-src-d="<?=$tabletUrl?>" />
    </div>
    <div id='itemscontainer'>
        <ol>
            <? foreach($items as $item) { ?>
                <li><?=$item->pollingstation?></li>
            <? } ?>
        </ol>

        <form action="<?=$baseUrl?>/polling.php/<?= $province ?>/<?= $constituency ?>" method="get">
            <select name='search' id='search'>
                <option value=''>Change ward</option>
                <? foreach($wards as $ward) { ?>
                    <option value="<?=$ward->name?>">Ward <?=$ward->name?></option>
                <? } ?>
            </select>
        </form>
    </div>

    <script language="javascript" src="<?=$baseUrl?>/js/polling.js"></script>

</body>
</html>
