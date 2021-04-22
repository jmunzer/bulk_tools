<?php

print("</br><a href='lcn.html'>Back to LCN tool</a>");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<p>Starting</p>";

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

/*
$resourceID = $_REQUEST['UUID'];

echo "Resource ID to use: " . $resourceID;
echo "</br>";
*/
/*
$shouldPublishLists = filter_var($_REQUEST['PUBLISH_LISTS'], FILTER_VALIDATE_BOOLEAN) || FALSE;

echo "Should publish lists?: " . var_export($shouldPublishLists, true);
echo "</br>";
echo "</br>";

$publishListArray = array();

//**********CREATE LOG FILE TO WRITE OUTPUT*

$myfile = fopen("../../report_files/item_output.log", "a") or die("Unable to open item_output.log");
fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");
fwrite($myfile, "List name" . "\t" . "List ID" . "\t" . "Item UUID" . "\t" . "Item added" . "\t" . "List Published" . "\r\n");

//************SET_VARIABLES***********
//uncomment if you want to set these permanently.. good idea tbh!
/*
	$shortCode = "";
	$clientID = "";
	$secret = "";
	$TalisGUID = "";
*/

function getToken($clientID, $secret) {
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

	return $token;
}

function metadataLookup($shortCode, $lcn) {

	$LookupUrl = 'https://' . $shortCode . '.rl.talis.com/catalog/records/' . $lcn;
	//$metadataLookup = simplexml_load_file($LookupUrl) or die("Failed to pull metadata for $lcn from your catalogue");
	$metadata = SimpleXML_load_file($LookupUrl);
	//var_export($metadata);
	return $metadata;
}

$token = getToken($clientID, $secret);

//***********READ**DATA******************
$file_handle = fopen($uploadfile, "rb");

while (!feof($file_handle) )  {

	$line_of_text = fgets($file_handle);
	$parts = explode(" ", $line_of_text);
	$lcn = trim($parts[0]);

	$metadata = metadataLookup($shortCode, $lcn);
	
		$isbn = $metadata->record->datafield[0]->subfield;
		$author = $metadata->record->datafield[8]->subfield[0];
		$pubyear = $metadata->record->datafield[8]->subfield[1];
		$webaddr = $metadata->record->datafield[28]->subfield[0];
		echo $isbn . "</br>";
		echo $author . "</br>";
		echo $pubyear . "</br>";
		echo $webaddr . "</br>";

	echo "</br>";
//	var_dump($metadata);

//********ASSIGN METADATA TO VARIABLES */

//	$leader = $metadata->leader[0];
//	echo $leader;
}
	/*
	$ch = curl_init();
	
	curl_setopt($ch, CURLOPT_URL, $item_lookup);
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
	fwrite($myfile, $title ."\t");
	echo "    List ID: " . $listID . "</br>";
	fwrite($myfile, $listID ."\t");
	echo "    ETag: " . $etag . "</br>";
	echo "    UUID: " . $uuid . "</br>";
	fwrite($myfile, $uuid ."\t");

	// writing list ID to array for bulk publish POST
	$forListArray = ['type' => 'draft_lists', 'id' => $listID]; //check this $listID value
	array_push($publishListArray, $forListArray);

	//**************ADD_ITEM***************
	$patch_url = 'https://rl.talis.com/3/' . $shortCode . '/draft_items/';

	$input = '{
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
								"id": "' . $listID . '",
								"type": "lists"
							},
							"meta": {
								"index": 0
							}
						},
						"resource": {
							"data": {
								"id": "' . $resourceID . '",
								"type": "resources"
							}
						}
					}
				}
			}';

	//**************PARAGRAPH POST*****************

	$ch2 = curl_init();

	curl_setopt($ch2, CURLOPT_URL, $patch_url);
	curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
		
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	));

	curl_setopt($ch2, CURLOPT_POSTFIELDS, $input);

	
	$output2 = curl_exec($ch2);
	$info2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);

	curl_close($ch2);
	if ($info2 !== 201){
		echo "<p>ERROR: There was an error adding the item:</p><pre>" . var_export($output2, true) . "</pre>";
		fwrite($myfile, "Item not added - failed" . "\t");
		continue;
	} else {
		echo "    Added Item $uuid to list $listID</br>";
		fwrite($myfile, "Item added successfully" . "\t");
	}

	//************GRAB**AN**ETAG**AGAIN*************

	$ch4 = curl_init();

	curl_setopt($ch4, CURLOPT_URL, $item_lookup);
	curl_setopt($ch4, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch4, CURLOPT_HTTPHEADER, array(

		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'

	));
	$output4 = curl_exec($ch4);
	$info4 = curl_getinfo($ch4, CURLINFO_HTTP_CODE);
	$output_json2 = json_decode($output4);
	curl_close($ch4);

	$etag2 = $output_json2->data->meta->list_etag;
	echo "    Updated ETag: " . $etag2 . "</br>";
	echo "    ---------------------------------------------------";
	echo "</br>";

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

fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");

fclose($file_handle);
fclose($myfile);
*/
?>