<?php require_once("../lib/include.php"); ?>

<?php
	$r = Sql::get("SELECT * FROM `users` ORDER BY `points` DESC;");
	
	foreach($r as $row){
		if($row["fb_id"] <= 0 || substr($row["fb_id"], 0, 3) == "api"){
			continue;
		}
		$n[$row["fb_id"]] = $row["points"];
	}
	arsort($n);

	$first = true;
	$i = 0;
	foreach($n as $id=>$points){
	if($i++ > 10){
		break;
	}
	$u = new User($id);
?>

	<div style="padding: 10px;">
		<div style="float: left; padding-right: 10px; min-height: 75px;">
			<div><fb:profile-pic uid="<?=$u->getId()?>" size="thumb" /></div>
		</div>
		<div style="font-weight: bold;padding-bottom:1em;">
			<fb:name uid="<?=$u->getId()?>" capitalize="true" />
			<?php
				if($first){
					$first = false;
					echo " (";
					__e('page leaderboard title ' . $u->getGender());
					echo ")";
				}
                	?>
		</div>
		<div style="font-size: 2em; font-weight: bold;overflow:auto;">
			<?=$u->getFormattedShit() ?>
		</div>
		<div style="padding-top: 1em;">
			<?php if($u->getShit() > 0 && $u->getId() != currentUser()->getId()){ ?>
				<a href="steal?from=<?=$u->getId()?>"><?php __e('page leaderboard steal ' . $u->getGender()) ?></a>	
			<?php } ?>
		</div>
		<hr style="clear:both" />
	</div>

<?php
	}
?>
