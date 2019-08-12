<?php

require_once 'init.php';

$count = 0;

	// Index mapping
$mappings = '{
	"mappings" : {
		"properties" : {
			"filename" : {
				"type" : "text"
			},
			"original_text" : {
				"type" : "text"
			},
			"marked_up_text" : {
				"type" : "annotated_text"
			},
			"disorders" : {
				"type" : "nested",
				"properties" : {
					"code" : {
						"type" : "keyword"
					},
					"name" : {
						"type" : "text",
						"index": false
					},
					"count" : {
						"type" : "integer"
					}
				}
			},
			"symptoms" : {
				"type" : "nested",
				"properties" : {
					"code" : {
						"type" : "keyword"
					},
					"name" : {
						"type" : "text",
						"index": false
					},
					"count" : {
						"type" : "integer"
					}
				}
			},
			"treatments" : {
				"type" : "nested",
				"properties" : {
					"code" : {
						"type" : "keyword"
					},
					"name" : {
						"type" : "text",
						"index": false
					},
					"count" : {
						"type" : "integer"
					}
				}
			},
			"tests" : {
				"type" : "nested",
				"properties" : {
					"code" : {
						"type" : "keyword"
					},
					"name" : {
						"type" : "text",
						"index": false
					},
					"count" : {
						"type" : "integer"
					}
				}
			}
		}
	}
}';

$params = [
	"index" => $index_name,
	"body" => json_decode($mappings)
];

// delete index if it exists
$existing = ['index' => $index_name];
$exists = $client->indices()->exists($existing);
$response = 1;
if ($exists) {
	$response = $client->indices()->delete($existing)['acknowledged'];
	echo "Existing index \"" . $index_name . "\" found. Will delete and recreate.<br>";
	echo "Deleting is ". ($response == 1 ? "successful." : "unsuccessful.") . "<br>";
}

if ($response == 1) {
	// print new index parameters
	echo "New index parameters are: <br>";
	echo json_encode($params);
	echo "<br><br>";
	// create new index
	// allow time for index to be created.
	sleep(5);
	Echo "Index creation response is: ";
	$response = $client->indices()->create($params);
	print_r($response);
	echo "<br><br>";
	$response = $response['acknowledged'];
}

if ($response) {
	foreach(glob($dir."*.json") as $file) {
		++$count;
		$contents = file_get_contents($file);
		$body = json_decode($contents, true);

		$indexed = $client->index([
			'index' => $index_name,
			'type' => '_doc',
			'body' => $body
		]);
		if ($indexed) {
			print_r($indexed);
			print('<br>');
		}
		if ($count == 1) {
			break;
		}
	}
	echo "<br>Finished indexing $count documents";
} else {
	echo "<br>Index creation unsuccessful. Aborting.";
}

?>