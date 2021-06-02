<?php

print("</br><a href='acq.html'>Back to New Acquisitions tool</a>");

ini_set('max_execution_time', '0');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<p>Starting</p>";

//classes
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

function itemPost($shortCode, $TalisGUID, $token, $input) {
	$item_patch = 'https://rl.talis.com/3/' . $shortCode . '/draft_items/';
	$ch_item = curl_init();

	curl_setopt($ch_item, CURLOPT_URL, $item_patch);
	curl_setopt($ch_item, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($ch_item, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch_item, CURLOPT_HTTPHEADER, array(
		
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	));

	curl_setopt($ch_item, CURLOPT_POSTFIELDS, $input);

	
	$output = curl_exec($ch_item);
	$info = curl_getinfo($ch_item, CURLINFO_HTTP_CODE);
	echo $info;

	$output_json_etag = json_decode($output);
	$etag = $output_json_etag->meta->list_etag;

	curl_close($ch_item);
	if ($info !== 201){
		echo "<p>ERROR: There was an error adding the item:</p><pre>" . var_export($output, true) . "</pre>";
	} else {
		echo "    Added item to list</br>";
	}
	return $etag;
}

function itemBody($etag, $listID, $item_uuid, $resource_uuid) {
	
	$input = '{
				"meta": {
					"list_etag": "' . $etag . '",
					"list_id": "' . $listID . '"
				},
				"data": {
					"id": "' . $item_uuid . '",
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
								"id": "' . $resource_uuid . '",
								"type": "resources"
							}
						}
					}
				}
			}';
		return $input;
};

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

$listID = $_REQUEST['ListID'];

echo "List ID to use: " . $listID;
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
$shouldPublishLists = filter_var($_REQUEST['PUBLISH_LISTS'], FILTER_VALIDATE_BOOLEAN) || FALSE;

echo "Should publish lists?: " . var_export($shouldPublishLists, true);
echo "</br>";
echo "</br>";

$publishListArray = array();
*/

//**********CREATE LOG FILE TO WRITE OUTPUT*

$myfile = fopen("../../report_files/newacq_output.log", "a") or die("Unable to open newacq_output.log");
fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");
//fwrite($myfile, "List name" . "\t" . "List ID" . "\t" . "Section Status" . "\t" . "Item Status" . "\t" . "Item Status" . "\t" . "Item Status" . "\t" . "List Published" . "\r\n");

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

//***************GRAB LIST ITEMS TO DELETE ****** */
$etag_lookup = 'https://rl.talis.com/3/' . $shortCode . '/lists/' . $listID . '/items';
$ch_etag = curl_init();

curl_setopt($ch_etag, CURLOPT_URL, $etag_lookup);
curl_setopt($ch_etag, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch_etag, CURLOPT_HTTPHEADER, array(
    
    "X-Effective-User: $TalisGUID",
    "Authorization: Bearer $token",
    'Cache-Control: no-cache'

));

$output_etag = curl_exec($ch_etag);
$info_etag = curl_getinfo($ch_etag, CURLINFO_HTTP_CODE);
$output_json_etag = json_decode($output_etag);
curl_close($ch_etag);

if ($info_etag !== 200){
    echo "<p>ERROR: There was an error getting the list items:</p><pre>" . var_export($output_etag, true) . "</pre>";
} else {
    echo "    Got items from list </br>";
}

$item_count = $output_json_etag->meta->item_count;
$itemID = $output_json_etag->data;

foreach ($itemID as $item) {
$i = $item->id;
echo $i . "<br>";
	
}

echo "    item count is: " . $item_count . "</br>";



/*

//************GRAB**LIST DETAILS***************

$etag_lookup = 'https://rl.talis.com/3/' . $shortCode . '/draft_lists/' . $listID;
$ch_etag = curl_init();

curl_setopt($ch_etag, CURLOPT_URL, $etag_lookup);
curl_setopt($ch_etag, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch_etag, CURLOPT_HTTPHEADER, array(
	
	"X-Effective-User: $TalisGUID",
	"Authorization: Bearer $token",
	'Cache-Control: no-cache'

));
$output_etag = curl_exec($ch_etag);
$info_etag = curl_getinfo($ch_etag, CURLINFO_HTTP_CODE);
$output_json_etag = json_decode($output_etag);
curl_close($ch_etag);
if ($info_etag !== 200){
	echo "<p>ERROR: There was an error getting the list etag:</p><pre>" . var_export($output, true) . "</pre>";
} else {
	echo "    Got etag for list </br>";
}

$title = $output_json_etag->data->attributes->title;
$listID = $output_json_etag->data->id;
$etag = $output_json_etag->data->meta->list_etag;

echo "    Title: " . $title . "</br>";
fwrite($myfile, $title ."\t");
echo "    List ID: " . $listID . "</br>";
fwrite($myfile, $listID ."\t");
echo "    ETag: " . $etag . "</br>";

/*
// writing list ID to array for bulk publish POST
$forListArray = ['type' => 'draft_lists', 'id' => $listID]; //check this $listID value
array_push($publishListArray, $forListArray);

//***********READ**DATA******************

$file_handle = fopen($uploadfile, "r");
if ($file_handle == FALSE) {
	echo_message_to_screen(ERROR, "Could not open csv file - Process Stopped.");
    exit;
}

$row = 0;
while (($line = fgetcsv($file_handle, 1000, ",")) !== FALSE) {

	$row++;

	$resource_uuid = guidv4();

	$resourceType = trim($line[0]);
	$Title = trim($line[1]);
	$isbn = trim($line[2]);
	
	echo "Resource Type: " . $resourceType . "</br>";
	echo "Title: " . $Title . "</br>";
	echo "ISBN: " . $isbn . "</br>";
	
	
	
	//************CREATE RESOURCE***************
	$createResourceAPIpost = 'https://rl.talis.com/3/' . $shortCode . '/resources';
	
	$body = '{
		"data": {
		  "id": "' . $resource_uuid . '",
		  "type": "resources",
		  "attributes": {
			"title": "' . $Title . '",
			"resource_type": "Book",
			"isbn13s": [
				"' . $isbn . '"
				]
		  },
		  "links": {},
		  "meta": {},
		  "relationships": {}
		}
	  }';
	
	var_export($body);
	
	$ch1 = curl_init();
	
	curl_setopt($ch1, CURLOPT_URL, $createResourceAPIpost);
	curl_setopt($ch1, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
		
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	
	

	));
	curl_setopt($ch1, CURLOPT_POSTFIELDS, $body);

	$output = curl_exec($ch1);
	$info1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
	echo $info1;
	$output_json = json_decode($output);
	curl_close($ch1);
	if ($info1 !== 200){
		echo "<p>ERROR: There was an error creating resource:</p><pre>" . var_export($output, true) . "</pre>";
		continue;
	} else {
		echo "    Hurray guys, we made a resource </br>";
	}
//----------------------------------
	$item_uuid = guidv4();
	$input = itemBody($etag, $listID, $item_uuid, $resource_uuid);
	$etag = itemPost($shortCode, $TalisGUID, $token, $input);
}
//----------------------------------
/*
	$title = $output_json->data->attributes->title;
	$listID = $output_json->data->id;
	$etag = $output_json->data->meta->list_etag;

	echo "    Title: " . $title . "</br>";
	fwrite($myfile, $title ."\t");
	echo "    List ID: " . $listID . "</br>";
	fwrite($myfile, $listID ."\t");
	echo "    ETag: " . $etag . "</br>";
	echo "    UUID: " . $uuid . "</br>";
//	fwrite($myfile, $uuid ."\t");
*/
	
	

	/*
		
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
	fwrite($myfile, $title ."\t");
	echo "    List ID: " . $listID . "</br>";
	fwrite($myfile, $listID ."\t");
	echo "    ETag: " . $etag . "</br>";
	echo "    UUID: " . $uuid . "</br>";
//	fwrite($myfile, $uuid ."\t");

	// writing list ID to array for bulk publish POST
	$forListArray = ['type' => 'draft_lists', 'id' => $listID]; //check this $listID value
	array_push($publishListArray, $forListArray);

	//**************ADD_SECTION************

	$section_patch = 'https://rl.talis.com/3/' . $shortCode . '/draft_sections';
	
	// EDIT THE BELOW PLACEHOLDER VALUES TO SET SECTION TITLE AND DESCRIPTION
	// 
	// Insert desired section title
	$section_title = "replace me with section title text";
	// Insert desired section description
	$section_description = "Replace me with section description's text";
	//
	// DO NOT EDIT BELOW THIS LINE
	
	$inp = '{
		"data": {
		  "id": "' . $uuid . '",
		  "type": "sections",
		  "attributes": {
			"description": "' . $section_description . '",
			"title": "' . $section_title . '"
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
	
	$output_json_section = json_decode($output_section);
	$etag = $output_json_section->meta->list_etag;
	
	curl_close($ch_section);
	if ($info_section !== 201){
		echo "<p>ERROR: There was an error adding the section:</p><pre>" . var_export($output_section, true) . "</pre>";
		fwrite($myfile, "Section $uuid not added - failed" . "\t");
		continue;
	} else {
		echo "    Added section $uuid to list $listID</br>";
		fwrite($myfile, "Section $uuid added successfully" . "\t");
	}


// START OF STRUCTURE-BUILDING AREA - COPY, PASTE OR DELETE BLOCKS BELOW TO BUILD DESIRED STRUCTURE

	//**************ITEM_1*****************
	$uuid = guidv4();
	$resource = "B986A749-F293-3976-40D4-5F616CEAB683"; //GET THIS RESOURCE ID FROM TARL - EDIT RESOURCE URL
	$input = itemBody($etag, $listID, $uuid, $uuid_section, $resource);
	$etag = itemPost($shortCode, $TalisGUID, $token, $input, $myfile, $uuid, $uuid_section);
	//**************************************

	//**************ITEM_2******************
	$uuid = guidv4();
	$resource = "9F64F566-3C07-9EF1-A6E3-77D4D694EBA6";
	$input = itemBody($etag, $listID, $uuid, $uuid_section, $resource);
	$etag = itemPost($shortCode, $TalisGUID, $token, $input, $myfile, $uuid, $uuid_section);
	//**************************************

	//**************ITEM_3*****************
	$uuid = guidv4();
	$resource = "61074635-D0EE-C0B5-DC9F-C0A684820DA4";
	$input = itemBody($etag, $listID, $uuid, $uuid_section, $resource);
	$etag = itemPost($shortCode, $TalisGUID, $token, $input, $myfile, $uuid, $uuid_section);	
	//**************************************

// END OF STRUCTURE-BUILDING AREA - DO NOT EDIT DATA BELOW THIS LINE (UNLESS YOU KNOW WHAT YOU ARE DOING) :)

	fwrite($myfile, "\n");
	echo "End of Record.";
	echo "---------------------------------------------------</br></br>";

}

	//print_r($publishListArray);
	//json_encode list array to prepare for API submisson
	$publishListArray_encoded = json_encode($publishListArray);

	//var_export($publishListArray_encoded);

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
			echo "    Published successfully</br>";
			fwrite($myfile, "Published successfully" . "\t");
		}
	}


fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");

*/

fclose($file_handle);
fclose($myfile);
?>