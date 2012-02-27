<?php
	require_once("common.php");

	require_once("pages.php");
	
	Viewport::drawHeader(__g('shit points html'));
	Viewport::drawTabs($pages, Viewport::getCurrentPage());
?>
