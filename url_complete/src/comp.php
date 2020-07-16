<?php

print("</br><a href='url.html'>Back to url tool</a>");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<p>Starting...</p>";

// Functions go here

function counter_add() {
	$filename = '/counter_add.txt';
	$fp = fopen($filename,"r");
	$counter = fread($fp, filesize($filename));
	fclose($fp);

	++$counter;
	echo "$counter rows processed";

	$fp = fopen($filename,"w");
	$fwrite($fp, $counter);
	fclose($fp);
}

function counter_del() {
	
}

function counter_repl() {
	
}

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

function post_url($shortCode, $resourceID, $body, $TalisGUID, $token) {
	global $myfile;

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

	curl_setopt($ch2, CURLOPT_POSTFIELDS, $body);

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
			add_url($itemID, $newURL, $shortCode, $TalisGUID, $token);
		//	counter_add();
		} elseif (empty($newURL)) {
			// point at 'delete url' function
			delete_url($itemID, $oldURL);
		} else {
			// point at 'url swap' function
			replace_url($itemID, $oldURL, $newURL);
		}
	}
}

function get_resource_id($resource_data) {

	if (! empty( $resource_data->included[0]->id )) {
		$resource = $resource_data->included[0]->id;
		echo "resource ID: $resource" . "</br>";
		return $resource;	
	} 
	return false;
}

function get_webaddress_array($resource_data) {
	echo "web address array: ";
	if (! empty( $resource_data->included[0]->attributes->web_addresses )) {
		$web_addresses = $resource_data->included[0]->attributes->web_addresses;
		
		print_r($web_addresses);
		echo "</br>";
		return $web_addresses;
	} 
	return false;
}

function get_online_resource($resource_data) {
	echo "view online button: ";
	if (! empty( $resource_data->included[0]->attributes->online_resource->link )) {
		$online_resource = $resource_data->included[0]->attributes->online_resource->link;	
	echo $online_resource . "</br>";
	return $online_resource;
	} 
	return false;
}

function add_url($itemID, $newURL, $shortCode, $TalisGUID, $token) {
	global $myfile;
	global $shouldWritetoLive;
	echo "\t add_url activated </br></br>";

	// get the resource
	$resource_data = get_item($shortCode, $itemID, $TalisGUID, $token);
	// get the existing web addresses
	$resource_id = get_resource_id($resource_data);
	$web_address_array = get_webaddress_array($resource_data);
	// add a new web addresses to the existing ones
	array_push($web_address_array, $newURL);
	// build the PATCH body
	$body = modify_url($resource_id, $web_address_array, $newURL);
	// if not a dry run - update
	if ($shouldWritetoLive == "true") {
		post_url($shortCode, $resource_id, $body, $TalisGUID, $token);
	} else {
		echo "Resource URL Not Updated - Dry Run";
		fwrite($myfile, "Resource URL Not Updated - Dry Run");
	}
	
}

function delete_url($itemID, $oldURL) {
	echo "delete_url activated </br></br>";
	// get the item
	// get the existing web addresses
	// check that the web address to remove is present
	// remove it
	// remove it from the online resource if set
	// if not a dry run - update
}

function replace_url($itemID, $oldURL, $newURL){
	echo "replace_url activated </br></br>";
	// get the item
	// get the existing web addresses
	// check that the web address to replace is present
	// remove the old and the new
	$online_resource = get_online_resource($resource_data);	
	//$web_addresses[$oldURL_index] = $newURL;  // update the found address
	// update the online resource
	// if not a dry run - update
}
/*
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
*/
//************GET_RESOURCE_ID***************

function get_item($shortCode, $itemID, $TalisGUID, $token) {
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
	
	// TODO - remove this? 
	// $self = $output_json->data->links->self;


	
	if ($info1 !== 200){
		echo "<p>ERROR: There was an error getting the resource ID:</p><pre>" . var_export($output, true) . "</pre>";
		// continue removed... need to figure out where to put it..
	} else {
		return $output_json;
	}

}

//************GET_URL_INFO***************

function check_web_addresses($oldURL, $item) {
	global $myfile;
	// TODO move this into either of the replace and delete functions
	$oldURL_found = array_search($oldURL, $web_addresses);
	
	if (isset($oldURL_found)) {
		echo "Found Matching URL \t";
		fwrite($myfile, "Found Matching URL at index: [$oldURL_found]");
		$body = modify_url($resourceID, $web_addresses, $oldURL_found, $newURL);

		if ($shouldWritetoLive == "true") {
			post_url($shortCode, $resourceID, $body, $TalisGUID, $token);
		} else {
			echo "Resource URL Not Updated - Dry Run </br>";
			fwrite($myfile, " - Resource URL Not Updated - Dry Run \r\n");
		}

	} else {
		echo "\t ERROR: no matching URL found in web address array. Moving onto next row...";
		// removed Continue;
	}



fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");

fclose($file_handle);
fclose($myfile);

print("</br><a href=$logfile>Click Here to download your output.log file.</a>");
}
?>