<?php
	require_once("Page.php");
	
	class Viewport{
		public static function drawHeader($title){
?>
			<div style="text-align:center;"><img src="http://shitpoints.arson-media.com/logo.jpg" /></div>
<?php
		}
		
		public static function drawTabs($pages, $current = null){
			echo "<fb:tabs>";
			foreach($pages as $page){
				echo "<fb:tab-item" .
						" href='/awesome-points/" . $page->getUri() . "'" .
						" title='" . $page->getTitle() . "'" .
						(($page->getUriBase() == $current)? " selected='true'" : "") .
						" align='" . $page->getAlign() . "'" .
				"/> ";
			}
			echo "</fb:tabs>";
?>
			<div style="padding-top:1em;font-size:1.2em; font-weight:bold;text-transform:uppercase;">
				<span style="float:left;">Sup, <?=currentUser()->getName()?></span>
				<span style="float:right;"><img src="http://shitpoints.arson-media.com/icon.png" style="padding-right:2px;"/><?=currentUser()->getShortShit()?> <?php __e('shit points html'); ?></span>
			</div>
			<hr style="clear:both;color:black;border:2px solid black;color:black;background-color:black;" />
<?php
		}
		
		public static function getCurrentPage(){
			$uri = $_SERVER['REQUEST_URI'];
			$uri = explode('/', $uri);
			return $uri[2];			
		}
	}
?>

