<?php
/**
 * Created by IntelliJ IDEA.
 * User: wilsor27
 * Date: 26/07/2013
 * Time: 22:38
 * To change this template use File | Settings | File Templates.
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title></title>
    <link type="text/css" rel="stylesheet" href="<?=$baseUrl?>/css/polling.css"/>
</head>
<body>
    <h1><a href="<?=$baseUrl?>/polling.php">Polling Stations</a></h1>
    <h2>
        <a href='<?=$baseUrl?>/polling.php/<?= $province ?>'><?= $province ?></a>
        <a href='<?=$baseUrl?>/polling.php/<?= $province ?>/<?= $constituency ?>'><?= $constituency ?></a>
    </h2>
    <p>Find all polling stations for your constituency:</p>
    <form action="<?=$baseUrl?>/polling.php/<?= $province ?>/<?= $constituency ?>" method="get">
        <select name='search' id='search'>
            <option value=''>Choose ward</option>
            <? foreach($wards as $ward) { ?>
                <option value="<?=$ward->name?>">Ward <?=$ward->name?></option>
            <? } ?>
        </select>
    </form>
    <script language="javascript" src="<?=$baseUrl?>/js/polling.min.js"></script>
</body>
</html>