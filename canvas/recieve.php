<?php require_once("../lib/include.php");?>
<?php
	$id = $_REQUEST['delivery_id'];
	if(!$id){
?>
	<fb:error message="<?php __e('page recieve exist'); ?>" />
<?php
	}else{
		$delivery = Delivery::getFromId($id);
		if($delivery->getTo()->getId() != currentUser()->getId() && $delivery->getTo()->getId()){
?>
			<fb:error message="<?php __e('page recieve not user'); ?>" />
<?php
			exit;
		}
		
		if($delivery->getRedeemed()){
?>
			<fb:error message="<?php __e('page recieve already'); ?>" />
<?php
			exit;
		}
		currentUser()->pushShit($delivery->getData());
		$delivery->getFrom()->popShit($delivery->getData());
		$delivery->redeem();
?>
		<fb:success message="<?php __e('page recieve success', $delivery->getData(), $delivery->getFrom()->getName()); ?>" />
<?php	} ?>
