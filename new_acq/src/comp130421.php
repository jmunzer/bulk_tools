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



//**************DELETE_ITEM*************** 
$patch_url = 'https://rl.talis.com/3/' . $shortCode . '/draft_items/' . $barc;
 
$input = '  {
                "meta": {
                    "list_etag": "' . $etag . '",
                    "list_id": "' . $assoc_listid . '"
                }
            }';
 
//**************POST_THE_DELETE************
 
$ch2 = curl_init();
 
curl_setopt($ch2, CURLOPT_URL, $patch_url);
curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, 'DELETE');
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
if ($info2 !== 200){
    echo "<p>ERROR: There was an error deleting the item:</p><pre>" . var_export($output2, true) . "</pre>";
    fwrite($myfile, "Item not deleted - failed" . "\t");
    continue;
} else {
    echo "    Deleted item $barc from list $assoc_listid</br>";
    fwrite($myfile, "Item deleted successfully" . "\t");
}
 
//************GRAB**AN**ETAG**AGAIN*************
 
$list_lookup = 'https://rl.talis.com/3/' . $shortCode . '/draft_lists/' . $assoc_listid;
 
$ch5 = curl_init();
 
curl_setopt($ch5, CURLOPT_URL, $list_lookup);
curl_setopt($ch5, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch5, CURLOPT_HTTPHEADER, array(
 
    "X-Effective-User: $TalisGUID",
    "Authorization: Bearer $token",
    'Cache-Control: no-cache'
 
));
$output5 = curl_exec($ch5);
$info5 = curl_getinfo($ch5, CURLINFO_HTTP_CODE);
$output_json3 = json_decode($output5);
curl_close($ch5);
 
$etag2 = $output_json3->data->meta->list_etag;
echo "    Updated ETag: " . $etag2 . "</br>";
echo "    ---------------------------------------------------";
echo "</br>";







fclose($file_handle);
fclose($myfile);
?>