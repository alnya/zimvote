<?php

require 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();

// define routes
$app->get('/',  'getDefault');

$app->get('/location/:lat/:long',  'getPollingStationsByLocation');
$app->get('/:province/','getProvince');
$app->get('/:province/:constituency',  'getConstituency');
$app->get('/:province/:constituency/:ward',  'getWard');

$app->hook('slim.before', function () use ($app) {
    $app->view()->appendData(array('baseUrl' => 'http://localhost/~wilsor27/zimvote/'));
});

// end define routes
$app->run();

function getDefault()
{
    $app = \Slim\Slim::getInstance();
    $db = getConnection();
    $data = array(
        'provinces' => getProvinces($db)
    );

    $app->render('default.php', $data);
    $db = null;
}


function getProvince($province) {

    $app = \Slim\Slim::getInstance();

    try {
        $db = getConnection();

        $data = array(
            'provinces' => getProvinces($db),
            'constituencies' => getConstituencies($db, $province),
            'province' => $province
        );

        $app->render('province.php', $data);
        $db = null;

    } catch(PDOException $e) {
        $app->render('error.php', array('error' => $e->getMessage()));
    }
}

function getConstituency($province, $constituency) {

    $app = \Slim\Slim::getInstance();

    try {
        $db = getConnection();

        $data = array(
            'provinces' => getProvinces($db),
            'constituencies' => getConstituencies($db, $province),
            'wards' => getWards($db, $constituency),
            'province' => $province,
            'constituency' => $constituency
        );

        $app->render('constituency.php', $data);
        $db = null;

    } catch(PDOException $e) {
        $app->render('error.php', array('error' => $e->getMessage()));
    }
}

function getWard($province, $constituency, $ward) {

    $app = \Slim\Slim::getInstance();

    $sql = "select * from pollingstations where constituency = :constituency and wardnumber = :ward";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("constituency", $constituency);
        $stmt->bindParam("ward", $ward);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_OBJ);

        $data = array(
            'items' => $items,
            'points' => getMapPoints($items),
            'jsPoints' => getJSMapPoints($items),
            'provinces' => getProvinces($db),
            'constituencies' => getConstituencies($db, $province),
            'wards' => getWards($db, $constituency),
            'province' => $province,
            'constituency' => $constituency,
            'ward' => $ward
        );

        $app->render('ward.php', $data);
        $db = null;

    } catch(PDOException $e) {
        $app->render('error.php', array('error' => $e->getMessage()));
    }
}

function getPollingStationsByLocation($latitude, $longitude) {
    $app = \Slim\Slim::getInstance();

    $range = 0.005;

    $minLat = floatval($latitude)-$range;
    $maxLat = floatval($latitude)+$range;
    $minLong = floatval($longitude)-$range;
    $maxLong = floatval($longitude)+$range;

    $sql = "select * from pollingstations where latitude between
    :minLat and :maxLat and longitude between :minLong and :maxLong";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("minLat", $minLat);
        $stmt->bindParam("maxLat", $maxLat);
        $stmt->bindParam("minLong", $minLong);
        $stmt->bindParam("maxLong", $maxLong);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_OBJ);

        $data = array(
            'items' => $items,
            'points' => getMapPoints($items),
            'jsPoints' => getJSMapPoints($items),
            'provinces' => getProvinces($db),
        );

        $app->render('result.php', $data);
        $db = null;

    } catch(PDOException $e) {
        $app->render('error.php', array('error' => $e->getMessage()));
    }
}

function getProvinces($db)
{
    $stmt = $db->prepare('select distinct province as name from pollingstations order by province asc');
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

function getConstituencies($db, $province)
{
    $stmt = $db->prepare('select distinct constituency as name from pollingstations where province = :province order by constituency asc');
    $stmt->bindParam("province", $province);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

function getWards($db, $constituency)
{
    $stmt = $db->prepare('select wardnumber as name from wards where constituency = :constituency order by wardnumber asc');
    $stmt->bindParam("constituency", $constituency);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}


function getMapPoints($items) {
    $points = '';
    foreach($items as $i => $item)
    {
        if ($item->longitude && $item->latitude && strlen($points) < 1800)
        {
            $num = $i+1;
            $points.="&markers=label:{$num}%7C{$item->longitude},{$item->latitude}";
        }
    }

    return $points;
}

function getJSMapPoints($items) {
    $points = '';
    foreach($items as $i => $item)
    {
        if ($item->longitude && $item->latitude)
        {
            $name = addslashes($item->pollingstation);
            if (strlen($points) > 0) { $points.=','; }
            $points.="['{$name}',{$item->longitude},{$item->latitude}]";
        }
    }

    return $points;
}


// database setup
function getConnection() {
    include 'conn.php';
    $dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $dbh;
}