<?php

print("</br><a href='section.html'>Back to Section tool</a>");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<p>Starting</p>";

//classes
/*
	//uuid classes
	class UUIDGenerator {
		private $uuids = [];
		private $preFetch;
	
		//  @param int $preFetch The number of uuids to cache-ahead when generating
		// 
		public function __construct($preFetch = 100){
			$this->preFetch = $preFetch;
		}
		
		private function refreshUUIDS(){
			$response = file_get_contents("https://www.uuidgenerator.net/api/version4/$this->preFetch");
			$this->uuids = explode("\n", $response);
			array_pop($this->uuids);
		}
	
		public function getUUID(){
			if(count($this->uuids) == 0){
			  $this->refreshUUIDS();
			}
			return trim(array_pop($this->uuids));
		}
	}
*/
//functions

function guidv4($data = null) {

    // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);

    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    // Output the 36 character UUID.
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function grab_etag($shortCode, $barc, $TalisGUID, $token) {
	
	$item_lookup = 'https://rl.talis.com/3/' . $shortCode . '/draft_lists/' . $barc;
	$ch_etag = curl_init();

	curl_setopt($ch_etag, CURLOPT_URL, $item_lookup);
	curl_setopt($ch_etag, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch_etag, CURLOPT_HTTPHEADER, array(

		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'

	));
	$output_etag = curl_exec($ch_etag);
	$info4 = curl_getinfo($ch_etag, CURLINFO_HTTP_CODE);
	$output_json_etag = json_decode($output_etag);
	curl_close($ch_etag);

	$etag = $output_json_etag->data->meta->list_etag;
	echo "    Updated ETag: " . $etag . "</br>";
	echo "    ---------------------------------------------------";
	echo "</br>";

	return $etag;
}

//*****************GRAB_INPUT_DATA**********

$uploaddir = '../uploads/';
$uploadfile = $uploaddir . basename($_FILES['userfile']['name']);

echo '<pre>';
if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
	echo "File is valid, and was successfully uploaded.\n";
} else {
	echo "File is invalid, and failed to upload - Please try again. -\n";
}
echo "</br>";
print_r($uploadfile);
echo "</br>";
echo "</br>";

/**
 * Get the user config file. This script will fail disgracefully if it has not been created and nothing will happen.
 */
require('../../user.config.php');

echo "Tenancy Shortcode set: " . $shortCode;
echo "</br>";

echo "Client ID set: " . $clientID;
echo "</br>";

echo "User GUID to use: " . $TalisGUID;
echo "</br>";

$shouldPublishLists = filter_var($_REQUEST['PUBLISH_LISTS'], FILTER_VALIDATE_BOOLEAN) || FALSE;

echo "Should publish lists?: " . var_export($shouldPublishLists, true);
echo "</br>";
echo "</br>";

$publishListArray = array();

//**********CREATE LOG FILE TO WRITE OUTPUT*

$myfile = fopen("../../report_files/section_output.log", "a") or die("Unable to open section_output.log");
fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");
fwrite($myfile, "List name" . "\t" . "List ID" . "\t" . "Item UUID" . "\t" . "Item added" . "\t" . "List Published" . "\r\n");

$tokenURL = 'https://users.talis.com/oauth/tokens';
$content = "grant_type=client_credentials";

//*********GET DATE**********************

$date = date('Y-m-d\TH:i:s');
// $date1 = "2015-12-21T15:44:36";

//************GET_TOKEN***************


$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $tokenURL);
curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_USERPWD, "$clientID:$secret");
curl_setopt($ch, CURLOPT_POSTFIELDS, $content);

$return = curl_exec($ch);
$info = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($info !== 200){
	echo "<p>ERROR: There was an error getting a token:</p><pre>" . var_export($return, true) . "</pre>";
} else {
	echo "Got Token</br>";
}

curl_close($ch);

$jsontoken = json_decode($return);

if (!empty($jsontoken->access_token)){
	$token = $jsontoken->access_token;
} else {
	echo "<p>ERROR: Unable to get an access token</p>";
	exit;
}

//***********READ**DATA******************

$file_handle = fopen($uploadfile, "rb");

while (!feof($file_handle) )  {

	$uuid = guidv4();
	$uuid_section = $uuid;
	$line_of_text = fgets($file_handle);
	$parts = explode(" ", $line_of_text);
	
	//************GRAB**LIST**DATA***************

	$barc = trim($parts[0]);
	
	$item_lookup = 'https://rl.talis.com/3/' . $shortCode . '/draft_lists/' . $barc;
	$ch1 = curl_init();
	
	curl_setopt($ch1, CURLOPT_URL, $item_lookup);
	curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
		
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'

	));
	$output = curl_exec($ch1);
	$info1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
	$output_json = json_decode($output);
	curl_close($ch1);
	if ($info1 !== 200){
		echo "<p>ERROR: There was an error getting the draft list:</p><pre>" . var_export($output, true) . "</pre>";
		continue;
	} else {
		echo "    Got draft for list </br>";
	}

	$title = $output_json->data->attributes->title;
	$listID = $output_json->data->id;
	$etag = $output_json->data->meta->list_etag;

	echo "    Title: " . $title . "</br>";
//	fwrite($myfile, $title ."\t");
	echo "    List ID: " . $listID . "</br>";
//	fwrite($myfile, $listID ."\t");
	echo "    ETag: " . $etag . "</br>";
	echo "    UUID: " . $uuid . "</br>";
//	fwrite($myfile, $uuid ."\t");

	// writing list ID to array for bulk publish POST
	$forListArray = ['type' => 'draft_lists', 'id' => $listID]; //check this $listID value
	array_push($publishListArray, $forListArray);

	//**************ADD_SECTION************

	$section_patch = 'https://rl.talis.com/3/' . $shortCode . '/draft_sections';
	
	$inp = '{
		"data": {
		  "id": "' . $uuid . '",
		  "type": "sections",
		  "attributes": {
			"description": "If you have a disability and an item on this module reading list isn\'t accessible to you in its current format (and you\'re registered with the Library\'s Accessibility Support Service), you can find accessible formats of books on RNIB Bookshare, or if they are not available through there, request accessible scans of items using the Document Scanning Request form.",
			"title": "Library Accessibility Support Service for disabled students"
		  },
		  "links": {},
		  "meta": {},
		  "relationships": {
			"container": {
			  "data": {
				"id": "' . $listID . '",
				"type": "lists"
			  },
			  "links": {
				"self": "string",
				"related": "string"
			  },
			  "meta": {
				"index": 0
			  }
			}
		  }
		},
		"meta": {
		  "has_unpublished_changes": true,
		  "list_etag": "' . $etag . '",
		  "list_id": "' . $listID . '"
		}
	  }';

	//**************SECTION POST*****************

	$ch_section = curl_init();

	curl_setopt($ch_section, CURLOPT_URL, $section_patch);
	curl_setopt($ch_section, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($ch_section, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch_section, CURLOPT_HTTPHEADER, array(
		
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	));

	curl_setopt($ch_section, CURLOPT_POSTFIELDS, $inp);

	
	$output_section = curl_exec($ch_section);
	$info_section = curl_getinfo($ch_section, CURLINFO_HTTP_CODE);

	curl_close($ch_section);
	if ($info_section !== 201){
		echo "<p>ERROR: There was an error adding the item:</p><pre>" . var_export($output_section, true) . "</pre>";
		fwrite($myfile, "Section not added - failed" . "\t");
		continue;
	} else {
		echo "    Added Item $uuid to list $listID</br>";
		fwrite($myfile, "Section added successfully" . "\t");
	}

	// Grab another eTag & UUID
	$etag = grab_etag($shortCode, $barc, $TalisGUID, $token);
	$uuid = guidv4();

	//**************ADD_ITEM_1***************
	$item_patch = 'https://rl.talis.com/3/' . $shortCode . '/draft_items/';

	$input1 = '{
				"meta": {
					"list_etag": "' . $etag . '",
					"list_id": "' . $listID . '"
				},
				"data": {
					"id": "' . $uuid . '",
					"type": "items",
					"relationships": {
						"container": {
							"data": {
								"id": "' . $uuid_section . '",
								"type": "sections"
							},
							"meta": {
								"index": 0
							}
						},
						"resource": {
							"data": {
								"id": "61074635-D0EE-C0B5-DC9F-C0A684820DA4",
								"type": "resources"
							}
						}
					}
				}
			}';

	//**************ITEM_1 POST*****************

	$ch_item1 = curl_init();

	curl_setopt($ch_item1, CURLOPT_URL, $item_patch);
	curl_setopt($ch_item1, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($ch_item1, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch_item1, CURLOPT_HTTPHEADER, array(
		
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	));

	curl_setopt($ch_item1, CURLOPT_POSTFIELDS, $input1);

	
	$output1 = curl_exec($ch_item1);
	$info1 = curl_getinfo($ch_item1, CURLINFO_HTTP_CODE);

	curl_close($ch_item1);
	if ($info1 !== 201){
		echo "<p>ERROR: There was an error adding the item:</p><pre>" . var_export($output1, true) . "</pre>";
		fwrite($myfile, "Item not added - failed" . "\t");
		continue;
	} else {
		echo "    Added Item $uuid to list $listID</br>";
		fwrite($myfile, "Item added successfully" . "\t");
	}

	// Grab another eTag & UUID
	$etag = grab_etag($shortCode, $barc, $TalisGUID, $token);
	$uuid = guidv4();

	//**************ADD_ITEM_2***************

	$input2 = '{
				"meta": {
					"list_etag": "' . $etag . '",
					"list_id": "' . $listID . '"
				},
				"data": {
					"id": "' . $uuid . '",
					"type": "items",
					"relationships": {
						"container": {
							"data": {
								"id": "' . $uuid_section . '",
								"type": "sections"
							},
							"meta": {
								"index": 1
							}
						},
						"resource": {
							"data": {
								"id": "9F64F566-3C07-9EF1-A6E3-77D4D694EBA6",
								"type": "resources"
							}
						}
					}
				}
			}';

	//**************ITEM_2 POST*****************

	$ch_item2 = curl_init();

	curl_setopt($ch_item2, CURLOPT_URL, $item_patch);
	curl_setopt($ch_item2, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($ch_item2, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch_item2, CURLOPT_HTTPHEADER, array(
		
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	));

	curl_setopt($ch_item2, CURLOPT_POSTFIELDS, $input2);

	
	$output2 = curl_exec($ch_item2);
	$info2 = curl_getinfo($ch_item2, CURLINFO_HTTP_CODE);

	curl_close($ch_item2);
	if ($info2 !== 201){
		echo "<p>ERROR: There was an error adding the item:</p><pre>" . var_export($output2, true) . "</pre>";
		fwrite($myfile, "Item not added - failed" . "\t");
		continue;
	} else {
		echo "    Added Item $uuid to list $listID</br>";
		fwrite($myfile, "Item added successfully" . "\t");
	}

	// Grab another eTag & UUID
	$etag = grab_etag($shortCode, $barc, $TalisGUID, $token);
	$uuid = guidv4();
		
	//**************ADD_ITEM_3***************
	
		$input3 = '{
					"meta": {
						"list_etag": "' . $etag . '",
						"list_id": "' . $listID . '"
					},
					"data": {
						"id": "' . $uuid . '",
						"type": "items",
						"relationships": {
							"container": {
								"data": {
									"id": "' . $uuid_section . '",
									"type": "sections"
								},
								"meta": {
									"index": 2
								}
							},
							"resource": {
								"data": {
									"id": "B986A749-F293-3976-40D4-5F616CEAB683",
									"type": "resources"
								}
							}
						}
					}
				}';
	
		//**************ITEM_3 POST*****************
	
		$ch_item3 = curl_init();
	
		curl_setopt($ch_item3, CURLOPT_URL, $item_patch);
		curl_setopt($ch_item3, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch_item3, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch_item3, CURLOPT_HTTPHEADER, array(
			
			"X-Effective-User: $TalisGUID",
			"Authorization: Bearer $token",
			'Cache-Control: no-cache'
		));
	
		curl_setopt($ch_item3, CURLOPT_POSTFIELDS, $input3);
	
		
		$output3 = curl_exec($ch_item3);
		$info3 = curl_getinfo($ch_item3, CURLINFO_HTTP_CODE);
	
		curl_close($ch_item3);
		if ($info3 !== 201){
			echo "<p>ERROR: There was an error adding the item:</p><pre>" . var_export($output3, true) . "</pre>";
			fwrite($myfile, "Item not added - failed" . "\t");
			continue;
		} else {
			echo "    Added Item $uuid to list $listID</br>";
			fwrite($myfile, "Item added successfully" . "\t");
		}
}

/*
	//print_r($publishListArray);
	//json_encode list array to prepare for API submisson
	$publishListArray_encoded = json_encode($publishListArray);

	//var_export($publishListArray_encoded);
}
	if ($shouldPublishLists === TRUE) {
		//**************PUBLISH**LIST***************
		$patch_url2 = 'https://rl.talis.com/3/' . $shortCode . '/bulk_list_publish_actions'; // change my endpoint
		$input2 = '{
					"data": {
						"type": "bulk_list_publish_actions",
						"relationships": {
							"draft_lists": {
								"data": ' . $publishListArray_encoded . '
							}
						}
					}	
				}';

		//**************PUBLISH POST*****************

		$ch3 = curl_init();

		curl_setopt($ch3, CURLOPT_URL, $patch_url2);
		curl_setopt($ch3, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch3, CURLOPT_HTTPHEADER, array(

			"X-Effective-User: $TalisGUID",
			"Authorization: Bearer $token",
			'Cache-Control: no-cache'
		));

		curl_setopt($ch3, CURLOPT_POSTFIELDS, $input2);


		$output3 = curl_exec($ch3);
		$info3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
		curl_close($ch3);
		if ($info3 !== 202){
			echo "<p>ERROR: There was an error publishing the list:</p><pre>" . var_export($output3, true) . "</pre>";
			fwrite($myfile, "Publish failed" . "\t");
			exit;
		} else {
			echo "    Published changes to $listID</br>";
			fwrite($myfile, "Published successfully" . "\t");
		}
	}
	
	fwrite($myfile, "\n");
	echo "End of Record.";
	echo "---------------------------------------------------</br></br>";
	*/

fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");

fclose($file_handle);
fclose($myfile);


?>