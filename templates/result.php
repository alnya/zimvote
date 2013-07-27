<?php
$mobileUrl = "http://maps.googleapis.com/maps/api/staticmap?scale=2&size=300x300{$points}&sensor=false";
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
    <h1>Polling Station Near You</h1>

    <? if (sizeof($items) > 0) { ?>
        <div id='mapcontainer'>
            <img class='resp' src='<?=$mobileUrl?>' data-src-t="<?=$tabletUrl?>" data-src-d="<?=$tabletUrl?>" />
        </div>
        <div id='itemscontainer'>
            <ol>
                <? foreach($items as $item) { ?>
                    <li><?=$item->pollingstation?></li>
                <? } ?>
            </ol>
        </div>
    <? } else { ?>
        <p>
            Sorry, there are no polling stations near your current location.
            You can choose a constituency to search below.
        </p>
        <form action="<?=$baseUrl?>/polling.php" method="get">
            <select name='search' id='search'>
                <option value=''>Choose province</option>
                <? foreach($provinces as $province) { ?>
                    <option value="<?=$province->name?>"><?=$province->name?></option>
                <? } ?>
            </select>
        </form>
    <? } ?>
    <script language="javascript" src="<?=$baseUrl?>/js/polling.js"></script>

</body>
</html>
