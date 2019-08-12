<?php
/*
This file is used to display a search result document in full after user clicks on a document name in index.php. Search is rerun with highlighting number of fragments set to 0, so that highlights are shown in the full document instead of snippets. Search is rerun on 1 doc only. If search string is empty and no
filters are set, the GET document elasticsearch operation is done.
*/
	session_start();
	require_once 'remove_markup.php';
	require_once 'init.php';
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<?php include 'header.php' ?>
		<title>
			<?php 
			$filename = "";
			$text = "";
			$highlight = "";
			if (isset($_SESSION['results']) && isset($_GET['q'])) {
				$q = $_GET['q'];
				$results = $_SESSION['results'];
				// Print title in the browser tab
				$filename = $results[$q]['_source']['filename'];
				echo $filename;
			}
			?>
		</title> 
	</head>
	<body>
		<div class='container'>
			<?php
					// search string is empty and no filters
				if ($_SESSION['text_to_search'] == '') {
					// get doc instead of search
					$params = [
					    'index' => $index_name,
					    'type' => '_doc',
					    'id' => $results[$q]['_id'],
					    '_source' => 'original_text'
					];
					$query = $client->get($params);
					$text = $query['_source']['original_text'];
				} else {
					// search witin one doc only, get the full text with highlighting
					$query = $client->search([
						"index" => $index_name,
						'body' => [
							'_source' => 'false',
							'query' => [
								'bool' => [
									'must' => [
										[
											"ids" => [
												"values" => $results[$q]['_id']
											]
										],
										[
											'query_string' => [
												'default_field' => $_SESSION['field'],
												'query' => $_SESSION['text_to_search']
											]
										]
									]
								]
							],
							'highlight' => [
								"number_of_fragments" => 0,
								'fields' => [
									$_SESSION['field'] => [
										'type' => 'annotated',
										'require_field_match' => 'true'	// will crash on some docs if false
									]
								]
							]
						]
					]);
					$text = $query['hits']['hits'][0]['highlight'][$_SESSION['field']][0];
				}
			?>
  			<h3>Result</h3>
			<h2> <?php echo $filename; ?> </h2>
			<p> <?php echo remove_markup(nl2br($text)); ?> </p>
		</div>
	</body>
</html>