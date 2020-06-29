<?php

print("</br><a href='url.html'>Back to url tool</a>");

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

$shortCode = $_REQUEST['SHORT_CODE'];

echo "Tenancy Shortcode set: " . $shortCode;
echo "</br>";

$clientID = $_REQUEST['CLIENT_ID'];

echo "Client ID set: " . $clientID;
echo "<br>";

$secret = $_REQUEST['CLIENT_SEC'];

echo "Client secret set: " . $secret;
echo "<br>";

$TalisGUID = $_REQUEST['GUID'];

echo "User GUID to use: " . $TalisGUID;
echo "<br>";
echo "<br>";

$OLD_URL = $_REQUEST['OLD_URL'];

echo "old URL to modify: " . $OLD_URL;
echo "<br>";

$NEW_URL = $_REQUEST['NEW_URL'];

echo "New URL to use: " . $NEW_URL;
echo "<br>";
echo "<br>";

//**********CREATE LOG FILE TO WRITE OUTPUT*

$myfile = fopen("../../report_files/urlfindreplace_output.log", "a") or die("Unable to open url_output.log");
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

//************READ**DATA******************

$file_handle = fopen($uploadfile, "rb");

while (!feof($file_handle) )  {

	$line_of_text = fgets($file_handle);
	$parts = explode(" ", $line_of_text);

//************GET_RESOURCE_ID***************

$barc = trim($parts[0]);

$item_lookup = "https://rl.talis.com/3/yorksj/draft_items/" . $barc;
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
		echo "authenticated http request: " . $info1;
		echo "<br>";
		echo "<br>";
		$output_json = json_decode($output);
	curl_close($ch1);
	if ($info1 !== 200){
		echo "<p>ERROR: There was an error getting the draft item:</p><pre>" . var_export($output, true) . "</pre>";
		continue;
	} else {
		echo "    Got draft for item </br>";
	}

$self = $output_json->data->links->self;
$resourceID = $output_json->data->relationships->resource->data->id;

	echo "Item URL: " . $self . "<br>";
	fwrite($myfile, $self ."\t");
	echo "Resource ID: " . $resourceID . "<br>";
	fwrite($myfile, $resourceID ."\t");

	//************GET_URL_INFO***************

$patch_url = "https://rl.talis.com/3/yorksj/resources/" . $resourceID;

$ch3 = curl_init();
		
		curl_setopt($ch3, CURLOPT_URL, $patch_url);
		curl_setopt($ch3, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch3, CURLOPT_HTTPHEADER, array(
			
			"X-Effective-User: $TalisGUID",
			"Authorization: Bearer $token",
			'Cache-Control: no-cache'
	
		));
		$output3 = curl_exec($ch3);
		$info3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
		echo "authenticated http request: " . $info3;
		echo "<br>";
		echo "<br>";
		$output_json3 = json_decode($output3);
	curl_close($ch3);
	if ($info3 !== 200){
		echo "<p>ERROR: There was an error getting the resource metadata:</p><pre>" . var_export($output, true) . "</pre>";
		continue;
	} else {
		echo "    Got resource metadata </br>";
	}

$resource_title = $output_json3->data->attributes->title;
$web_addr = $output_json3->data->meta->all_online_resources[0]->original_url;

	echo "Resource Title: " . $resource_title . "<br>";
	fwrite($myfile, $resource_title ."\t");
	echo "Web Address: " . $web_addr . "<br>";
	fwrite($myfile, $web_addr ."\t");
	echo "---------------------------------------------------";
	echo "<br>";

//**************MODIFY_URL***************

$cleaned_url = str_replace($OLD_URL,$NEW_URL,$web_addr);
 echo $cleaned_url;
echo "<br>";
echo "--------------------------------------------------- <br>";

$input = '{
			"data": {
				"type": "resources",
				"id": "' . $resourceID . '",
				"attributes": { 
					"web_addresses": [ "' . $cleaned_url . '" ], 
					"online_resource": {
						"source": "uri",
						"link": "' . $cleaned_url . '"
						}
					} 
				} 
			}';

//**************POST_URL_TO_RESOURCE*****************

		$ch2 = curl_init();

		curl_setopt($ch2, CURLOPT_URL, $patch_url);
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
		// put some if else logic here please!
	}
fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");

fclose($file_handle);
fclose($myfile);
	
?>
