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
$app->get('/results/pr/:race/:year',  'getPRResults');

$app->get('/results/:race/:year/:constituency',  'getResults');
$app->get('/swing/:race/:constituency',  'getSwing');

//$app->post('/candidate', 'addCandidate');
//$app->put('/candidate/:id', 'updateCandidate');
//$app->delete('/candidate/:id',   'deleteCandidate');

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
                    CEIL((sum(r.zec_votes) / (select sum(t.zec_votes) FROM presidential_results t where t.year = :year )) * 100) AS percent,
                    p.colour AS colour, concat(m.mp_firstname,' ',m.mp_surname) AS name
                    from mps m
                    left outer join presidential_results r on r.mp_id = m.mp_id AND r.year = :year
                    join parties p on p.party_id = m.party_id
                    where m.constit_id = 0 AND m.year = :year group by m.mp_id
                    order by r.zec_votes desc";
            break;
        case "battleground":
        case "house":
            $sql = "select count(q.constit_id) as votes, q.name, q.colour from (select c.constit_id,
                    (SELECT p.colour FROM house_results r join mps m on r.mp_id = m.mp_id and m.year = :year join parties p on
                    p.party_id = m.party_id where r.zec_votes > 0 and r.constit_id = c.constit_id and r.year = :year ORDER BY r.zec_votes DESC LIMIT 1) as colour,
                    (SELECT p.party_name FROM house_results r join mps m on r.mp_id = m.mp_id and m.year = :year join parties p on
                    p.party_id = m.party_id where r.zec_votes > 0 and r.constit_id = c.constit_id and r.year = :year ORDER BY r.zec_votes DESC LIMIT 1) as name
                    from constituencies c) q
                    group by q.name";
            break;
        case "houselist":
            $sql = "select SUM(p.listseats) as votes, p.party as name, p.colour from vwprovinceresults p where
                    p.year = :year and p.party in (select c.party from candidates2013 c where c.province = p.id and c.seat = 'National Assembly Party List') group by p.party";
            break;
        case "senate":
            if ($year == '2008')
            {
                $sql = "select count(q.senate_id) as votes, q.name, q.colour from (select s.senate_id,
                    (SELECT p.colour FROM senators r join parties p on
                    p.party_id = r.party_id where r.senate_id = s.senate_id ORDER BY r.zec_votes DESC LIMIT 1) as colour,
                    (SELECT p.party_name FROM senators r join parties p on
                    p.party_id = r.party_id where r.senate_id = s.senate_id ORDER BY r.zec_votes DESC LIMIT 1) as name
                    from senators s
                    where s.year = :year GROUP BY senate_id) q
                    group by q.name";
            }
            else
            {
                $sql = "select SUM(p.listseats) as votes, p.party as name, p.colour from vwprovinceresults p where
                p.year = :year  and p.party in (select c.party from candidates2013 c where c.province = p.id and c.seat = 'Senate Party List') group by p.party";
            }

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
                p.party_id = m.party_id where r.constit_id = c.constit_id AND r.year = :year and r.zec_votes > 0 ORDER BY r.zec_votes DESC LIMIT 1) as colour,
                CEIL(((SELECT r.zec_votes FROM presidential_results r where r.constit_id = c.constit_id AND r.year = :year ORDER BY r.zec_votes DESC LIMIT 1) / v.votes) * 100) as won
                from constituencies c
                inner join constituencykml k on c.constit_name = k.constituency
                left outer join (SELECT q.constit_id, SUM(q.zec_votes) as votes FROM presidential_results q WHERE q.year = :year GROUP BY q.constit_id) v on v.constit_id = c.constit_id
                ORDER BY constit_name";
            break;
        case "battleground":
            $sql = "select id, name, voters, region_id, geometry, turnout, margin as won, margin,
                case
                    when margin >= 40  then '#CEEBEE'
                    when margin < 40 and margin >= 25 then '#9DB3E0'
                    when margin < 25 and margin >= 10 then '#6C7BD3'
                    when margin < 10 and margin >= 5 then '#3B43C5'
                    when margin < 5 then '#0A0BB8'
                end as colour
                from (select c.constit_id as id, c.constit_name as name, c.registered_voters as voters, c.region_id, k.geometry,
                CEIL((v.votes / c.registered_voters) * 100) as turnout,
                CEIL(((SELECT a.zec_votes FROM house_results a where a.zec_votes > 0 and a.constit_id = c.constit_id AND a.year = :year ORDER BY a.zec_votes DESC LIMIT 1) / v.votes) * 100) as won,
                CEIL(((SELECT b.zec_votes FROM house_results b where b.zec_votes < (SELECT a.zec_votes FROM house_results a where a.zec_votes > 0 and a.constit_id = c.constit_id AND a.year = 2008 ORDER BY a.zec_votes DESC LIMIT 1) and b.constit_id = c.constit_id AND b.year = 2008 ORDER BY b.zec_votes DESC LIMIT 1) / v.votes) * 100) as secondplace,
                CEIL(((SELECT a.zec_votes FROM house_results a where a.zec_votes > 0 and a.constit_id = c.constit_id AND a.year = :year ORDER BY a.zec_votes DESC LIMIT 1) / v.votes) * 100) - CEIL(((SELECT b.zec_votes FROM house_results b where b.zec_votes < (SELECT a.zec_votes FROM house_results a where a.zec_votes > 0 and a.constit_id = c.constit_id AND a.year = 2008 ORDER BY a.zec_votes DESC LIMIT 1) and b.constit_id = c.constit_id AND b.year = 2008 ORDER BY b.zec_votes DESC LIMIT 1) / v.votes) * 100) as margin
                from constituencies c inner join constituencykml k on c.constit_name = k.constituency
                left outer join (SELECT q.constit_id, SUM(q.zec_votes) as votes FROM house_results q WHERE q.year = :year GROUP BY q.constit_id) v on v.constit_id = c.constit_id
                ORDER BY constit_name) g order by margin asc";
            break;
        case "house":
            $sql = "select c.constit_id as id, c.constit_name as name, c.registered_voters as voters, c.region_id, k.geometry,
                CEIL((v.votes / c.registered_voters) * 100) as turnout,
                (SELECT p.colour FROM house_results r join mps m on r.mp_id = m.mp_id join parties p on
                p.party_id = m.party_id where r.zec_votes > 0 and  r.constit_id = c.constit_id AND r.year = :year ORDER BY r.zec_votes DESC LIMIT 1) as colour,
                CEIL(((SELECT r.zec_votes FROM house_results r where r.zec_votes > 0 and r.constit_id = c.constit_id AND r.year = :year ORDER BY r.zec_votes DESC LIMIT 1) / v.votes) * 100) as won
                from constituencies c inner join constituencykml k on c.constit_name = k.constituency
                left outer join (SELECT q.constit_id, SUM(q.zec_votes) as votes FROM house_results q WHERE q.year = :year GROUP BY q.constit_id) v on v.constit_id = c.constit_id
                ORDER BY constit_name";
            break;
        case "houselist":
            $sql = "select p.province as name, p.province as id, null as voters, p.geometry,
                    (SELECT q.colour FROM vwprovinceresults q where q.votes > 0 AND  q.id = p.province AND q.year = :year ORDER BY q.votes DESC LIMIT 1) as colour
                    from provincekml p ORDER BY p.province";
            break;
        case "senate":
            if ($year == '2008')
            {
                $sql = "select s.senate_id as id, s.constituency as name, SUM(s.zec_votes) as voters, k.geometry,
                    (SELECT p.colour FROM senators r join parties p on p.party_id = r.party_id where
                    r.senate_id = s.senate_id ORDER BY r.zec_votes DESC LIMIT 1) as colour,
                    CEIL(((SELECT r.zec_votes FROM senators r where r.senate_id = s.senate_id ORDER BY r.zec_votes DESC LIMIT 1) / SUM(s.zec_votes)) * 100) as won
                    from senators s inner join senatekml k on s.constituency = k.senate
                    WHERE s.year = :year GROUP BY s.constituency ORDER BY name";
            }
            else
            {
                $sql = "select p.province as name, p.province as id, null as voters, p.geometry,
                    (SELECT q.colour FROM vwprovinceresults q where q.votes > 0 AND q.id = p.province AND q.year = :year ORDER BY q.votes DESC LIMIT 1) as colour
                    from provincekml p ORDER BY p.province";            }
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
                left outer join presidential_results r on r.mp_id = m.mp_id AND r.constit_id = :constituency
                inner join parties p on m.party_id = p.party_id
                WHERE m.constit_id = 0 AND m.year = :year
                ORDER BY r.zec_votes DESC";
            break;
        case "battleground":
            $sql = "select r.party as name, r.colour, concat(r.percent,'%') as votes from vwhouseresults r where id = :constituency and year = :year GROUP BY party order by votes desc";
            break;
        case "house":
        $sql = "select r.party as name, r.colour, r.votes as votes from vwhouseresults r where id = :constituency and year = :year GROUP BY party order by votes desc";
            break;
        case "houselist":
            $sql = "select p.party as name, p.colour, p.listseats as votes from vwprovinceresults p where
                p.id = :constituency and p.year = :year and p.party in (select c.party from candidates2013 c where c.province = p.id and c.seat = 'National Assembly Party List') ORDER BY p.percent DESC";
            break;
        case "senate":
            if ($year == '2008') {
                $sql = "select p.party_name as name, p.colour, s.zec_votes as votes
                    from senators s
                    inner join parties p on s.party_id = p.party_id
                    WHERE s.year = :year AND s.senate_id = :constituency
                    ORDER BY s.zec_votes DESC";
            }
            else
            {
                $sql = "select p.party as name, p.colour, p.listseats as votes from vwprovinceresults p where
                    p.id = :constituency and p.year = :year and p.party in (select c.party from candidates2013 c where c.province = p.id and c.seat = 'Senate Party List')  order by votes desc";
            }
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

function getPRResults($race, $year)
{
    $r = $race == 'senate' ? 'Senate Party List' : 'National Assembly Party List';

    $sql = "select p.id as province, p.party as name, p.colour, p.listseats as votes from vwprovinceresults p where
            p.year = :year and p.party in (select c.party from candidates2013 c where c.province = p.id and c.seat = '{$r}')  order by p.id, p.votes desc";

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

function getResults($race, $year, $constituency) {

    switch($race)
    {
        case "president":
            $sql = "select concat(m.mp_firstname, ' ', m.mp_surname) as name, p.party_name as party, r.zec_votes as votes, p.colour,
                concat(CEIL(r.zec_votes / (SELECT SUM(q.zec_votes) FROM presidential_results q WHERE q.year = :year AND q.constit_id = :constituency) * 100),'%') as percent
                from mps m
                left outer join presidential_results r on r.mp_id = m.mp_id AND r.year = :year AND r.constit_id = :constituency
                inner join parties p on m.party_id = p.party_id
                WHERE m.constit_id = 0 AND m.year = :year
                ORDER BY r.zec_votes DESC";
            break;
        case "battleground":
        case "house":
            $sql = "select r.name, r.party, r.votes, r.colour, concat(r.percent,'%') as percent from vwhouseresults r where id = :constituency and year = :year order by votes desc";
            break;
        case "houselist":
            $sql = "select concat(c.firstname, ' ', c.surname) as name, p.party_name as party, null as votes, p.colour, :year as year,
                    c.listposition as percent
                    from candidates2013 c
                    inner join parties p on c.partyid = p.party_id
                    WHERE c.province = :constituency and c.seat = 'National Assembly Party List'
                    and c.listposition <= (select case when q.listseats is null then 6 else q.listseats end as listseats from vwprovinceresults q where q.party = p.party_name and q.id = :constituency and q.year = :year)
                    order by party, listposition";
            break;

        case "senate":
            if ($year == '2008') {
                $sql = "select concat(s.firstname, ' ', s.surname) as name, p.party_name as party, s.zec_votes as votes, p.colour,
                    concat(CEIL(s.zec_votes / (SELECT SUM(q.zec_votes) FROM senators q WHERE q.year = s.year AND q.senate_id = s.senate_id) * 100),'%')as percent
                    from senators s
                    inner join parties p on s.party_id = p.party_id
                    WHERE s.year = :year AND s.senate_id = :constituency
                    ORDER BY s.zec_votes DESC";
            }
            else
            {
                $sql = "select concat(c.firstname, ' ', c.surname) as name, p.party_name as party, null as votes, p.colour, :year as year,
                    c.listposition as percent
                    from candidates2013 c
                    inner join parties p on c.partyid = p.party_id
                    WHERE c.province = :constituency and c.seat = 'Senate Party List'
                    and c.listposition <= (select case when q.listseats is null then 6 else q.listseats end as listseats from vwprovinceresults q where q.party = p.party_name and q.id = :constituency and q.year = :year)
                    order by party, listposition";
            }
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

function getSwing($race, $constituency) {

    switch($race)
    {
        case "president":
            $sql = "select a.colour, a.name, a.percent as SwingTo, b.percent as SwingFrom, b.percent - a.percent as swing from
                (select r.party as name, r.colour, r.percent from vwpresidentresults r where id = :constituency and year = 2013 order by votes desc LIMIT 1) a
                join
                (select r.party as name, r.colour, r.percent from vwpresidentresults r where id = :constituency and year = 2008 order by votes desc) b on a.name = b.name";
            break;
        case "battleground":
        case "house":
            $sql = "select a.colour, a.name, a.percent as SwingTo, b.percent as SwingFrom, b.percent - a.percent as swing from
                (select r.party as name, r.colour, r.percent from vwhouseresults r where id = :constituency and year = 2013 order by votes desc LIMIT 1) a
                join
                (select r.party as name, r.colour, r.percent from vwhouseresults r where id = :constituency and year = 2008 order by votes desc) b on a.name = b.name";
            break;
    }

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
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
    include 'conn.php';
    $dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $dbh;
}

?>