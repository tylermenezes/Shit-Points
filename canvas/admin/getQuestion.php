<?php
	require_once("../../lib/include.php");
	requireDebug();
?>
<?php
	$ids = $_REQUEST['id'];
	$ids = str_replace("\r\n", ",", $ids);
	$ids = str_replace("\n", ",", $ids);
	$ids = str_replace("\r", ",", $ids);
	$ids = str_replace(" ", ",", $ids);
?>
<h1><?__e("admin page getQuestion byId")?></h1>
<form method="post">
	<textarea name="id"><?=$ids?></textarea>
	<input type="submit" value="<?__e("admin page getQuestion byId submit")?>" />
</form>
<?__e("admin page getQuestion byId delim")?>

<?php
if($ids){
?>
<ol>
<?php
	foreach(explode(",", $ids) as $id){

		$question = Question::getFullQuestionById($id);
?>
<li>
	<h2><?=$question->getText();?> <em>(<?=$question->getId();?>)</em></h2>
	<ol>
		<?php
			$i = 1;
			foreach($question->getAnswers() as $answer){
		?>

		<li><?=$answer->getText();?> <em>(-<?=$answer->getId();?>)</em></li>
		<?php
			}
		?>
	</ol>
</li>
<?php
	}
?>
</ol>
<?php
}
?>
