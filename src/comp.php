<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<p>Starting</p>";

//*****************GRAB_INPUT_DATA**********

$uploaddir = 'uploads/';
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

$shortCode = $_REQUEST['SHORT_CODE'];

echo "Tenancy Shortcode set: " . $shortCode;
echo "</br>";

$clientID = $_REQUEST['CLIENT_ID'];

echo "Client ID set: " . $clientID;
echo "</br>";

$secret = $_REQUEST['CLIENT_SEC'];

echo "Client secret set: " . $secret;
echo "</br>";

$TalisGUID = $_REQUEST['GUID'];

echo "User GUID to use: " . $TalisGUID;
echo "</br>";
echo "</br>";

//**********CREATE LOG FILE TO WRITE OUTPUT*

$myfile = fopen("output.log", "a") or die("Unable to open output.log");
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
	$barc = trim($parts[0]);
	$item_lookup = 'https://rl.talis.com/3/' . $shortCode . '/draft_items/' . $barc;

	//************GRAB**A LIST_ID*************

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

		if ($info4 !== 200){
			echo "<p>ERROR: There was an error getting the draft item information:</p><pre>" . var_export($output, true) . "</pre>";
			continue;
		} else {
			echo "    Got item draft information</br>";
		}
	
		$assoc_listid = $output_json2->data->relationships->list->data->id;


		echo "    list_id: " . $assoc_listid . "</br>";
		echo "</br>";
	/*
	//************GRAB**AN**ETAG***************

	$list_lookup = 'https://rl.talis.com/3/' . $shortCode . '/draft_lists/' . $assoc_listid;

	$ch1 = curl_init();
	
	curl_setopt($ch1, CURLOPT_URL, $list_lookup);
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
	
	//**************DELETE_ITEM***************
	$patch_url = 'https://rl.talis.com/3/' . $shortCode . '/draft_items/';

	$input = '	{
					"meta": {
						"list_etag": "' . $etag . '",
						"list_id": "' . $listID . '"
					}
				}';

	//**************PARAGRAPH POST*****************

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
	if ($info2 !== 201){
		echo "<p>ERROR: There was an error deleting the item:</p><pre>" . var_export($output2, true) . "</pre>";
		fwrite($myfile, "Item not deleted - failed" . "\t");
		continue;
	} else {
		echo "    Deleted item $barc from list $listID</br>";
		fwrite($myfile, "Item deleted successfully" . "\t");
	}

	//************GRAB**AN**ETAG**AGAIN*************

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

	//**************PUBLISH**LIST***************
	$patch_url2 = 'https://rl.talis.com/3/' . $shortCode . '/draft_lists/' . $listID . '/publish_actions';
	$input2 = '{
				"data": {
					"type": "list_publish_actions"
				},
				"meta": {
					"has_unpublished_changes": "true",
					"list_etag": "' . $etag2 . '",
					"list_id": "' . $listID . '"
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
		continue;
	} else {
		echo "    Published changes to $listID</br>";
		fwrite($myfile, "Published successfully" . "\t");
	}

	fwrite($myfile, "\n");
	echo "End of Record.";
	echo "---------------------------------------------------</br></br>";
*/
}

fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");

fclose($file_handle);
fclose($myfile);

print("</br></br><a href='index.html'>Back</a>");

?>