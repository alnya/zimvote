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
    <h1><a href="<?=$baseUrl?>/polling.php">Polling Station Near You</a></h1>

    <? if (sizeof($items) > 0) { ?>
        <div class="clearfix">
            <div id='mapcontainer'>
                <img class='resp' src='<?=$mobileUrl?>' data-src-t="<?=$tabletUrl?>" data-src-d="<?=$tabletUrl?>" />
            </div>
            <div id='itemscontainer'>
                <ol>
                    <? foreach($items as $item) { ?>
                        <li><?=$item->pollingstation?> (<?=$item->ID?>)</li>
                    <? } ?>
                </ol>
            </div>
        </div>
        <div class="clearfix">
            <p>
                Sokwanele is supporting the Simukai 'Protect your Vote' initiative (<a href="www.simukai.org">www.simukai.org</a>).
                You will note that each polling station we provide has a number after its name in brackets.
                This is the Simukai polling station ID number.
                Please take note of the ID number of the polling station you are at, and then follow the Simukai directions exactly as below:
            </p>
            <p>
                <strong>How to Protect your Vote:</strong>
                <br/>
                After you have voted remain at the polling station until voting is complete.
                Once the results are posted outside the station, as is legally required, you can claim your power by SMS the
                results of the presidential election in the following format to one of the numbers below:
            </p>
            <ul>
                <li><a href="sms:0027713563219?body=IDxxxxMTxxxxRMxxxx">00 27 71 3563219</a></li>
                <li><a href="sms:0027713562087?body=IDxxxxMTxxxxRMxxxx">00 27 71 3562087</a></li>
            </ul>
            <p>
                Type in the ID number for your polling station, then the number of votes won by Morgan Tsvangirai and the number of votes won by Robert Mugabe:
                <br/></br/>
                IDxxxxMTxxxxRMxxxx
                <br/><br/>
                eg. ID<span style='color:blue'>0391</span>MT<span style='color:red'>1423</span>RM<span style='color:red'>1262</span>
                <br/><br/>
                The above example will tell us that there are <span style='color:red'>1,423</span> votes for MT and <span style='color:green'>1,262</span> votes for RM at
                Polling Station <span style='color:blue'>0391</span> (Tent in Open Space Cr Mabvazuva-Chaminuka Rds in Harare)</p>
            </p>
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
    <script language="javascript" src="<?=$baseUrl?>/js/polling.min.js"></script>

</body>
</html>
