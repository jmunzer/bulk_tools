<?php

print("</br><a href='url.html'>Back to url tool</a>");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<p>Starting...</p>";

// Functions go here

function modify_url($resourceID, $web_addresses, $newURL) {

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

function post_url($shortCode, $resourceID, $input, $TalisGUID, $token, $myfile) {
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
	
	if ($info2 !== 200){
		echo "<p> - ERROR: There was an error updating the URL:</p><pre>" . var_export($output2, true) . "</pre></br>";
		fwrite($myfile, " - ERROR: Resource URL Not Updated");
	} else {
		echo " - Resource URL Updated Successfully</br>";
		fwrite($myfile, " - Resource URL Updated Successfully");
	}
}

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

$LOG_LEVEL = 'DEBUG';

if(isset($_REQUEST['DRY_RUN']) &&
	$_REQUEST['DRY_RUN'] == "writeToLive") {
		$shouldWritetoLive = "true";
	}
	else
	{
		$shouldWritetoLive = "false";
	}

echo "Writing to live tenancy?: $shouldWritetoLive";
echo "</br>";
echo "</br>";

	$tokenURL = 'https://users.talis.com/oauth/tokens';
	$content = "grant_type=client_credentials";
	$date = date('Y-m-d\TH:i:s'); // "2015-12-21T15:44:36"

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

	// Creating a report file...
$logfile = "../../report_files/urlcomplete_output.log";
$myfile = fopen($logfile, "a") or die("Unable to open urlcomplete_output.log");
fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");

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

		$itemID = trim($line[0]);
		$oldURL = trim($line[1]);
		$newURL = trim($line[2]);

		echo $itemID . "\t";
		echo $oldURL . "\t";
		echo $newURL . "\t";

		// TODO check if the values are URLs.

		// Build function-select logic here

		if(empty($oldURL) && empty($newURL)){
			// this is a problem
			// TODO - add error message logging
			continue;
		}

		if (empty($oldURL)) {
			//point at 'add url' function
			add_url($itemID, $newURL);

		} elseif (empty($newURL)) {
			// point at 'delete url' function
			delete_url($itemID, $oldURL);
		} else {
			// point at 'url swap' function
			replace_url($itemID, $oldURL, $newURL);
		}
	}
}

function add_url($itemID, $newURL) {
	// get the item
	$item = get_item();
	$resource = get_resource($item);
	// get the existing web addresses
	$web_addresses = get_web_addresses($resource);
	// add a new web addresses to the existing ones
	$web_addresses = array_push($web_addresses, $newURL);
	// add an online resource
	// if not a dry run - update
	if($shouldWritetoLive){
		modify_url($resource['id'], $web_addresses, $newURL);
	} else {
		// log something
	}
}

function delete_url($itemID, $oldURL) {
	// get the item
	// get the existing web addresses
	// check that the web address to remove is present
	// remove it
	// remove it from the online resource if set
	// if not a dry run - update
}

function replace_url($itemID, $oldURL, $newURL){
	// get the item
	// get the existing web addresses
	// check that the web address to replace is present
	// remove the old and the new
	//$web_addresses[$oldURL_index] = $newURL;  // update the found address
	// update the online resource
	// if not a dry run - update
}

function echo_message_to_screen($log_level, $message){
	// TODO Change this to use numerical comparison so can output all log messages of level and above
	//DEBUG
	if ($LOG_LEVEL == 'DEBUG' && $log_level == 'DEBUG') {
		echo "DEBUG: $message";
	}
	//INFO
	if ($LOG_LEVEL == 'INFO' && $log_level == 'INFO') {
		echo "INFO: $message";
	}
	//WARNING
	if ($LOG_LEVEL == 'WARNING' && $log_level == 'WARNING') {
		echo "WARNING: $message";
	}
	//ERROR
	if ($LOG_LEVEL == 'ERROR' && $log_level == 'ERROR') {
		echo "ERROR: $message";
	}
}

//************GET_RESOURCE_ID***************

$item_lookup = "https://rl.talis.com/3/" . $shortCode . "/draft_items/" . $itemID . "?include=resource";

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
		echo "<p>ERROR: There was an error getting the draft item:</p><pre>" . var_export($output, true) . "</pre>";
		fwrite($myfile, "ERROR: There was an error getting the draft item for " . $itemID . var_export($output, true) . "\r\n");
		continue;
	}

$self = $output_json->data->links->self;
$resourceID = $output_json->included[0]->id;

//************GET_URL_INFO***************

	fwrite($myfile, $self ."\t");
	fwrite($myfile, $resourceID ."\t");
	
$web_addresses = $output_json->included[0]->attributes->web_addresses;

	// TODO move this into either of the replace and delete functions
	$oldURL_found = array_search($oldURL, $web_addresses);
	
	if (isset($oldURL_found)) {
		echo "Found Matching URL \t";
		fwrite($myfile, "Found Matching URL at index: [$oldURL_found]");
		$input = modify_url($resourceID, $web_addresses, $oldURL_found, $newURL);

		if ($shouldWritetoLive == "true") {
			post_url($shortCode, $resourceID, $input, $TalisGUID, $token, $myfile);
		} else {
			echo "Resource URL Not Updated - Dry Run </br>";
			fwrite($myfile, " - Resource URL Not Updated - Dry Run \r\n");
		}

	} else {
		echo "\t ERROR: no matching URL found in web address array. Moving onto next row... </br>";
		fwrite($myfile, "ERROR: no matching URL found in web address array. Moving onto next row... \r\n"); 
		continue;
	}



fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");

fclose($file_handle);
fclose($myfile);

print("</br><a href=$logfile>Click Here to download your output.log file.</a>");

?>

