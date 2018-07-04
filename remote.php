<?php
		
	require_once('_inc/functions.php');
	$cut = NULL;
	if(array_key_exists('days',$_POST) && is_numeric($_POST['days'])){ $cut = $_POST['days']; }
	echo visualizeDatSleep(file($_POST['url']),$cut);

?>