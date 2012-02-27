<?php require_once("../lib/include.php"); ?>
<?php
	if($_REQUEST["amount"]){
		currentUser()->pushShit($_REQUEST["amount"]);
?>
		<fb:success message="<?php __e('page upload success', $_REQUEST["amount"]); ?>" />
<?php
	}else{
?>
	<?php __e('page upload amount'); ?>
	<form method="post">
		<input type="text" name="amount" value="0" /> <input type="submit" value="Upload!" />
	</form>
<?php
	}
?>
