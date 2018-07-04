<?php
	
	require('_inc/functions.php');
	exit(sprintf(file_get_contents('index.html'),visualizeDatSleep(file("csv/Austin_sleep.csv"),10)));
	
?>
