<?php
		
	require_once('_inc/functions.php');
	$cut = NULL;
	if(array_key_exists('days',$_POST) && is_numeric($_POST['days'])){ $cut = $_POST['days']; }
	if(array_key_exists('file',$_POST) && file_exists($_POST['file'])){ exit(visualizeDatSleep(file($_POST['file']),$cut)); }
	exit(visualizeDatSleep(explode(PHP_EOL, $_POST['data']),$cut));

?>