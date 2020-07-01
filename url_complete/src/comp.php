<?php

print("</br><a href='url.html'>Back to url tool</a>");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<p>Starting</p>";
//***********FUNCTIONS *******************/

function modify_url($resourceID, $web_addresses, $oldURL_index, $newURL) {
	
	$web_addresses[$oldURL_index] = $newURL;  // update the found address

	$template = '{
				"data": {
					"type": "resources",
					"id": "' . $resourceID . '",
					"attributes": {
						"web_addresses": [],
						"online_resource": {
							"source": "uri",
							"link": "' . $newURL. '"
						}
					} 
				}
			}';
	$template_obj = json_decode($template, true);
	$template_obj['data']['attributes']['web_addresses'] = $web_addresses;

	return json_encode($template_obj);
}

function post_url($resourceID, $input, $TalisGUID, $token) {
	$patch_url = "https://rl.talis.com/3/" . $shortCode . "/resources/" . $resourceID;
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


//**********CREATE LOG FILE TO WRITE OUTPUT*

$myfile = fopen("../../report_files/urlcomplete_output.log", "a") or die("Unable to open urlcomplete_output.log");
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

$row = 1;
if (($file_handle = fopen($uploadfile, "r")) !== FALSE) {
	while (($line = fgetcsv($file_handle, 1000, ",")) !== FALSE) {

		$num = count($line);
		$row++;

		$itemID = $line[0];
		$oldURL = $line[1];
		$newURL = $line[2];

		echo $itemID . "\t";
		echo $oldURL . "\t";
		echo $newURL . "\t";
		echo "</br>";

//************GET_RESOURCE_ID***************

$item_lookup = "https://rl.talis.com/3/" . $shortCode . "/draft_items/" . $itemID . "?include=resource";
// echo $item_lookup;


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
$resourceID = $output_json->included[0]->id;

//************GET_URL_INFO***************

$online_resource =  $output_json->included[0]->attributes->online_resource->link;

	echo "\t Item URL: " . $self . "<br>";
	fwrite($myfile, $self ."\t");
	echo "\t Resource ID: " . $resourceID . "<br><br>";
	fwrite($myfile, $resourceID ."\t");
	echo "\t Online Resouce: " . $online_resource . "<br>";
	fwrite($myfile, $online_resource ."\t");

	if ($online_resource !== $oldURL) {
		echo "\t no online resource match found for $online_resource <br><br>";
	}
	else {
		echo "\t we found a online resource match of $online_resource <br><br>";
	}
	
$web_addresses = $output_json->included[0]->attributes->web_addresses;

	$oldURL_found = array_search($oldURL, $web_addresses);
	
	if (isset($oldURL_found)) {
		echo "\t found matching old URL: $oldURL - at web address array index: $oldURL_found";
		
		$input = modify_url($resourceID, $web_addresses, $oldURL_found, $newURL);
		post_url($resourceID, $input, $TalisGUID, $token);

	} else {
		echo "\t no matching URL found in web address array. Moving onto next row";
		continue;
	}

	}

}
fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");

fclose($file_handle);
fclose($myfile);
	
?>