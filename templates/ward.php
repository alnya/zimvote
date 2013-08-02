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
    <h1><a href="<?=$baseUrl?>/polling.php">Polling Stations</a></h1>
    <h2>
        <a href='<?=$baseUrl?>/polling.php/<?= $province ?>'><?= $province ?></a>
        <a href='<?=$baseUrl?>/polling.php/<?= $province ?>/<?= $constituency ?>'><?= $constituency ?></a>
        Ward <?= $ward ?>
    </h2>
    <div  class="clearfix">
        <div id='mapcontainer'>
            <img class='resp' src='<?=$mobileUrl?>' data-src-t="<?=$tabletUrl?>" data-src-d="<?=$tabletUrl?>" />
        </div>
        <div id='itemscontainer'>
            <ol>
                <? foreach($items as $item) { ?>
                    <li><?=$item->pollingstation?> (<?=$item->ID?>)</li>
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
    <script language="javascript" src="<?=$baseUrl?>/js/polling.min.js"></script>

</body>
</html>
