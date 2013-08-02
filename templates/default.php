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
    <h1>Polling Stations</h1>
    <p>Find all polling stations for your province:</p>
    <form action="<?=$baseUrl?>/polling.php" method="get">
        <select name='search' id='search'>
            <option value=''>Choose province</option>
            <? foreach($provinces as $province) { ?>
                <option value="<?=$province->name?>"><?=$province->name?></option>
            <? } ?>
        </select>
    </form>
    <script language="javascript" src="<?=$baseUrl?>/js/polling.min.js"></script>
    <script language="javascript">polling.getLocation();</script>
</body>
</html>