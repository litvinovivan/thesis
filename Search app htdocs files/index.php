 <?php 
	require_once 'init.php';
	require_once 'remove_markup.php';

	session_start();

	$aggregations = '{
	    "Disorders": {
	      "nested": {
	        "path": "disorders"
	      },
	      "aggs": {
	        "count": {
	          "terms": {
	            "field": "disorders.code",
	            "size" : 5
	          }
	        }
	      }
	    },
	    "Symptoms": {
	      "nested": {
	        "path": "symptoms"
	      },
	      "aggs": {
	        "count": {
	          "terms": {
	            "field": "symptoms.code",
	            "size" : 5
	          }
	        }
	      }
	    },
	    "Tests": {
	      "nested": {
	        "path": "tests"
	      },
	      "aggs": {
	        "count": {
	          "terms": {
	            "field": "tests.code",
	            "size" : 5
	          }
	        }
	      }
	    },
	    "Treatments": {
	      "nested": {
	        "path": "treatments"
	      },
	      "aggs": {
	        "count": {
	          "terms": {
	            "field": "treatments.code",
	            "size" : 5
	          }
	        }
	      }
	    }
	}';

	$q = "";
	$text_to_search = "";
	$filter_string = "";
	$extracted = "{}";
	$text_button = "";
	$extract_button = "";
	$concepts_button = "";
	$descendants_switch = "";
	$search_type = "";
	$_SESSION['field'] = "marked_up_text";
	if (isset($_GET['q'])) {
		$q = $_GET['q'];
		$search_type = $_GET['search_type'];
		# variable with the name stored in 'search_type' is going to be "checked"
		$$search_type = "checked";
		# get descendants switch value
		if (isset($_GET['descendants'])) {
			$descendants_switch = "checked";
		}
	} else {
		// initial state of the page
		$text_button = "checked";
	}
		// if search query is empty
	if ($q == '') {
		$extracted = "{}";
		if (isset($_SESSION['filters'])) {
			$text_to_search = $_SESSION['filter_string'];
			$_SESSION['field'] = "marked_up_text";
			$query_part = [
				'query_string' => [
					'default_field' => $_SESSION['field'],
					'query' => $text_to_search
				]
			];
		} else {
			$query_part = ['match_all' => new \stdClass()];
		}
		if ($_SESSION['text_to_search'] != $text_to_search) {
			$_SESSION['query'] = $client->search([
				"index" => $index_name,
				'body' => [
					'_source' => 'filename',
					'query' => $query_part,
					'aggs' => json_decode($aggregations),
					'highlight' => [
						'fields' => [
							$_SESSION['field'] => [
								'type' => 'annotated',
								'require_field_match' => 'true'
							]
						]
					],
					'size' => $number_of_results_to_show
				]
			]);
		}
		$descendants_switch = "";
		// search query is not empty
	} else {
		if (isset($_SESSION['filters'])) {
			$filter_string = " AND " . $_SESSION['filter_string'];
		}
				//Free-text search
		if ($search_type == "text_button") {
			$text_to_search = $q . $filter_string;
			$descendants_switch = "";
				
				//Extract concepts from search query and search concept identifiers
		} elseif ($search_type == "extract_button") {
			if ($descendants_switch) {
				$flag = "-c ";
			} else {
				$flag = "";
			}
			// only run python script if search inputs changed
			$concept_count = 0;
			if ($_SESSION['last_q'] != $q || $_SESSION['last_search_type'] != $search_type 
					|| $_SESSION['descendants_switch'] != $descendants_switch) {
				$_SESSION['extracted'] = exec($python . $extractor_location . $flag . "\"$q\"");
				if (!$_SESSION['extracted']) {
					echo "Attention: QucikUMLS server is not running. Extraction failed.";
				} else {
					$result_array = json_decode($_SESSION['extracted']);
					// adding extracted concept IDs to search query
					foreach ($result_array as $key => $value) {
						$text_to_search = $text_to_search . " $key";
						++$concept_count;
					}
					$_SESSION['text_with_extracted_ids'] = $text_to_search;
					$_SESSION['concept_count'] = $concept_count;
				}
			}
			if (!$_SESSION['extracted']) {
				echo "Attention: QucikUMLS server is not running. Extraction failed.";
			}
			// If text_to_search is not empty, wrap it in () and append filter_string.
			// If text_to_search is empty, remove AND from filter_string
			$text_to_search = ($_SESSION['text_with_extracted_ids'] ? "(".ltrim($_SESSION['text_with_extracted_ids']).")" . $filter_string : ($filter_string ? explode(" AND ", $filter_string, 2)[1] : ""));
		} else {	//$search_type == "concepts_button"
			$text_to_search = "(".$q.")" . $filter_string;
			$descendants_switch = "";
		}
		// only run this if $text_to_search changed from last loading of the page
		if ($text_to_search != $_SESSION['text_to_search']) {
			$_SESSION['query'] = $client->search([
				"index" => $index_name,
				'body' => [
					'query' => [
						'query_string' => [
							'default_field' => $_SESSION['field'],
							'query' => $text_to_search 
						]
					],
					'highlight' => [
						'fields' => [
							$_SESSION['field'] => [
								'type' => 'annotated',
								'require_field_match' => 'false'
							]
						]
					],
					'size' => $number_of_results_to_show,
					'aggs' => json_decode($aggregations, true)
				]
			]);
		}
	}
	// saving last search to avoid rerunning extraction when coming back from results.
	$_SESSION['last_search_type'] = $search_type;
	$_SESSION['last_q'] = $q;
	$_SESSION['descendants_switch'] = $descendants_switch;
	$_SESSION['text_to_search'] = $text_to_search;
	
	$total = $_SESSION['query']['hits']['total']['value'];
	if ($total > 0) {
		$results = $_SESSION['query']['hits']['hits'];
		$_SESSION['results'] = $results;
		
		//prepare aggregation data
		$categories = array("Disorders", "Symptoms", "Tests", "Treatments");
		$colours = array("Disorders" => "red", "Symptoms" => "blue", "Tests" => "orange", "Treatments" => "green");
		$aggs = $_SESSION['query']['aggregations'];
	}
 ?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<title>The Health Search</title>
		<?php include 'header.php' ?>
		
		<!-- Google charts -->
		<script src="https://www.gstatic.com/charts/loader.js"></script>
		<script>
		    google.charts.load('current', {packages: ['corechart']});
		    
		// create chart functions for each concept category in aggregations
		<?php foreach ($categories as $category) : ?>

		    google.charts.setOnLoadCallback(<?php echo $category ?>);
		    
		    function <?php echo $category ?> () {
		      	// Define the chart to be drawn.
				var data = google.visualization.arrayToDataTable([
					[{label: <?php echo "'$category'" ?>, type: 'string'}, 'Count', {role: 'annotation'}, { role: 'style' }],		
					<?php
							//Populate chart data with aggregations data
							// e.g. ['Oxygen', 0.21],
						$comma = ""; 
						foreach ($aggs[$category]['count']['buckets'] as $bucket) {	
							echo $comma."['".$display_names[$bucket['key']]."', ".$bucket['doc_count'].", ".$bucket['doc_count'].", 'fill-opacity: 0.8']";
							$comma = ",";
						} 
					?>
						]);
					<?php
							//Add Concept code to each row as hidden property
						$i = 0;
						foreach ($aggs[$category]['count']['buckets'] as $bucket) {	?>
							data.setRowProperty( <?php echo "$i, 'code', '{$bucket['key']}'" ?> )
					<?php
						$i++ ; 
						}
					?>
				var options = {
					title: <?php echo "'$category'" ?>,
					legend: 'none',
					fontSize: 13,
					colors: ['<?php echo $colours[$category]?>'],
					chartArea: {
						left: 180
					}
				};

				// Instantiate and draw the chart.
				var chart = new google.visualization.BarChart(document.getElementById(
						<?php echo "'$category'" ?>));

				// The select handler. Call the chart's getSelection() method
				function selectHandler() {
				    var selectedItem = chart.getSelection()[0];
				    if (selectedItem) {
				    	var concept = data.getValue(selectedItem.row, 0);

				    	// Send the Category and index of which concept was clicked
				    	$.ajax({
		                    type: "POST",
		                    url: "apply_filters.php",
		                    data:
		                    {
		                        category: data.getColumnLabel(0),
		                        code: data.getRowProperty(selectedItem.row, 'code'),
		                        name: concept
		                    },
		                    success: function(response) {
		                        if (response == "done") {
		                        	window.location.reload();
		                        }
		                    }
		                });
				    }
				}
				// Listen for the 'select' event, and call my function selectHandler() when
				// the user selects something on the chart.
				google.visualization.events.addListener(chart, 'select', selectHandler);
				chart.draw(data, options);
		    }
		<?php endforeach; ?>
		</script>
	</head>
	<body>
		<div class='container'>
			<!-- this script is provided by David Conlan, The Australian e-Health Research Centr, eCSIRO -->
			<script>
				function get_search_type() {
					return $("input[name='search_type']:checked").val();
				}
					//Ivan's
				function select_or_focus(event, ui) {
					event.preventDefault();
					if (get_search_type() === "concepts_button") {
						return ui.item.value;
					} else {
						return ui.item.label;
					}
				}

				$( function() {
					$( "#q" ).autocomplete({
						source: function( request, response ) {
							type="POST";
							url="https://ontoserver.csiro.au/stu3-latest/ValueSet/$expand";
							postData = JSON.stringify({
								"resourceType": "Parameters",
								"parameter": [
									{
										"name": "filter",
										"valueString": request.term
									},
									{
										"name": "url",
										"valueUri": "http://snomed.info/sct?fhir_vs"
									},
									{
										"name": "count",
										"valueInteger": "20"
									},
										// Ivan added below
									{	// To get fully qualified name
										"name": "includeDesignations",
										"valueBoolean": "true"
									},
									{	//Excluding inactive codes
										"name": "activeOnly",
										"valueBoolean": "true"
									}
								]
						  	});
						  		// Modified by Ivan to get the fully specified name
							processFunction = function( data ) {
								result = [];
								if (data.expansion && data.expansion.contains) {
								  	for (descendant of data.expansion.contains) {
										for (r of descendant.designation) {
											if (r.use && r.use.display == "Fully specified name") {
												result.push({ 'label' : r.value, 'value' : descendant.code});
												break;
											}
										}
										
								  	}
								}
								response( result );
							};
							$.ajax( {
								type: type,
								url: url,
								data: postData,
								contentType: "application/json; charset=utf-8",
								dataType: "json",
								success: processFunction
							} );
						},
						// This section is modified with the if statement by Ivan
						select: function( event, ui ) {
							$(this).val(select_or_focus(event, ui));
						},
						// This section is modified with the if statement by Ivan
						focus: function( event, ui ) {
							$(this).val(select_or_focus(event, ui));
						},
						minLength: 2
					} );
				} );
			</script>
			<form action="index.php" method="get" autocomplete="off">				
				<div class="form-group">
					<h2>Search medical records:</h2>
					<div class="form-check">
						<label class="form-check-label">
							<input type="radio" class="form-check-input" name="search_type" value="text_button" 
							<?php echo $text_button; ?>>Free text search
						</label>
					</div>
					<div class="form-check">
						<label class="form-check-label">
							<input type="radio" class="form-check-input" name="search_type" value="extract_button" 
							<?php echo $extract_button; ?>>Extract concepts from text and search concept identifiers
						</label>
					</div>
					<div class="form-check">
						<label class="form-check-label">
							<input type="radio" class="form-check-input" name="search_type" value="concepts_button" 
							<?php echo $concepts_button; ?>>Concept identifier search (allows Boolean expressions)
						</label>
					</div>
					<div class="form-check custom-control custom-switch">
						<input type="checkbox" class="custom-control-input" id="switch1" name="descendants" <?php echo $descendants_switch; ?>>
						<label class="custom-control-label" for="switch1">Include concept descendants</label>
					</div>
					<input class="form-control" type="text" style="width:70%" placeholder="Search clinical text" id="q" name="q" value='<?php echo $q; ?>' >
					<input class="btn btn-primary" type="submit">
				</div>
			</form>
			<h3>Search results</h3>
			<?php
				if ($extract_button && $q != '') {
					echo "<h5> Extracted " . $_SESSION['concept_count'] . " concepts: </h5>";
					echo "<div style=\"overflow:auto; height:50px;\"> {$_SESSION['extracted']} </div>";
				}
				echo "<h5> Searched query: </h5>";
				echo "<p style=\"overflow:auto; height:50px;\"> \"".$text_to_search."\" </p>"; 
			?>
			<script>
				// from w3schools.com
				function hideShow() {
					var x = document.getElementById("charts");
					if (x.style.display === "none") {
				    	x.style.display = "block";
				  	} else {
				    	x.style.display = "none";
				  	}
				}
				// sends ajax request to server to reset/clear filters and reloads page
				function clearFilters() {
			    	$.ajax({
	                    type: "GET",
	                    url: "clear_filters.php",
	                    success: function(response) {
	                        if (response == "done") {
	                        	window.location.reload();
	                        }
	                    }
	                });
			    }
			</script>
			<div class="container row mb-1">
				<button class="btn btn-info mr-sm-2" onclick="hideShow()">Hide/Show Charts</button>
				<button class="btn btn-warning mr-sm-2" onclick="clearFilters()">Clear all filters</button>
			</div>
			<div id='charts'>
				<div id='filters'>
					<?php 
						if (isset($_SESSION['filters'])) {
							echo "<h5>Applied filters:</h5>";
							foreach ($_SESSION['filters'] as $category => $array) {
								echo '<span class="'.$category.'">' . $category . ":</span> ";
								$comma = '';
								foreach ($array as $concept_values) {
									echo $comma . $concept_values['name'] . " (" . $concept_values['code'] . ")";
									$comma = ', ';
								}
								echo ".<br>";
							}
						}
					?>
				</div>
				<div class="row">
					<div class="col" id="Disorders"></div>
					<div class="col" id="Symptoms"></div>
				</div>
				<div class="row">
					<div class="col" id="Tests"></div>
					<div class="col" id="Treatments"></div>
				</div>
			</div>
			<?php
				echo "<h4>".$total." results found.</h4>"; 
				if (isset($results)) {
					$counter = 0;
					foreach ($results as $result) {
			?>
						<a href="show.php?q=<?php echo $counter ?>">
							<h4><?php echo $result['_source']['filename'] ?></h4>
						</a>
			<?php 
						if (isset($result['highlight'][$_SESSION['field']])) {
							echo "<p>";
							foreach ($result['highlight'][$_SESSION['field']] as $highlight) { 
								echo remove_markup($highlight)."<br>";
							}
							echo "</p>";
						}
						$counter++;
					}
					echo "<br><br>";
				}
			?>
		</div>
	</body>
</html>