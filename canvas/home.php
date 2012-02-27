<?php require_once("../lib/include.php"); ?>
<?php
	if(currentUser()->hasUnreadNotifications()){
		foreach(currentUser()->getUnreadNotifications() as $notification){
?>
			<fb:error message="<?php __e($notification->getMessage())?>" />
<?php
			$notification->markRead();
		}
		currentUser()->resetCounter();
	}else if(currentUser()->getShit() == 0){
?>
	<fb:error message="<?php __e('page home none') ?>" />
<?php
	}else if(currentUser()->getShit() < 0){
?>
	<fb:error message="<?php __e('page home negative') ?>" />
<?php
	}else{
?>
		<div style="border:1px solid gray;padding:.25em 1em;">
			<p><strong><?php __e('page home info title'); ?></strong></p>
			<p><?php __e('page home info text'); ?></p>
		</div>
<?php	} ?>
<div style="text-align:center;padding:10px;">
</div>
<blockquote style="text-align: center;font-size:larger;"><?php __e('quote') ?></blockquote>
