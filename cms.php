<?php
ini_set('auto_detect_line_endings', true);

$csv = array();
//connect to the database
//$connect = mysql_connect("127.0.0.1","dev","password");
//mysql_select_db("sokwanele",$connect); //select the table

// QUERY TO GENERATE CSV
//select r.constit_id, r.mp_id, concat(m.mp_firstname, ' ', m.mp_surname) as name, p.party_name as party, c.constit_name as constituency, c.province, r.zec_votes as votes
//from presidential_results || house_results r
//join mps m on m.mp_id = r.mp_id
//join parties p on m.party_id = p.party_id
//join constituencies c on c.constit_id = r.constit_id
//where m.year = 2013
//order by r.constit_id, r.mp_id

// check there are no errors
if($_FILES['csv']['error'] == 0){

    $table = $_POST['type'] == 'president' ? 'presidential_results' : 'house_results';

    //get the csv file
    $file = $_FILES[csv][tmp_name];
    $handle = fopen($file,"r");

    //loop through the csv file and insert into database
    do {
        if ($data[0]) {
            $constitID = $data[0];
            $mpID = $data[1];
            $votes = $data[6];

            mysql_query(sprintf("UPDATE %s SET zec_votes = %d where year = 2013 AND constit_id = %d and mp_id = %d",
                $table,
                $votes,
                $constitID,
                $mpID
            ));
        }

    } while ($data = fgetcsv($handle));

    //redirect
    header('Location: success.html'); die;
}
?>
