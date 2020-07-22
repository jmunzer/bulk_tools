<?php

print("</br><a href='url.html'>Back to url tool</a>");

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
ini_set('max_execution_time', '600');
// error_reporting(E_ALL);

echo "<p>Starting...</p>";

// User set variables
// Get the user config file. This script will fail disgracefully if it has not been created and nothing will happen.
require('../../user.config.php');

echo "Tenancy Shortcode set: " . $shortCode;
echo "</br>";

echo "Client ID set: " . $clientID;
echo "</br>";

echo "User GUID to use: " . $TalisGUID;
echo "</br>";

// Test run or live run switch
if(isset($_REQUEST['DRY_RUN']) &&
	$_REQUEST['DRY_RUN'] === "writeToLive") {
		$shouldWritetoLive = true;
	}
	else
	{
		$shouldWritetoLive = false;
	}

echo "Writing to live tenancy?: $shouldWritetoLive";
echo "</br>";

// Constants
$tokenURL = 'https://users.talis.com/oauth/tokens';
$content = "grant_type=client_credentials";
$date = date('Y-m-d\TH:i:s'); // "2015-12-21T15:44:36"
$COUNTERS=[];

// Error reporting constants
const DEBUG = 4;
const INFO = 3;
const WARNING = 2;
const ERROR = 1;

// Error reporting User select
// This currently defaults to ERROR as the first select in url.html
$LOG_LEVEL = $_REQUEST['loglvl'];
echo "Logging Level Selected: " . get_friendly_log_level_name($LOG_LEVEL) . "</br>";

// Pull in user file
$uploaddir = '../uploads/';
$uploadfile = $uploaddir . basename($_FILES['userfile']['name']);

echo 'File uploaded: ';
if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
	echo_message_to_screen(INFO, "File is valid, and was successfully uploaded.");
} else {
	exit("File is invalid and failed to upload - Please click back and try again.");
}

print_r($uploadfile);
echo "</br>";
echo "</br>";

// Create a report file...
$logfile = "../../report_files/url_output.csv";
$myfile = fopen($logfile, "a") or die("Unable to open url_output.csv");
// Write column headers
fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");
fwrite($myfile, "Write To Live Tenancy?: $shouldWritetoLive | User GUID: $TalisGUID \r\n\r\n");
fwrite($myfile, "item id,old URL,new URL,transaction type,resource id,current web_address array,current online resource,match array,patch status\r\n");

// Get an API token
$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $tokenURL);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_USERPWD, "$clientID:$secret");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $content);

	$return = curl_exec($ch);
	$info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
	if ($info !== 200){
		echo_message_to_screen(ERROR, "Unable to retrieve an API token: <pre>" . var_export($return, true) . "</pre> - Process Stopped.");
		exit;
	} 

curl_close($ch);

$jsontoken = json_decode($return);

if ($jsontoken === null ) {
	echo_message_to_screen(ERROR, "API token response was empty (NULL returned) - Process Stopped.")
	exit;
} else {
	if (!empty($jsontoken->access_token)){
			$token = $jsontoken->access_token;
			echo_message_to_screen(DEBUG, "Successfully retrieved an API token: $token");
		} else {
			echo_message_to_screen(ERROR, "API access_token was retrieved but is empty - Process Stopped.");
			exit;
		}
	}

// Read File
$row = 0;
$file_handle = fopen($uploadfile, "r")
if ($file_handle == FALSE) {
	echo_message_to_screen(ERROR, "Could not open csv file - Process Stopped.");
    exit;
}
while (($line = fgetcsv($file_handle, 1000, ",")) !== FALSE) {

	$row++;
	$num = count($line);
		
	$itemID = trim($line[0]);
	$oldURL = filter_var(trim($line[1]), FILTER_VALIDATE_URL);
	$newURL = filter_var(trim($line[2]), FILTER_VALIDATE_URL);

	echo_message_to_screen(INFO, "</br> ---- ---- ---- ---- ---- ---- </br>");

	if (!empty ($itemID)) {
		echo_message_to_screen(DEBUG, "Item ID: $itemID \t");
		fwrite($myfile,$itemID . ",");
	} else {
		fwrite($myfile,",");
	}

	if (!empty ($oldURL)) {
		echo_message_to_screen(DEBUG, "Old URL: $oldURL \t");
		fwrite($myfile,$oldURL . ",");
	} else {
			fwrite($myfile,",");
	}

	if (!empty ($newURL)) {
		echo_message_to_screen(DEBUG, "New URL: $newURL \t");
		fwrite($myfile,$newURL . ",");
	} else {
		fwrite($myfile,",");
	}

		// Function-select logic
	if(empty($oldURL) && empty($newURL)){
		// this is a problem
		echo_message_to_screen(WARNING, "WARNING: Row " . $row . " does not appear to have either an old URL or a new URL. Moving onto next item...");
		fwrite($myfile, "\r\n");
		continue;
	}

	if (empty($oldURL)) {
		//point at 'add url' function
		add_url($itemID, $newURL, $shortCode, $TalisGUID, $token);
	} elseif (empty($newURL)) {
		// point at 'delete url' function
		delete_url($itemID, $oldURL, $shortCode, $TalisGUID, $token);
	} else {
		// point at 'url replace' function
		replace_url($itemID, $oldURL, $newURL, $shortCode, $TalisGUID, $token);
	}
				
    if($row <= 10 || $row % 10 == 0 ){
	   echo_message_to_screen(INFO, "Processed $row rows");
	// echo "Processed $row rows </br>";
	}
}


function echo_message_to_screen($log_level, $message){
    global $LOG_LEVEL;
    // echo the log message if the log level says we should.
    if ($LOG_LEVEL >= $log_level) {
        $friendly_name = get_friendly_log_level_name($log_level);
        echo "</br><strong>{$friendly_name}</strong>: $message";
    }
}
function get_friendly_log_level_name($log_level) {
    // map log levels to friendly names for humans
    $log_level_map = [
        4 => "DEBUG",
        3 => "INFO",
        2 => "WARN",
        1 => "ERROR"
    ];
    return $log_level_map[$log_level];
}

function increment_counter($counter_name) {
	global $COUNTERS;
	if (! isset($COUNTERS[$counter_name])) {
        $COUNTERS[$counter_name] = 1;
    } else {
        $COUNTERS[$counter_name] += 1;
	}
}

function counter_summary() {
	global $COUNTERS;
    foreach ($COUNTERS as $k => $v) {
		echo "</br>$k $v";
    }
}

function get_resource_id($resource_data) {
	global $myfile;

	if (! empty( $resource_data->included[0]->id )) {
		$resource = $resource_data->included[0]->id;
		echo_message_to_screen(DEBUG, "Resource ID: $resource \t");
		fwrite($myfile,$resource . ",");
		return $resource;	
	} 
	fwrite($myfile,",");
	return false;
}

function get_webaddress_array($resource_data) {
	global $myfile;

	if (! empty( $resource_data->included[0]->attributes->web_addresses )) {
		$web_addresses = $resource_data->included[0]->attributes->web_addresses;		
		echo_message_to_screen(DEBUG, print_r($web_addresses, TRUE));
		$web_address_string = join(' | ', $web_addresses);
		fwrite($myfile, $web_address_string . ",");
		return $web_addresses;
	} 
	echo_message_to_screen(INFO, "Web Address Array is empty\t");
	fwrite($myfile,"Web Address Array is empty,");
	return false;
}

function get_online_resource($resource_data) {
	global $myfile;

	if (! empty( $resource_data->included[0]->attributes->online_resource->link )) {
		$online_resource = $resource_data->included[0]->attributes->online_resource->link;	
		echo_message_to_screen(DEBUG, "Online resource currently set: $online_resource \t");
		fwrite($myfile, $online_resource . ",");
		return $online_resource;
	} 
	echo_message_to_screen(INFO, "Online Resource is not set\t");
	fwrite($myfile,"Online Resource is not set,");
	return false;
}

function add_url($itemID, $newURL, $shortCode, $TalisGUID, $token) {
	global $myfile;
	global $shouldWritetoLive;

	echo_message_to_screen(INFO, "add\t");
	fwrite($myfile,"add" . ",");

	// get the resource
	$resource_data = get_item($shortCode, $itemID, $TalisGUID, $token);
	if($resource_data) {
		// get the existing web addresses
		$resource_id = get_resource_id($resource_data);
		$web_address_array = get_webaddress_array($resource_data);
		// for reporting purposes only ->
		$online_resource = get_online_resource($resource_data);
		fwrite($myfile, ",");
		// if there is no existing web addresses it is OK to proceed to add some.
		// but we need to make sure that the array is present to add to.
		if (!is_array($web_address_array)) {
			$web_address_array = [];
		}
		// add a new web addresses to the existing ones
		array_push($web_address_array, $newURL);
		// build the PATCH body
		$body = build_patch_body($resource_id, $web_address_array, $newURL);
		echo_message_to_screen(DEBUG, "add_url patch request body: $body");
		// if not a dry run - update
		if ($shouldWritetoLive === true) {
			post_url($shortCode, $resource_id, $body, $TalisGUID, $token);
		} else {
			fwrite($myfile, "Test Run\r\n");
		}
		increment_counter('URLs added: ');
	}
}

function delete_url($itemID, $oldURL, $shortCode, $TalisGUID, $token) {
	global $myfile;
	global $shouldWritetoLive;

	echo_message_to_screen(INFO, "delete \t");
	fwrite($myfile,"delete" . ",");

	$resource_data = get_item($shortCode, $itemID, $TalisGUID, $token);
	if($resource_data) {
		// get the existing web addresses
		$resource_id = get_resource_id($resource_data);
		$web_address_array = get_webaddress_array($resource_data);
		$online_resource = get_online_resource($resource_data);
		// if we do have web addresses that we can delete...
		if ($web_address_array){

			// add a new web addresses to the existing ones
			$web_address_array = check_web_addresses($oldURL, "", $web_address_array, "delete");
			// build the PATCH body
			$body = build_patch_body($resource_id, $web_address_array, "");
			
			// check online resource
			$body = check_online_resource($oldURL, $online_resource, $body);
			echo_message_to_screen(DEBUG, "delete_url patch request body: $body");
			// if not a dry run - update
			if ($shouldWritetoLive === true) {
				post_url($shortCode, $resource_id, $body, $TalisGUID, $token);
			} else {
				fwrite($myfile, "Test Run\r\n");
			}
			increment_counter('URLs deleted: ');
		} else {
			increment_counter('Delete operations with no web addresses on item: ');
		}
		increment_counter('URLs deleted: ');
	}
}

function replace_url($itemID, $oldURL, $newURL, $shortCode, $TalisGUID, $token){
	global $myfile;
	global $shouldWritetoLive;
	
	echo_message_to_screen(INFO, "replace\t");
	fwrite($myfile,"replace" . ",");

	$resource_data = get_item($shortCode, $itemID, $TalisGUID, $token);
	if($resource_data) {
		// get the existing web addresses
		$resource_id = get_resource_id($resource_data);
		$web_address_array = get_webaddress_array($resource_data);
		$online_resource = get_online_resource($resource_data);

		if ($web_address_array) {
			// add a new web addresses to the existing ones
			$web_address_array = check_web_addresses($oldURL, $newURL, $web_address_array, "replace");
			// build the PATCH body
			$body = build_patch_body($resource_id, $web_address_array, $newURL);
			echo_message_to_screen(DEBUG, "replace_url patch request body: $body");
			// if not a dry run - update
			if ($shouldWritetoLive === true) {
				post_url($shortCode, $resource_id, $body, $TalisGUID, $token);
			} else {
				fwrite($myfile, "Test Run\r\n");
			}
			increment_counter('URLs replaced: ');
		} else {
			fwrite($myfile, "Resource URL Not Updated - Dry Run");
		}
		increment_counter('URLs replaced: ');
	}
	// get the item
	// get the existing web addresses
	// check that the web address to replace is present
	// remove the old and the new
	//$web_addresses[$oldURL_index] = $newURL;  // update the found address
	// update the online resource
	// if not a dry run - update
}

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
	

	if ($info1 !== 200){
		echo_message_to_screen(WARNING, "WARNING: Unable to retrieve the resource data: <pre>" . var_export($output, true) . "</pre>");
		return false;
	} else {
		echo_message_to_screen(DEBUG, "Successfully retrieved the resource data: <pre>" . var_export($output, true) . "</pre>");
		return $output_json;
	}

}

function check_web_addresses($oldURL, $newURL, $web_address_array, $mode) {
	global $myfile;
	$oldURL_found = array_search($oldURL, $web_address_array);
	
	if (isset($oldURL_found)) {
		if($mode == "delete") {
			unset($web_address_array[$oldURL_found]);
		} 
		if($mode == "replace") {
			$web_address_array[$oldURL_found] = $newURL;
		}
		echo_message_to_screen(INFO, "Found Matching URL at index: [$oldURL_found] \t");
		fwrite($myfile,$oldURL_found . ",");
	} else {
		echo_message_to_screen(WARNING, "WARNING: no matching URL found in web address array. Moving onto next row... \t");
		fwrite($myfile,"No matching URL found" . ",");
	}
	return $web_address_array;
}

function check_online_resource($oldURL, $online_resource, $body) {
	$body_decoded = json_decode($body, true);
	$body_decoded['data']['attributes']['online_resource'] = null;
	return json_encode($body_decoded);	
}

function build_patch_body($resourceID, $web_addresses, $newURL) {

	$template = '{
				"data": {
					"type": "resources",
					"id": "' . $resourceID . '",
					"attributes": {
						"web_addresses": [],
						"online_resource": {
							"source": "uri",
							"link": "' . $newURL . '"
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
		echo_message_to_screen(WARNING, "WARNING: Resource URL Not Updated: <pre>" . var_export($output2, true) . "</pre>");
		fwrite($myfile,"Resource URL Not Updated\r\n");
	} else {
		echo_message_to_screen(DEBUG, "Resource URL Updated Successfully</br>");
		fwrite($myfile,"Resource URL Updated Successfully\r\n");
	}
}

counter_summary();

fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");

fclose($file_handle);
fclose($myfile);

print("</br></br><a href=$logfile>Click Here to download your output.csv file.</a>");
?>
