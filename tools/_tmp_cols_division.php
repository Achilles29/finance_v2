<?php
$mysqli = new mysqli('127.0.0.1','root','','db_finance'); if($mysqli->connect_errno){echo $mysqli->connect_error; exit(1);} 
$rs=$mysqli->query("SHOW COLUMNS FROM mst_operational_division");
while($r=$rs->fetch_assoc()){ echo $r['Field']."|".$r['Type']."\n"; }
?>
