<?php

require_once("../lib/db.php");

mysql_query(sprintf("UPDATE `users` SET `isactive` = 0 WHERE `fb_id` = %d;", $_REQUEST['fb_sig_user']), $sql);
print mysql_error();

?>