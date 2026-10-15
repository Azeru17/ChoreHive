<?php
require 'config.php';
$r = $conn->query("SHOW CREATE TABLE users");
echo $r->fetch_assoc()['Create Table'] . "\n";
