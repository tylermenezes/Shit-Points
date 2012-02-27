<?php require_once("../lib/include.php"); ?>
<?php 
$ids = $_REQUEST['ids'];
$amount = $_REQUEST['amount'];
$delivery_id = $_REQUEST['delivery_id'];
	if(!$delivery_id) $delivery_id = md5(currentUser()->getId() . time() . rand(0,10000000));

if($ids && $amount && $amount > 0 && $amount < currentUser()->getShit()){
		foreach($ids as $id){
			$user = new User($id);
			Delivery::send($delivery_id, new User($id), currentUser(), $amount);						
		}
?>		
	<fb:success message="<?php __e('page send success'); ?>"/>
<?php }else{ ?>
	<?php if($amount < 0) : ?>
		<fb:error message="<?php __e('page send positive'); ?>" />
	<?php elseif($amount > currentUser()->getShit()) : ?>
		<fb:error message="<?php __e('page send too much'); ?>" />
	<?php endif; ?>

	<?php if($amount < 0 || $amount > currentUser()->getShit() || !$amount) : ?>
		<h1><?php __e('page send amount'); ?></h1>
		<form method="post">
			<input type="text" name="amount" value="<?=$amount?>" />
			<input type="submit" value="<?php __e('page send button'); ?>" />
			<input type="hidden" name="delivery_id" value="<?=$delivery_id?>" />
		</form>
	<?php else : ?>
		<fb:request-form
			method="post"
			action="send"
			content="<?php __e('page send content', currentUser()->getName(), $amount); ?><?php echo htmlentities("<fb:req-choice url=\"http://apps.facebook.com/awesome-points/recieve?delivery_id=" . $delivery_id . "\" label=\"" . __g('page send action') . "\"") ?>"
			type="<?php __e('page send type') ?>"
			invite="false">
			<div class="clearfix" style="padding-bottom: 10px;">
				<fb:multi-friend-selector
					actiontext="<?php __e('page send intro', $amount) ?>"
					max="1"
					bypass="cancel"
					email_invite="false"
					import_external_friends="false" />
				<input type="hidden" name="amount" value="<?=$amount?>" fb_protected="true" />
				<input type="hidden" name="delivery_id" value="<?=$delivery_id?>" fb_protected="true" />
			</div>
		</fb:request-form>

	<?php endif; ?>
<?php } ?>
