<?php

print("</br><a href='recode.html'>Back to recode tool</a>");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

error_reporting(E_ALL);

echo "<p>Starting</p>";

//*********GET DATE**********************

$date = date('Y-m-d\TH:i:s');
// $date1 = "2015-12-21T15:44:36";


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

//**********CREATE LOG FILE TO WRITE OUTPUT*

$myfile = fopen("../../report_files/recode_output.log", "a") or die("Unable to open recode_output.log");
fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");
fwrite($myfile, "List name" . "\t" . "List ID" . "\t" . "Item UUID" . "\t" . "Item deleted" . "\t" . "List Published" . "\r\n");

//************SET_VARIABLES***********
//uncomment if you want to set these permanently.. good idea tbh!
/*
	$shortCode = "";
	$clientID = "";
	$secret = "";
	$TalisGUID = "";
*/

$tokenURL = 'https://users.talis.com/oauth/tokens';
$content = "grant_type=client_credentials";

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
	echo "<p>    ERROR: There was an error getting a token:</p><pre>" . var_export($return, true) . "</pre>";
} else {
	echo "    Got Token</br>";
}

curl_close($ch);

$jsontoken = json_decode($return);

if (!empty($jsontoken->access_token)){
	$token = $jsontoken->access_token;
} else {
	echo "<p>    ERROR: Unable to get an access token</p>";
	exit;
}


//***********READ**DATA******************

$row = 1;
if (($file_handle = fopen($uploadfile, "r")) !== FALSE) {
	while (($line = fgetcsv($file_handle, 1000, "\t")) !== FALSE) {

		$num = count($line);
		$row++;

		$listId = $line[0];
		$newCode = $line[1];
		$listTitle = $line[2];

		echo $listId . "\t";
		echo $newCode . "\t";
		echo $listTitle . "\t";
		echo "</br>";

//************GRAB**LIST**DETAILS*************
$list_lookup = 'https://rl.talis.com/3/' . $shortCode . '/lists/' . $listId;

	$ch1 = curl_init();

		curl_setopt($ch1, CURLOPT_URL, $list_lookup);
		curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
	
			"X-Effective-User: $TalisGUID",
			"Authorization: Bearer $token",
			'Cache-Control: no-cache'
	
	));
		$output1 = curl_exec($ch1);

  		$info1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
		$output_json1 = json_decode($output1);
		curl_close($ch1);

		if ($info1 !== 200){
			echo "<p>ERROR: There was an error getting the draft item information:</p><pre>" . var_export($output, true) . "</pre>";
			continue;
		} else {
			echo "</br> Got list information</br></br>";
		}

		$assoc_listid = $output_json1->data->id;
		echo "    list_id: " . $assoc_listid . "</br>";
		$title = $output_json1->data->attributes->title;
		echo "    Title: " . $title . "</br>";
		$orig_node = $output_json1->data->relationships->nodes->data[0]->id;
		echo "    Original Node: " . $orig_node . "</br>";
		
		fwrite($myfile, $title . "\t");
		fwrite($myfile, $assoc_listid . "\t");
		fwrite($myfile, $orig_node . "\t");


	
	//**************UPDATE_LIST_TITLE***************

	$input = 	'{
					"data": {
						"id": "' . $listId . '",
						"type": "lists",
						"attributes": {
							"title": "' . $listTitle . '"
							}
						}
				}';

	$ch2 = curl_init();

	curl_setopt($ch2, CURLOPT_URL, $list_lookup);
	curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, 'PATCH');
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
		echo "<p>ERROR: There was an error updating the list title:</p><pre>" . var_export($output2, true) . "</pre>";
		fwrite($myfile, "title not updated - failed" . "\t");
		continue;
	} else {
		echo "    Title Updated to: " . $listTitle . " for list: $listId</br>";
		fwrite($myfile, "Title Updated to: " . $listTitle . " for list: $listId \t");
	}

	//**************UPDATE_LIST_PARENT***************

	$node_lookup = $list_lookup . '/relationships/nodes';

		$input1 = 	'{
						"data": [
							{
								"id": "' . $newCode . '",
								"type": "modules"
							}
						]
					}';

	$ch3 = curl_init();

	curl_setopt($ch3, CURLOPT_URL, $node_lookup);
	curl_setopt($ch3, CURLOPT_CUSTOMREQUEST, 'PATCH');
	curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch3, CURLOPT_HTTPHEADER, array(

	"X-Effective-User: $TalisGUID",
	"Authorization: Bearer $token",
	'Cache-Control: no-cache'
	));

	curl_setopt($ch3, CURLOPT_POSTFIELDS, $input1);


	$output3 = curl_exec($ch3);
	$info3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);

	curl_close($ch3);
	if ($info3 !== 200){
	echo "<p>ERROR: There was an error updating the list parent node:</p><pre>" . var_export($output3, true) . "</pre>";
	fwrite($myfile, "Node update failed" . "\t");
	continue;
	} else {
	echo "    Parent node Updated to: " . $newCode . " for list: $listId</br>";

	fwrite($myfile, "Parent node Updated to: $newCode for list: $listId \t");
	}

	fwrite($myfile, "\n");
	echo "End of Record.";
	echo "---------------------------------------------------</br></br>";
}

fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");

fclose($file_handle);
}
fclose($myfile);
?>