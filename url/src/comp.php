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

//Load the report class so it is available to us.
require('./ReportRow.class.php');

echo "Tenancy Shortcode set: " . $shortCode;
echo "</br>";

echo "Client ID set: " . $clientID;
echo "</br>";

echo "User GUID to use: " . $TalisGUID;
echo "</br>";

// Test run or live run switch
if(isset($_REQUEST['DRY_RUN']) &&
	$_REQUEST['DRY_RUN'] === "writeToLive") {
		$shouldWritetoLive = "true";
	}
	else
	{
		$shouldWritetoLive = "false";
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
// This currently defaults to WARNING as the first select in url.html
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
$r = new ReportRow();
fwrite($myfile, $r->getCsvHeader()."\r\n");

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
	echo_message_to_screen(ERROR, "API token response was empty (NULL returned) - Process Stopped.");
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
$file_handle = fopen($uploadfile, "r");
if ($file_handle == FALSE) {
	echo_message_to_screen(ERROR, "Could not open csv file - Process Stopped.");
    exit;
}

$row = 0;
while (($line = fgetcsv($file_handle, 1000, ",")) !== FALSE) {

	$row++;

	if ( count($line) !== 3) {
		echo_message_to_screen(ERROR, "Number of columns in your CSV file should be 3");
		exit;
	}

	// if this is the first row, detect and remove BOMs from UTF8 files.
	if ($row === 1) {
		trim($line[0], "\\xef\\xbb\\xbf");
	}

	$itemID = trim($line[0]);
	$oldURL = filter_var(trim($line[1]), FILTER_VALIDATE_URL);
	$newURL = filter_var(trim($line[2]), FILTER_VALIDATE_URL);

	echo_message_to_screen(INFO, "</br> ---- ---- ---- ---- ---- ---- </br>");

	$itemReport = new ReportRow();

	if (!empty ($itemID)) {
		echo_message_to_screen(DEBUG, "Item ID: $itemID \t");
		$itemReport->itemID = $itemID;
	}

	if (!empty ($oldURL)) {
		echo_message_to_screen(DEBUG, "Old URL: $oldURL \t");
		$itemReport->oldURL = $oldURL;
	}

	if (!empty ($newURL)) {
		echo_message_to_screen(DEBUG, "New URL: $newURL \t");
		$itemReport->newURL = $newURL;
	}

		// Function-select logic
	if(empty($oldURL) && empty($newURL)){
		// this is a problem
		echo_message_to_screen(WARNING, "WARNING: Row " . $row . " does not appear to have either an old URL or a new URL. Moving onto next item...");
		$row = $itemReport->getCsvRow();
		fwrite($myfile, "$row\r\n");
		continue;
	}

	if (empty($oldURL)) {
		//point at 'add url' function
		add_url($itemID, $newURL, $shortCode, $TalisGUID, $token, $itemReport);
	} elseif (empty($newURL)) {
		// point at 'delete url' function
		delete_url($itemID, $oldURL, $shortCode, $TalisGUID, $token, $itemReport);
	} else {
		// point at 'url replace' function
		replace_url($itemID, $oldURL, $newURL, $shortCode, $TalisGUID, $token, $itemReport);
	}

	$row = $itemReport->getCsvRow();
	fwrite($myfile, "$row\r\n");
	foreach($itemReport->getResourceReports() as $r){
		$row = $r->getCsvRow();
		fwrite($myfile, "$row\r\n");
	}
    if($row <= 10 || $row % 10 == 0 ){
	   echo_message_to_screen(INFO, "Processed $row rows");
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
	if (! empty( $resource_data->id )) {
		$resource = $resource_data->id;
		echo_message_to_screen(DEBUG, "Resource ID: $resource \t");
		return $resource;
	} 
	return "";
}

function get_web_address_array($resource_data) {
	if (! empty( $resource_data->attributes->web_addresses )) {
		$web_addresses = $resource_data->attributes->web_addresses;
		echo_message_to_screen(DEBUG, print_r($web_addresses, TRUE));
		return $web_addresses;
	}
	return [];
}

function get_online_resource($resource_data) {
	if (! empty( $resource_data->attributes->online_resource->link )) {
		$online_resource = $resource_data->attributes->online_resource->link;
		echo_message_to_screen(DEBUG, "Online resource currently set: $online_resource \t");
		return $online_resource;
	}
	return "";
}

function add_url($itemID, $newURL, $shortCode, $TalisGUID, $token, ReportRow $itemReport) {
	global $myfile;
	global $shouldWritetoLive;

	echo_message_to_screen(INFO, "add\t");

	// get the resource (but we only add the URL to the primary resource)
	$getPartOf = false;
	$item_data = get_item($shortCode, $itemID, $TalisGUID, $token, $getPartOf);
	if($item_data) {
		$resources = get_resources_from_item($item_data);
		$any_resource_updated = false;

		$report = new ReportRow();
		$itemReport->addResourceReport($report);
		$report->transactionType = "Add";

		// get the primary resource.
		$resource_data = $resources[0];

		// get the existing web addresses
		$resource_id = get_resource_id($resource_data);
		$web_address_array = get_web_address_array($resource_data);
		// for reporting purposes only ->
		$online_resource = get_online_resource($resource_data);

		$report->resourceID = $resource_id;
		$report->currentWebAddressArray = $web_address_array;
		$report->currentOnlineResource = $online_resource;
		$report->newURL = $newURL;

		// if there is no existing web addresses it is OK to proceed to add some.
		// but we need to make sure that the array is present to add to.
		if (!is_array($web_address_array)) {
			$web_address_array = [];
		}
		// add a new web addresses to the existing ones
		array_push($web_address_array, $newURL);
		$report->newWebAddressArray = $web_address_array;

		// build the PATCH body
		$body = build_patch_body($resource_id, $web_address_array, $newURL);
		echo_message_to_screen(DEBUG, "add_url patch request body: $body");
		// if not a dry run - update
		if ($shouldWritetoLive === "true") {
			$result = post_url($shortCode, $resource_id, $body, $TalisGUID, $token);
			if ($result) {
				$report->actionMessage = "URL added Successfully";
				$any_resource_updated = true;
				$report->updated = true;
			} else {
				$report->actionMessage = "URL not added";
				$report->failure = true;
			}
		} else {
			$report->updated = true;
			$itemReport->actionMessage = "Dry Run";
			$report->actionMessage = "Would be updated";
		}
		increment_counter('URLs added: ');

		if ($any_resource_updated === true){
			$itemReport->actionMessage = "Item updated";
		}	
	}
}

function delete_url($itemID, $oldURL, $shortCode, $TalisGUID, $token, ReportRow $itemReport) {
	global $myfile;
	global $shouldWritetoLive;

	echo_message_to_screen(INFO, "delete \t");

	$item_data = get_item($shortCode, $itemID, $TalisGUID, $token);
	if($item_data) {
		$resources = get_resources_from_item($item_data);

		$any_resource_updated = false;
		$web_address_found = false;
		$online_resource_found = false;

		foreach ($resources as $r){
			$report = new ReportRow();
			$itemReport->addResourceReport($report);
			$report->transactionType = "delete";
			
			// get the existing web addresses
			$resource_id = get_resource_id($r);
			$web_address_array = get_web_address_array($r);
			$online_resource = get_online_resource($r);

			$report->resourceID = $resource_id;
			$report->currentWebAddressArray = $web_address_array;
			$report->currentOnlineResource = $online_resource;
			$report->oldURL = $oldURL;

			// if we do have web addresses that we can delete...
			if ($web_address_array){
				$web_address_found = true;
				$new_web_address_array = check_web_addresses($oldURL, "", $web_address_array, "delete");
				$report->newWebAddressArray = $new_web_address_array;

				// Nothing further to do if there are no changes to make
				if($web_address_array === $new_web_address_array){
					echo_message_to_screen(DEBUG, "Nothing to update with this resource</br>");
					$report->actionMessage = "Nothing to do";
					$report->updated = true;
					continue;
				}

				// build the PATCH body
				$body = build_patch_body($resource_id, $new_web_address_array, "");
				
				// check online resource
				$body = check_online_resource($oldURL, $online_resource, $body);
				echo_message_to_screen(DEBUG, "delete_url patch request body: $body");
				// if not a dry run - update
				if ($shouldWritetoLive === "true") {
					$result = post_url($shortCode, $resource_id, $body, $TalisGUID, $token);
					if ($result) {
						$any_resource_updated = true;
						$report->updated = true;
						$report->actionMessage = "URL Deleted Successfully";
					} else {
						$report->actionMessage = "URL Not Deleted";
						$report->failure = true;
					}
				} else {
					$itemReport->actionMessage = "Dry Run";
					$report->updated = true;
					$report->actionMessage = "Would remove web address";
				}
				increment_counter('URLs deleted: ');
			} else {
				$report->actionMessage = "Nothing to do";
			}
		}

		// We only want to flag these warnings if they do not happen for any resource in this item
		if ($web_address_found === false){
			echo_message_to_screen(INFO, "Web Address not found in any resource\t");
			$itemReport->currentWebAddressArray = "Web Address not found in any resource";
			increment_counter('Delete operations with no web addresses on item: ');
		}
		if ($online_resource_found === false){
			echo_message_to_screen(INFO, "Online Resource is not set\t");
			$itemReport->currentOnlineResource = "Online Resource is not set";
		}

		if ($any_resource_updated === true){
			$itemReport->actionMessage = "Item updated";
		}	

	}
}

function replace_url($itemID, $oldURL, $newURL, $shortCode, $TalisGUID, $token, ReportRow $itemReport){
	global $shouldWritetoLive;
	echo_message_to_screen(INFO, "replace\t");

	$item_data = get_item($shortCode, $itemID, $TalisGUID, $token);
	if($item_data) {
		$resources = get_resources_from_item($item_data);
		$any_resource_updated = false;
		$web_address_found = false;
		$online_resource_found = false;

		foreach ($resources as $r) {
			$report = new ReportRow();
			$itemReport->addResourceReport($report);
			$report->transactionType = "replace";

			// get the existing web addresses
			$resource_id = get_resource_id($r);
			$web_address_array = get_web_address_array($r);
			$online_resource = get_online_resource($r);
			$report->resourceID = $resource_id;
			$report->currentWebAddressArray = $web_address_array;
			$report->currentOnlineResource = $online_resource;
			$report->oldURL = $oldURL;
			$report->newURL = $newURL;

			if ($online_resource !== false){
				$online_resource_found = true;
			}

			if ($web_address_array) {
				$web_address_found = true;
				
				// add a new web addresses to the existing ones
				$new_web_address_array = check_web_addresses($oldURL, $newURL, $web_address_array, "replace");
				$report->newWebAddressArray = $new_web_address_array;

				// Nothing further to do if there are no changes to make
				if($web_address_array === $new_web_address_array){
					echo_message_to_screen(DEBUG, "Nothing to update with this resource</br>");
					$report->actionMessage = "Nothing to do";
					continue;
				}

				$new_online_resource = $newURL;

				// build the PATCH body
				$body = build_patch_body($resource_id, $new_web_address_array, $new_online_resource);
				echo_message_to_screen(DEBUG, "replace_url patch request body: $body");
				// if not a dry run - update
				if ($shouldWritetoLive === "true") {
					$result = post_url($shortCode, $resource_id, $body, $TalisGUID, $token);
					if ($result) {
						$report->actionMessage = "URL replaced successfully";
						$report->updated = true;
						$any_resource_updated = true;
					} else {
						$report->actionMessage = "URL not replaced";
						$report->failure = true;
						$any_resource_updated = false;
					}
				} else {
					$itemReport->actionMessage = "Dry Run";
					$report->actionMessage = "Would update";
					$report->updated = true;
				}
				increment_counter('URLs replaced: ');
			} else {
				$report->actionMessage = "Nothing to do";
				$report->updated = true;
			}
		}
		// We only want to flag these if they do not happen for any resource in this item
		if ($web_address_found === false){
			echo_message_to_screen(INFO, "Web Address not found in any resource\t");
			$itemReport->currentWebAddressArray = "Web Address not found in any resource";
		}

		if ($online_resource_found === false){
			echo_message_to_screen(INFO, "Online Resource is not set\t");
			$itemReport->currentOnlineResource = "Online Resource is not set";
		}

		if ($any_resource_updated === true){
			$itemReport->actionMessage = "Item updated";
		} else {
			$itemReport->actionMessage = "Item not updated";
		}
	}
	return $itemReport;
}

/**
 * Extract all resources from the item data returned from the API
 * There may be 0 or more resources found
 *
 * @param array $itemData
 * @return array
 */
function get_resources_from_item(stdClass $itemData){
	$resources = [];
	if (!isset($itemData->included)){
		return $resources;
	}
	foreach ($itemData->included as $v){
		if(!isset($v->type)){
			continue;
		}
		if($v->type == "resources"){
			$resources[] = $v;
		}
	}
	return $resources;
}

function get_item($shortCode, $itemID, $TalisGUID, $token, $includePartOf=true) {
	$partOf = "";
	if ($includePartOf === true) {
		$partOf = ",resource.part_of";
	}
	$item_lookup = "https://rl.talis.com/3/" . $shortCode . "/draft_items/" . $itemID . "?include=resource" . $partOf;

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
	$oldURL_found = array_search($oldURL, $web_address_array);
	echo_message_to_screen(DEBUG, "Array Search Result = " . var_export($oldURL_found, true));

	// array_search returns a false value if it did not find a match
	if ( $oldURL_found !== false) {
		if($mode == "delete") {
			unset($web_address_array[$oldURL_found]);
		} 
		if($mode == "replace") {
			$web_address_array[$oldURL_found] = $newURL;
		}
		echo_message_to_screen(INFO, "Found Matching URL at index: [$oldURL_found] \t");
	} else {
		echo_message_to_screen(WARNING, "WARNING: no matching URL found in web address array. Moving onto next row... \t");
	}
	return $web_address_array;
}

function check_online_resource($oldURL, $online_resource, $body) {
	$body_decoded = json_decode($body, true);
	$body_decoded['data']['attributes']['online_resource'] = null;
	return json_encode($body_decoded);	
}

function build_patch_body($resourceID, $web_addresses, $new_online_resource_url) {

	$template = '{
				"data": {
					"type": "resources",
					"id": "' . $resourceID . '",
					"attributes": {
						"web_addresses": [],
						"online_resource": {
							"source": "uri",
							"link": "' . $new_online_resource_url . '"
						}
					} 
				}
			}';
	$template_obj = json_decode($template, true);
	$template_obj['data']['attributes']['web_addresses'] = $web_addresses;

	return json_encode($template_obj);
}

function post_url($shortCode, $resourceID, $body, $TalisGUID, $token) {
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
		return false;
	} else {
		echo_message_to_screen(DEBUG, "Resource URL Updated Successfully</br>");
		return true;
	}
}

counter_summary();

fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");

fclose($file_handle);
fclose($myfile);

print("</br></br><a href=$logfile>Click Here to download your output.csv file.</a>");
?>
