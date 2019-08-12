<?php

/*
This file is activated by AJAX call from index.php when user cliks on a chart.
It adds the clicked chart concepts to the search string with AND operator to act as a filter.
- $_SESSION['filter_string'] contains the clicked chart concepts string that's added to the search string
- $_SESSION['filters'] is fill the "Applied filters" section above the charts.
- $_SESSION['filter_map'] is used in the remove_markup.php to apply relevant class to found filter concepts for highlighting with different colours.
*/

	session_start();

	$category = $_POST['category'];
	$code = $_POST['code'];
	$name = $_POST['name'];

	$received_filter = ['code' => $code, 'name' => $name];

	// need to add to existing filters if any
	//	Case: No previous filters set
	if (!isset($_SESSION['filters'])) {
		$_SESSION['filters'][$category] = [$received_filter];
		$_SESSION['filter_string'] = $code;
	} else {
		// Case: Category already in the array 
		if (array_key_exists($category, $_SESSION['filters'])) {
			// check that code is not in the array already
			$new_code = True;
			foreach ($_SESSION['filters'][$category] as $concept_values) {
				if (in_array($code, $concept_values)) {
					$new_code = False;
					break; 	//do nothing, code already in filters
				}
			}
			if ($new_code) {
				array_push($_SESSION['filters'][$category], $received_filter);
				$_SESSION['filter_string'] .= " AND " . $code;
			}
		} else {
			// New category
			$_SESSION['filters'][$category] = [$received_filter];
			$_SESSION['filter_string'] .= " AND " . $code;
		}	
	}
	// This map will be used for highlighting 
	$_SESSION['filter_map'][$code] = $category;

	echo "done";
?>