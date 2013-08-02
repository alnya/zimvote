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

    $app = \Slim\Slim::getInstance();

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
            $sql = "select SUM(p.listseats) as votes, p.party as name, p.colour,
                    CEIL((sum(p.listseats) / 60) * 100) AS percent
                    from vwprovinceresults p where
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
                $sql = "select SUM(p.listseats) as votes, p.party as name, p.colour,
                      CEIL((sum(p.listseats) / 60) * 100) AS percent
                      from vwprovinceresults p where
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

        $hash = implode('', array_map(function($item) { return $item->votes; }, $items));
        $app->etag('party'.$race.$year.$hash);


        writeResponse($items);
    } catch(PDOException $e) {
        writeError($e->getMessage());
    }
}

function getConstituencies($race, $year) {

    $app = \Slim\Slim::getInstance();

    $registeredVotersCol = $year == '2013' ? 'c.registered_voters2013' : 'c.registered_voters';

    switch($race)
    {
        case "president":
            $sql = "select c.constit_id as id, c.constit_name as name, {$registeredVotersCol} as voters, c.region_id, k.geometry,
                CEIL((v.votes / {$registeredVotersCol}) * 100) as turnout,
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
                from (select c.constit_id as id, c.constit_name as name, {$registeredVotersCol} as voters, c.region_id, k.geometry,
                CEIL((v.votes / {$registeredVotersCol}) * 100) as turnout,
                CEIL(((SELECT a.zec_votes FROM house_results a where a.zec_votes > 0 and a.constit_id = c.constit_id AND a.year = :year ORDER BY a.zec_votes DESC LIMIT 1) / v.votes) * 100) as won,
                CEIL(((SELECT b.zec_votes FROM house_results b where b.zec_votes < (SELECT a.zec_votes FROM house_results a where a.zec_votes > 0 and a.constit_id = c.constit_id AND a.year = 2008 ORDER BY a.zec_votes DESC LIMIT 1) and b.constit_id = c.constit_id AND b.year = 2008 ORDER BY b.zec_votes DESC LIMIT 1) / v.votes) * 100) as secondplace,
                CEIL(((SELECT a.zec_votes FROM house_results a where a.zec_votes > 0 and a.constit_id = c.constit_id AND a.year = :year ORDER BY a.zec_votes DESC LIMIT 1) / v.votes) * 100) - CEIL(((SELECT b.zec_votes FROM house_results b where b.zec_votes < (SELECT a.zec_votes FROM house_results a where a.zec_votes > 0 and a.constit_id = c.constit_id AND a.year = 2008 ORDER BY a.zec_votes DESC LIMIT 1) and b.constit_id = c.constit_id AND b.year = 2008 ORDER BY b.zec_votes DESC LIMIT 1) / v.votes) * 100) as margin
                from constituencies c inner join constituencykml k on c.constit_name = k.constituency
                left outer join (SELECT q.constit_id, SUM(q.zec_votes) as votes FROM house_results q WHERE q.year = :year GROUP BY q.constit_id) v on v.constit_id = c.constit_id
                ORDER BY constit_name) g order by margin asc";
            break;
        case "house":
            $sql = "select c.constit_id as id, c.constit_name as name, {$registeredVotersCol} as voters, c.region_id, k.geometry,
                CEIL((v.votes / {$registeredVotersCol}) * 100) as turnout,
                (SELECT p.colour FROM house_results r join mps m on r.mp_id = m.mp_id join parties p on
                p.party_id = m.party_id where r.zec_votes > 0 and  r.constit_id = c.constit_id AND r.year = :year ORDER BY r.zec_votes DESC LIMIT 1) as colour,
                CEIL(((SELECT r.zec_votes FROM house_results r where r.zec_votes > 0 and r.constit_id = c.constit_id AND r.year = :year ORDER BY r.zec_votes DESC LIMIT 1) / v.votes) * 100) as won
                from constituencies c inner join constituencykml k on c.constit_name = k.constituency
                left outer join (SELECT q.constit_id, SUM(q.zec_votes) as votes FROM house_results q WHERE q.year = :year GROUP BY q.constit_id) v on v.constit_id = c.constit_id
                ORDER BY constit_name";
            break;
        case "houselist":
            $sql = "select p.province as name, p.province as id, null as voters, p.geometry,
                    (SELECT q.colour FROM vwprovinceresults q where q.complete = 1 and q.votes > 0 AND  q.id = p.province AND q.year = :year ORDER BY q.votes DESC LIMIT 1) as colour
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
                    (SELECT q.colour FROM vwprovinceresults q where q.complete = 1 and q.votes > 0 AND q.id = p.province AND q.year = :year ORDER BY q.votes DESC LIMIT 1) as colour
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

        $hash = implode('', array_map(function($item) { return $item->colour; }, $items));
        $app->etag('constituency'.$race.$year.$hash);


        writeResponse($items);
    } catch(PDOException $e) {
        writeError($e->getMessage());
    }
}

function processListSeats($items)
{
    $totalSeats = 0;
    $hasVotes = false;
    foreach($items as $row)
    {
        if ($row->votes != 0)
        {
            $hasVotes= true;
        }
        $roundedVotes = floor($row->votes);
        $row->srcVotes = $row->votes;
        $row->roundedVotes = $roundedVotes;
        $row->remainder = $row->votes - $roundedVotes;
        $row->votes = $roundedVotes;
        $totalSeats = $totalSeats + $row->roundedVotes;
    }

    if ($hasVotes && $totalSeats < 6)
    {
        usort($items, 'sortByRemainder');

        foreach($items as $row)
        {
            if ($totalSeats < 6)
            {
                $row->votes = $row->votes + 1;
                $totalSeats = $totalSeats + $row->votes;
            }
        }
    }

    usort($items, 'sortByVotes');
}

function sortByRemainder($a, $b) {
    return $a->remainder - $b->remainder;
}

function sortByVotes($a, $b) {
    return $a->votes - $b->votes;
}

function getResultsSummary($race, $year, $constituency) {

    $app = \Slim\Slim::getInstance();

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
            $sql = "select r.party as name, r.colour, concat(r.percent,'%') as votes from vwhouseresults r where id = :constituency and year = :year GROUP BY party order by percent desc";
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
                $sql = "select p.party as name, p.colour, p.listseats as votes, p.percent, :year as year from vwprovinceresults p where
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

        if (($year == '2013' && $race == 'senate') || $race == 'houselist')
        {
            processListSeats($items);
        }

        $hash = implode('', array_map(function($item) { return $item->votes; }, $items));
        $app->etag('summary'.$race.$year.$constituency.$hash);

        writeResponse($items);
    } catch(PDOException $e) {
        writeError($e->getMessage());
    }
}

function getPRResults($race, $year)
{
    $app = \Slim\Slim::getInstance();

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

        $hash = implode('', array_map(function($item) { return $item->votes; }, $items));
        $app->etag('pr'.$race.$year.$hash);

        writeResponse($items);
    } catch(PDOException $e) {
        writeError($e->getMessage());
    }
}

function getResults($race, $year, $constituency) {

    $app = \Slim\Slim::getInstance();

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
            $sql = "select concat(c.firstname, ' ', c.surname) as name, p.party_name as party, q.listseats as votes, p.colour, :year as year,
                    c.listposition as percent
                    from candidates2013 c
                    inner join parties p on c.partyid = p.party_id
                    join  vwprovinceresults q on q.party = p.party_name and q.id = c.province and q.year = :year
                    WHERE c.province = :constituency and c.seat = 'National Assembly Party List'
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
                $sql = "select concat(c.firstname, ' ', c.surname) as name, p.party_name as party, q.listseats as votes, p.colour, :year as year,
                    c.listposition as percent
                    from candidates2013 c
                    inner join parties p on c.partyid = p.party_id
                    join  vwprovinceresults q on q.party = p.party_name and q.id = c.province and q.year = :year
                    WHERE c.province = :constituency and c.seat = 'Senate Party List'
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

        if (($year == '2013' && $race == 'senate') || $race == 'houselist') {

            $r = $race == 'senate' ? 'Senate Party List' : 'National Assembly Party List';

            $sql = "select p.party as name, p.listseats as votes from vwprovinceresults p where
                p.id = :constituency and p.year = :year and p.party in (select c.party from candidates2013 c where c.province = p.id and c.seat = '{$r}') ORDER BY p.percent DESC";

            $stmt = $db->prepare($sql);
            $stmt->bindParam("year", $year);
            $stmt->bindParam("constituency", $constituency);
            $stmt->execute();
            $summaryList = $stmt->fetchAll(PDO::FETCH_OBJ);
            processListSeats($summaryList);

            $filteredList = array();
            foreach ($summaryList as $summary) {
                if ($summary->votes > 0) {
                    foreach ($items as $row) {
                        if ($row->party == $summary->name && $row->percent <= $summary->votes) {
                            $filteredList[] = $row;
                        }
                    }
                }
            }

            if (count($filteredList) > 0) {
                $items = $filteredList;
            }
        }

        $db = null;


        $hash = implode('', array_map(function($item) { return $item->percent; }, $items));
        $app->etag('results'.$race.$year.$constituency.$hash);

        writeResponse($items);
    } catch(PDOException $e) {
        writeError($e->getMessage());
    }
}

function getSwing($race, $constituency) {

    $app = \Slim\Slim::getInstance();

    $r = $race == 'president' ? 'vwpresidentresults' : 'vwhouseresults';

    $sql = "select r.party as name, r.year, r.colour, r.percent from {$r} r where id = :constituency order by year desc, votes desc";

    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("constituency", $constituency);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_OBJ);

        $response = array();
        $first2013 = null;
        $second2013 = null;
        $first2008 = null;
        $second2008 = null;
        foreach($items as $row)
        {
            if ($row->year == 2013 && is_null($first2013))
            {
                $first2013 = $row;
            }
            else if ($row->year == 2013 && is_null($second2013))
            {
                $second2013 = $row;
            }
            else if ($row->year == 2008 && $row->name == $first2013->name && is_null($first2008))
            {
                $first2008 = $row;
            }
            else if ($row->year == 2008 && $row->name == $second2013->name &&  is_null($second2008))
            {
                $second2008 = $row;
            }
        }

        if ($first2013 != null && $second2013 != null and $first2008 != null && $second2008 != null)
        {
            $response['fromcolor'] = $second2008->colour;
            $response['tocolour'] = $first2013->colour;
            $response['swing'] = ((($first2013->percent - $first2008->percent) + ($second2013->percent - $second2008->percent)) / 2);

        }

        $app->etag('swing'.$race.$constituency.$response['swing']);

        $db = null;
        writeResponse($response);
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