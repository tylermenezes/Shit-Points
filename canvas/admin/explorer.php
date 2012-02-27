<?php
	require_once("../../lib/include.php");
	requireDebug();
?>
<h1><?__e("admin page explorer")?></h1>
<?php

?>
<?php
$id = $_POST['id'];
if(!$id){
	$id = 1;
}

$question = Question::getFullQuestionById($id);
?>
<form method="post" style="float:left;display:block;">
	<input type="hidden" name="id" value="<?=$id - 1?>" />
	<input type="submit" value="<?__e("admin page explorer previous")?>" />
</form>
<form method="post" style="float:left;display:block;">
	ID: <input type="text" name="id" value="<?=$id?>" />
	<input type="submit" value="<?__e("admin page explorer go")?>" />
</form>
<form method="post" style="float:right;display:block;">
	<input type="hidden" name="id" value="<?=$id + 1?>" />
	<input type="submit" value="<?__e("admin page explorer next")?>" />
</form>
<hr style="clear:both;" />
<h2><?=$question->getText();?></h2>
<ol>
	<?php
		$i = 1;
		foreach($question->getAnswers() as $answer){
	?>

	<li><?=$answer->getText();?></li>
	<?php
		}
	?>
</ol>