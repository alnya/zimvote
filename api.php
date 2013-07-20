<?php
require 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();

// define routes
$app->get('/regions', 'getRegions');
$app->get('/parties', 'getParties');
$app->get('/constituencies/:race/:year', 'getConstituencies');
$app->get('/resultssummary/:race/:year/:constituency',  'getResultsSummary');
$app->get('/results/party/:race/:year',  'getPartyResults');
$app->get('/results/:race/:year/:constituency',  'getResults');

$app->post('/candidate', 'addCandidate');
$app->put('/candidate/:id', 'updateCandidate');
$app->delete('/candidate/:id',   'deleteCandidate');

// end define routes
$app->run();

function writeResponse($data)
{
    $app = \Slim\Slim::getInstance();
    $app->contentType('application/json');
    $data = array('data' => $data);
    echo json_encode($data);
}

function writeError($message)
{
    $app = \Slim\Slim::getInstance();
    $app->contentType('application/json');
    $data = array('error'=> array('text'=>$message));
    echo json_encode($data);
    $app->response()->status(500);
}

// get all regions
function getRegions() {
    $sql = "select * FROM regions ORDER BY region_name";
    try {
        $db = getConnection();
        $stmt = $db->query($sql);
        $items = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
        writeResponse($items);
    } catch(PDOException $e) {
        writeError($e->getMessage());
    }
}

// get all parties
function getParties() {
    $sql = "select * FROM parties ORDER BY party_name";
    try {
        $db = getConnection();
        $stmt = $db->query($sql);
        $items = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
        writeResponse($items);
    } catch(PDOException $e) {
        writeError($e->getMessage());
    }
}

function getPartyResults($race, $year) {

    switch($race)
    {
        case "president":
            $sql = "SELECT sum(r.zec_votes) AS votes,
                    ((sum(r.zec_votes) / (select sum(t.zec_votes) FROM presidential_results t)) * 100) AS Percent,
                    p.colour AS colour, concat(m.mp_firstname,' ',m.mp_surname) AS name
                    from presidential_results r join mps m on r.mp_id = m.mp_id
                    join parties p on p.party_id = m.party_id
                    where r.year = :year group by r.mp_id
                    order by r.zec_votes desc";
            break;
        case "house":
            $sql = "SELECT sum(r.zec_votes) AS votes,
                    ((sum(r.zec_votes) / (select sum(t.zec_votes) FROM presidential_results t)) * 100) AS Percent,
                    p.colour AS colour, p.party_name AS name
                    from house_results r join mps m on r.mp_id = m.mp_id
                    join parties p on p.party_id = m.party_id
                    where r.year = :year group by m.party_id
                    order by r.zec_votes desc";
            break;
        case "senate":
            $sql = "SELECT sum(r.zec_votes) AS votes,
                    ((sum(r.zec_votes) / (select sum(t.zec_votes) FROM presidential_results t)) * 100) AS Percent,
                    p.colour AS colour, p.party_name AS name
                    from senators r join parties p on p.party_id = r.party_id
                    where r.year = :year group by r.party_id
                    order by r.zec_votes desc";
            break;
    }

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("year", $year);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;

        writeResponse($items);
    } catch(PDOException $e) {
        writeError($e->getMessage());
    }
}

function getConstituencies($race, $year) {

    switch($race)
    {
        case "president":
            $sql = "select c.constit_id as id, c.constit_name as name, c.registered_voters as voters, c.region_id, k.geometry,
                CEIL((v.votes / c.registered_voters) * 100) as turnout,
                (SELECT p.colour FROM presidential_results r join mps m on r.mp_id = m.mp_id join parties p on
                p.party_id = m.party_id where r.constit_id = c.constit_id ORDER BY r.zec_votes DESC LIMIT 1) as colour,
                CEIL(((SELECT r.zec_votes FROM presidential_results r where r.constit_id = c.constit_id ORDER BY r.zec_votes DESC LIMIT 1) / v.votes) * 100) as won
                from constituencies c
                inner join constituencykml k on c.constit_name = k.constituency
                join (SELECT q.constit_id, SUM(q.zec_votes) as votes FROM presidential_results q GROUP BY q.constit_id) v on v.constit_id = c.constit_id
                WHERE c.year = :year
                ORDER BY constit_name";
            break;
        case "battleground":
            $sql = "select c.constit_id as id, c.constit_name as name, c.registered_voters as voters, c.region_id, k.geometry,
                CEIL((v.votes / c.registered_voters) * 100) as turnout,
                (SELECT p.colour FROM house_results r join mps m on r.mp_id = m.mp_id join parties p on
                p.party_id = m.party_id where r.constit_id = c.constit_id ORDER BY r.zec_votes DESC LIMIT 1) as colour,
                CEIL(((SELECT r.zec_votes FROM house_results r where r.constit_id = c.constit_id ORDER BY r.zec_votes DESC LIMIT 1) / v.votes) * 100) as won
                from constituencies c inner join constituencykml k on c.constit_name = k.constituency
                join (SELECT q.constit_id, SUM(q.zec_votes) as votes FROM house_results q GROUP BY q.constit_id) v on v.constit_id = c.constit_id
                WHERE c.year = :year ORDER BY constit_name";
            break;
        case "house":
            $sql = "select c.constit_id as id, c.constit_name as name, c.registered_voters as voters, c.region_id, k.geometry,
                CEIL((v.votes / c.registered_voters) * 100) as turnout,
                (SELECT p.colour FROM house_results r join mps m on r.mp_id = m.mp_id join parties p on
                p.party_id = m.party_id where r.constit_id = c.constit_id ORDER BY r.zec_votes DESC LIMIT 1) as colour,
                CEIL(((SELECT r.zec_votes FROM house_results r where r.constit_id = c.constit_id ORDER BY r.zec_votes DESC LIMIT 1) / v.votes) * 100) as won
                from constituencies c inner join constituencykml k on c.constit_name = k.constituency
                join (SELECT q.constit_id, SUM(q.zec_votes) as votes FROM house_results q GROUP BY q.constit_id) v on v.constit_id = c.constit_id
                WHERE c.year = :year ORDER BY constit_name";
            break;
        case "senate":
            $sql = "select s.senate_id as id, s.constituency as name, SUM(s.zec_votes) as voters, k.geometry,
                (SELECT p.colour FROM senators r join parties p on p.party_id = r.party_id where
                r.senate_id = s.senate_id ORDER BY r.zec_votes DESC LIMIT 1) as colour,
                CEIL(((SELECT r.zec_votes FROM senators r where r.senate_id = s.senate_id ORDER BY r.zec_votes DESC LIMIT 1) / SUM(s.zec_votes)) * 100) as won
                from senators s inner join senatekml k on s.constituency = k.senate
                WHERE s.year = :year GROUP BY s.constituency ORDER BY name";
            break;
    }

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("year", $year);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;

        writeResponse($items);
    } catch(PDOException $e) {
        writeError($e->getMessage());
    }
}

function getResultsSummary($race, $year, $constituency) {

    switch($race)
    {
        case "president":
            $sql = "select concat(m.mp_firstname, ' ', m.mp_surname) as name, p.colour, r.zec_votes as votes
                from mps m
                left outer join presidential_results r on r.mp_id = m.mp_id
                inner join parties p on m.party_id = p.party_id
                WHERE m.constit_id = 0 AND m.year = :year AND r.year = :year AND r.constit_id = :constituency
                ORDER BY r.zec_votes DESC";
            break;
        case "battleground":
        case "house":
            $sql = "select p.party_name as name, p.colour, r.zec_votes as votes
                from mps m
                left outer join house_results r on r.mp_id = m.mp_id
                inner join parties p on m.party_id = p.party_id
                WHERE r.year = :year AND m.year = :year AND m.constit_id = :constituency
                ORDER BY r.zec_votes DESC";
            break;
        case "senate":
            $sql = "select p.party_name as name, p.colour, s.zec_votes as votes
                from senators s
                inner join parties p on s.party_id = p.party_id
                WHERE s.year = :year AND s.senate_id = :constituency
                ORDER BY s.zec_votes DESC";
            break;
    }

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("year", $year);
        $stmt->bindParam("constituency", $constituency);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
        writeResponse($items);
    } catch(PDOException $e) {
        writeError($e->getMessage());
    }
}

function getResults($race, $year, $constituency) {

    switch($race)
    {
        case "president":
            $sql = "select concat(m.mp_firstname, ' ', m.mp_surname) as name, p.party_name as party, r.zec_votes as votes, p.colour,
                CEIL(r.zec_votes / (SELECT SUM(q.zec_votes) FROM presidential_results q WHERE q.year = :year AND q.constit_id = :constituency) * 100) as percent
                from mps m
                left outer join presidential_results r on r.mp_id = m.mp_id
                inner join parties p on m.party_id = p.party_id
                WHERE m.constit_id = 0 AND m.year = :year AND r.year = :year AND r.constit_id = :constituency
                ORDER BY r.zec_votes DESC";
            break;
        case "battleground":
        case "house":
            $sql = "select concat(m.mp_firstname, ' ', m.mp_surname) as name, p.party_name as party, r.zec_votes as votes,p.colour,
                CEIL(r.zec_votes / (SELECT SUM(q.zec_votes) FROM house_results q WHERE q.year = :year AND q.constit_id = :constituency) * 100) as percent
                from mps m
                left outer join house_results r on r.mp_id = m.mp_id
                inner join parties p on m.party_id = p.party_id
                WHERE r.year = :year AND m.year = :year AND m.constit_id = :constituency
                ORDER BY r.zec_votes DESC";
            break;
        case "senate":
            $sql = "select concat(s.firstname, ' ', s.surname) as name, p.party_name as party, s.zec_votes as votes,p.colour,
                CEIL(s.zec_votes / (SELECT SUM(q.zec_votes) FROM senators q WHERE q.year = s.year AND q.senate_id = s.senate_id) * 100) as percent
                from senators s
                inner join parties p on s.party_id = p.party_id
                WHERE s.year = :year AND s.senate_id = :constituency
                ORDER BY s.zec_votes DESC";
            break;
    }

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("year", $year);
        $stmt->bindParam("constituency", $constituency);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
        writeResponse($items);
    } catch(PDOException $e) {
        writeError($e->getMessage());
    }
}

function addCandidate() {
    $request = Slim::getInstance()->request();
    $data = json_decode($request->getBody());
    $sql = "INSERT INTO mps (mp_firstname, mp_surname, party_id, constit_id, year) VALUES ".
        "(:mp_firstname, :mp_surname, :party_id, :constit_id, :year)";
    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("mp_firstname", $data->firstname);
        $stmt->bindParam("mp_surname", $data->surname);
        $stmt->bindParam("party_id", $data->party);
        $stmt->bindParam("constit_id", $data->constituency);
        $stmt->bindParam("year", $data->year);
        $stmt->execute();
        $data->id = $db->lastInsertId();
        $db = null;

        writeResponse($data);
    } catch(PDOException $e) {
        writeError($e->getMessage());
    }
}

function updateCandidate($id) {
    $request = Slim::getInstance()->request();
    $data = json_decode($request->getBody());
    $sql = "UPDATE mps SET (mp_firstname = :mp_firstname, mp_surname = :mp_surname, party_id = :party_id, ".
        "constit_id = :constit_id, year = :year) WHERE mp_id = :id";
    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("mp_firstname", $data->firstname);
        $stmt->bindParam("mp_surname", $data->surname);
        $stmt->bindParam("party_id", $data->party);
        $stmt->bindParam("constit_id", $data->constituency);
        $stmt->bindParam("year", $data->year);
        $stmt->bindParam("id", $id);
        $stmt->execute();
        $db = null;

        writeResponse($data);
    } catch(PDOException $e) {
        writeError($e->getMessage());
    }
}

function deleteCandidate($id) {
    $sql = "DELETE FROM mps WHERE mp_id=:id";
    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("id", $id);
        $stmt->execute();
        $db = null;
    } catch(PDOException $e) {
        writeError($e->getMessage());
    }
}

// database setup
function getConnection() {
    // DEV
    $dbhost="127.0.0.1";
    $dbuser="dev";
    $dbpass="password";
    $dbname="sokwanele";


    $dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $dbh;
}

?>