<?php

require_once("../lib/db.php");
require_once("../lib/fb.php");

$uid = $_REQUEST['fb_sig_user'];

mysql_query(sprintf("INSERT IGNORE INTO `users` VALUES (%d, 10);", $uid), $sql);

?>
