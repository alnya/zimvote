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
    </h2>
    <p>Find all polling stations for your province:</p>
    <form action="<?=$baseUrl?>/polling.php/<?= $province ?>" method="get">
        <select name='search' id='search'>
            <option value=''>Choose constituency</option>
            <? foreach($constituencies as $constituency) { ?>
                <option value="<?=$constituency->name?>"><?=$constituency->name?></option>
            <? } ?>
        </select>
    </form>
    <script language="javascript" src="<?=$baseUrl?>/js/polling.js"></script>
</body>
</html>