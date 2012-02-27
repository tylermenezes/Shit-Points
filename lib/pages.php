<?php
	$pages = array(
		new Page(__g('page home'), 'home'),
		new Page(__g('page clicker'), 'clicker'),
		new Page(__g('page send'), 'send'),
		new Page(__g('page leaderboard'), 'leaderboard'),
		new Page(__g('page download'), 'download', 'right'),
		new Page(__g('page upload'), 'upload', 'right'),
		new Page(__g('page about'), 'about', 'right'),
	);
	
	if(currentUser()->getPermissions()->canModerate()){
		$pages[] = new Page(__g('page generate'), 'generate', 'right');
	}
?>