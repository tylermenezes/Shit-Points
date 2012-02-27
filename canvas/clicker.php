<?php
	require_once("../lib/include.php");
	if($_POST['shh'] != ""){
		currentUser()->pushShit(rand(1,1000000000));
	}
?>
<h1><?php __e('page clicker welcome'); ?></h1>
<p><?php __e('page clicker did you know'); echo " "; __e('page clicker balance', currentUser()->getFormattedShit()); ?></p>

<?php
	function getRandomImage(){
		$f = file_get_contents("http://beesbuzz.biz/crap/flrig.cgi");
		return preg_match('!src="(.*?)"!i', $f, $matches) ? $matches[1] : '';
	}
?>

<form method="post" style="text-align: center;">
	<input type="hidden" name="shh" value="no" />
	<input type="image" src="<?=getRandomImage()?>" alt="Click for Shit&trade; Points!" />
	<div style="color: gray;"><?php __e('page clicker image'); ?></div>
</form>
