<?php

	require 'vendor/autoload.php';

	$client = Elasticsearch\ClientBuilder::create()->build();

	// File used to get display names of concepts in the aggreagation charts. The file is created during concept extraction from original documents in the python script extract.py.
	$display_names = file_get_contents("display_names.json");
	$display_names = json_decode($display_names, true);
	
	// Index name for index creation and indexing and subsequent searching.
	$index_name = 'relations_annotated_with_aggs_0.8';

	// number of results to show on the search page
	$number_of_results_to_show = 500;

	// python interpreter to run extractor.py
	$python = 'C:\anaconda3520\envs\umls\python.exe';

	// location of extractor.py file that python interpreter will run to extract concepts. Make sure to
	// add 1 space on each side.
	$extractor_location = " Quickumls/extractor.py ";

	// Directory containing json docs to index 
	$dir = 'C:\xampp\htdocs\searchapp\stuff\annotated_json_0.8\\';

?>