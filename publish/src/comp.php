<?php

print("</br><a href='publish.html'>Back to Publish tool</a>");

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

$publishListArray = array();

//**********CREATE LOG FILE TO WRITE OUTPUT*

$myfile = fopen("../../report_files/publish_output.log", "a") or die("Unable to open publish_output.log");
fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");
fwrite($myfile, "List name" . "\t" . "List ID" . "\t" . "List Published" . "\r\n");

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

	$line_of_text = fgets($file_handle);
	$parts = explode(" ", $line_of_text);
	
	//************GRAB**AN**ETAG***************

	$listID = trim($parts[0]);

	// writing list ID to array for bulk publish POST
	$forListArray = ['type' => 'draft_lists', 'id' => $listID]; //check this $listID value
	array_push($publishListArray, $forListArray);

}

	//print_r($publishListArray);
	//json_encode list array to prepare for API submisson
	$publishListArray_encoded = json_encode($publishListArray);

	//var_export($publishListArray_encoded);

	//**************PUBLISH**LIST***************
	$patch_url2 = 'https://rl.talis.com/3/' . $shortCode . '/bulk_list_publish_actions';
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

	fwrite($myfile, "\n");
	echo "End of Record.";
	echo "---------------------------------------------------</br></br>";


fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");

fclose($file_handle);
fclose($myfile);

?>