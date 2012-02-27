<?php require_once("../lib/include.php"); ?>
<?php
	$from = new User($_REQUEST["from"]);
	$amount = $_REQUEST["amount"];
	if($amount && $amount <= $from->getShit()){
		currentUser()->pushShit($amount);
		$from->popShit($amount);
	
		$from->sendNotification(currentUser(), sprintf('{{notification stolen|%s|%s|%s}}', ($from->getShit() == 0)? 'All' : $amount, currentUser()->getName(), "steal?from=" . currentUser()->getId()));
?>
		<fb:success message="<?php __e('page steal success') ?>" />
<?php	}else{ ?>
		<?php if($amount){ ?>
			<fb:error message="<?php __e('page steal too many'); ?>" />
		<?php } ?>
		<?php __e('page steal amount', $from->getName()) ?>
		<form method="POST">
			<input type="hidden" name="from" value="<?=$from->getId();?>" />
			<input type="text" name="amount" value="<?=$from->getShit();?>" />
			<input type="submit" value="<?php __e('page steal'); ?>" />
		</form>
<?php	} ?>
