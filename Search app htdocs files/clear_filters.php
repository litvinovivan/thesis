<?php

/*
This file runs after an AJAX call when Clear All Filters button is clicked on the index.php page.
Clears filters.
*/

	session_start();
	
	if (isset($_SESSION['filters'])) {
		unset($_SESSION['filters']);
		unset($_SESSION['filter_string']);
		unset($_SESSION['filter_map']);
	}

	echo "done";

?>