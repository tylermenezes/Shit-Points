<?php require_once("../lib/include.php");?>
<?php if(currentUser()->getPermissions()->canModerate()){ ?>
	<?php if($_POST['amount'] && $_POST['number']){ ?>
		<?php if($_POST['list']){ ?>
			<ul>
				<?php for($i = 0; $i < $_POST['number']; $i++){
						$code = md5($_POST['amount'] . time() . rand(0,5000*$_POST['amount']) . $i);
						Delivery::send($code, new User(0), currentUser(), $_POST['amount']);
					?>
						<li><?php echo $code; ?></li>
				<?php } ?>
			</ul>
		<?php }else{ ?>
			<table width="100%">
				<tr>
				<?php for($i = 0; $i < $_POST['number']; $i++){
						$code = md5($_POST['amount'] . time() . rand(0,5000*$_POST['amount']) . $i);
						Delivery::send($code, new User(0), currentUser(), $_POST['amount']);
						if($i % 3 == 0){
							echo "</tr><tr>";
						}
					?>
						<td style="text-align:center;margin:10px;padding:5px;">
							<div style="width:100%;">
								<img src="http://shitpoints.arson-media.com/logocard.jpg" />
							</div>
							<div style="font-size:2em;font-weight:bold;">
								<?php echo (strlen($_POST['amount']) > 5)? substr($_POST['amount'], 0, 5) . " x 10<sup>" . (strlen($_POST['amount'])-5) . "</sup>" : $_POST['amount'];  __e(" {{shit points html}}"); ?>
							</div>
							<div style="padding-bottom: 10px">
								<?php __e('page generate instructions'); ?>
							</div>
							<div style="font-size:.85em;font-family:monospace">
								<?php echo $code; ?>
							</div>
						</td>
				<?php } ?>
				</tr>
			</table>
		<?php } ?>
	<?php }else{ ?>
		<?php __e('page generate intro'); ?>
		<form method="post">
			<?php __e('page generate amount'); ?><input type="text" name="amount" /><br />
			<?php __e('page generate number'); ?><input type="text" name="number" /><br />
			<input type="checkbox" name="list" value="yes" /><?php __e('page generate list') ?><br />
			<input type="submit" value="<?php __e('page generate button'); ?>" />
		</form>
	<?php } ?>
<?php }else{ ?>
	<?php __e('permission error'); ?>
<?php } ?>
