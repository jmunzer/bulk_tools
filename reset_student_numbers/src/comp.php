<?php
//error_reporting(E_ALL);
// Resets supplied lists' student numbers to the values of any nodes it is attached to
// By finding student numbers on nodes a list is attached to and supplying this value in the list node relationship override_student_numbers attributes
// Default behaviour is to not overwrite the list numbers where this would result in a value of "0"
// Contains a switch to override the default behaviour, meaning the numbers on lists are overwritten regardless of the value on the node

print("</br><a href='reset_numbers.html'>Back to reset student numbers tool</a>");

ini_set('max_execution_time', '600');

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
		$shouldWritetoLive = "true";
	}
	else
	{
		$shouldWritetoLive = "false";
	}

// Default behaviour override switch
if(isset($_REQUEST['OVERRIDE']) &&
	$_REQUEST['OVERRIDE'] === "overrideDefault") {
		$overrideDefault = "true";
	}
	else
	{
		$overrideDefault = "false";
}

echo "Writing to live tenancy?: $shouldWritetoLive";
echo "</br>";
echo "Override default behaviour?: $overrideDefault";
echo "</br>";

// Constants
$tokenURL = 'https://users.talis.com/oauth/tokens';
$content = "grant_type=client_credentials";
$date = date('Y-m-d\TH:i:s'); // "2015-12-21T15:44:36"

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

// Create report file
$logfile = "../../report_files/reset_numbers_output.log";
$myfile = fopen("../../report_files/reset_numbers_output.log", "a") or die("Unable to open reset_numbers_output.log");
fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");
fwrite($myfile, "List ID\tOld Node Relationships\tNew Node Relationships\tUpdate Status\r\n");

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

// Patch mode constants
const DETACH = 0;
const ATTACH = 1;

// Detach template
$detach_template = '{"data":[]}';
$detachBody = json_decode($detach_template, true);

// Read file
$file_handle = fopen($uploadfile, "r");
if ($file_handle == FALSE) {
	echo_message_to_screen(ERROR, "Could not open txt file - Process Stopped.");
    exit;
}

$row = 0;
while (!feof($file_handle)) {
	$row++;
	// if this is the first row, detect and remove BOMs from UTF8 files.
	if ($row === 1) {
		trim($file_handle[0], "\\xef\\xbb\\xbf");
	}

	// Check for blank lines and output useful message (instead of API error response)
	$listID = trim(fgets($file_handle));
	if (!empty($listID)) {
		echo_message_to_screen(INFO, "List ID: $listID \t");
		fwrite($myfile, $listID . "\t");
	} else {
		echo_message_to_screen(INFO, "No List ID, skipping line...\t");
		fwrite($myfile, "No list ID\t\t\tSkipping...\r\n");
		continue;
	}

	// Get list node relationship data
	$list_node_relationships = get_list_node_relationships($shortCode, $listID, $TalisGUID, $token);
	if (!$list_node_relationships) {
		fwrite($myfile, "Unable to retrieve list's node relationship data\t\tSkipping...\r\n");
		continue;
	}

	// Does the list have an existing list node relationship?
	if (empty($list_node_relationships->data[0]->type)) {
		echo_message_to_screen(INFO, "List not attached to any nodes. Moving onto next list...");
		fwrite($myfile, "\t\tList not attached to any nodes - nothing updated\r\n");
	} else {		
		// Log list's old node relationship and any override student numbers
		$old_node_relationships = format_old_node_data($list_node_relationships);
		fwrite($myfile, $old_node_relationships . "\t");
		
		// Populate an array of node ids and their override_student_numbers from relationships...
		$list_nodes = [];
		for ($i = 0, $size = count($list_node_relationships->data); $i < $size; ++$i) {
			$node_id = $list_node_relationships->data[$i]->id;
			if (isset($list_node_relationships->data[$i]->meta->override_student_numbers)) {
				$override_student_numbers = $list_node_relationships->data[$i]->meta->override_student_numbers;
			} else {
				$override_student_numbers = 0;
			}
			$list_nodes[$node_id] = $override_student_numbers;
		}
		// ... and get student numbers from the list's attached nodes
		// By default, using the old override_student_numbers if the node's student number value is 0
		$node_student_numbers = [];
		foreach ($list_nodes as $node_id => $old_student_numbers) {
			$node_student_numbers[$node_id] = get_new_student_numbers($shortCode, $TalisGUID, $token, $node_id, $old_student_numbers, $overrideDefault);
		}

		// Log list's new node relationship data
		$new_node_relationships = format_new_node_data($node_student_numbers);
		fwrite($myfile, $new_node_relationships . "\t");

		// Create attach template from list node relationships and the node data's student numbers
		$attachBody = patch_attach_template($list_node_relationships, $node_student_numbers);
		
		// If not dry run - update
		if ($shouldWritetoLive === "true") {
			// Detach list from its nodes
			$detachResult = post_url($shortCode, $listID, $detachBody, $TalisGUID, $token, DETACH);
			if ($detachResult) {
				// If detach successful, log result...
				fwrite($myfile, "List detached");
				// and reattach list to its nodes
				$attachResult = post_url($shortCode, $listID, $attachBody, $TalisGUID, $token, ATTACH);
				if ($attachResult) {
					// If reattach successful, log result
					if ($overrideDefault === "true") {
						fwrite($myfile, " and reattached. Student numbers reset using override behaviour.\r\n");
					} else {
						fwrite($myfile, " and reattached. Student numbers reset using default behaviour.\r\n");
					}
				} else {
					// Log reattach not successful
					fwrite($myfile, " but not reattached. Student numbers NOT reset - check list's hierarchy relationships.\r\n");
				}
			} else {
				// Log detach not succesful
				fwrite($myfile, "List not detached from node. No further action taken.\r\n");
			}
		} else {
			fwrite($myfile, "Dry Run - nothing updated.\r\n");
		}
	}
}

// List node relationships
function get_list_node_relationships($shortCode, $listID, $TalisGUID, $token) {
	$list_lookup = "https://rl.talis.com/3/" . $shortCode . "/lists/" . $listID . "/relationships/nodes";

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

	if ($info1 !== 200) {
		echo_message_to_screen(WARNING, "Unable to retrieve the list's node relationship data: <pre>" . var_export($output, true) . "</pre>");
		return false;
	} else {
		echo_message_to_screen(DEBUG, "Successfully retrieved the list's node relationship data: <pre>" . var_export($output, true) . "</pre>");
		return $output_json;
	}
}

// Format old node data for logs
function format_old_node_data($list_node_relationships) {
	$log_data = "";
	for ($i = 0, $size = count($list_node_relationships->data); $i < $size; ++$i) {
		$id = $list_node_relationships->data[$i]->id;
		if (isset($list_node_relationships->data[$i]->meta->override_student_numbers)) {
			$old_student_numbers = $list_node_relationships->data[$i]->meta->override_student_numbers;
		} else {
			$old_student_numbers = false;
		}		
		
		$log_data .= "$id";

		if ($old_student_numbers) {
			$log_data .= " ($old_student_numbers)";
		} else {
			$log_data .= " (null)";
		}

		if ($i + 1 !== $size) {
			$log_data .= "; ";
		}
	}
	return $log_data;
}

// Format new node data for logs
function format_new_node_data($node_student_numbers) {
	$size = count($node_student_numbers);
	$i = 0;
	$log_data = "";
	foreach ($node_student_numbers as $id => $student_numbers) {
		$log_data .= $id . " (" . $student_numbers . ")";
		if (++$i !== $size) {
			$log_data .= "; ";
		}
	}
	return $log_data;
}

// Get new student numbers from attached nodes
// getNodes call limited to 10 results - a low but otherwise arbitary value (for now).
// Limit can be lowered/raised as needed if results of search_term too broad/narrow.
function get_new_student_numbers($shortCode, $TalisGUID, $token, $search_term, $old_student_numbers, $overrideDefault) {
	$node_lookup = "https://rl.talis.com/3/" . $shortCode . "/nodes?page[limit]=10&page[offset]=0&filter[search_term]=" . $search_term;
	// Set value of $old_student_numbers to "null" if empty
	if (empty($old_student_numbers)) {
		$old_student_numbers = "null";
	}

	$ch1 = curl_init();
		
		curl_setopt($ch1, CURLOPT_URL, $node_lookup);
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

	if ($info1 !== 200) {
		echo_message_to_screen(WARNING, "Unable to retrieve any node data: <pre>" . var_export($output, true) . "</pre>");
		return false;
	} else {
		echo_message_to_screen(DEBUG, "Successfully retrieved node data for search term: " . $search_term . "<pre>" . var_export($output, true) . "</pre>");

		// Use extract_student_numbers() with $output_json to get student numbers for the provided $node_id
		// If no results are returned by getNodes, then the $old_student_numbers are returned
		if ($output_json->meta->total > 0) {
			$node_student_numbers = extract_student_numbers($output_json, $search_term);
			// Check for default behaviour override
			if ($overrideDefault === "true") {
				// Override behaviour
				// Returns student number value found on node, or "null" if no value was found on node
				if (empty($node_student_numbers)) {
					return "null";
				} else {
					return $node_student_numbers;
				}
			} else {
				// Default behaviour
				// If extract_student_numbers is empty (i.e. the matching node was not found or the matching node had no student numbers), return $old_student_numbers
				if (empty($node_student_numbers)) {
					return $old_student_numbers;
				} else {
					return $node_student_numbers;
				}
			}
		} else {
			return $old_student_numbers;
		}
	}
}

// Returns the value of the student_number attribute against the first match it makes
// If no match or if the matched node has no student_number attribute then null is returned
function extract_student_numbers($node_search_result, $search_id) {
	for ($i = 0, $size = count($node_search_result->data); $i < $size; ++$i) {
		$result_id = $node_search_result->data[$i]->id;
		if (isset($node_search_result->data[$i]->attributes->student_numbers)) {
			$node_student_numbers = $node_search_result->data[$i]->attributes->student_numbers;
		} else {
			$node_student_numbers = null;
		}

		if ($search_id == $result_id) {
			return $node_student_numbers;
		}
	}
	return null;
}

// Bring list node relationship data together with node student number data
function patch_attach_template($list_node_relationships, $node_student_numbers){
	$template_data = "";
	for ($i = 0, $size = count($list_node_relationships->data); $i < $size; ++$i) {
		$type = $list_node_relationships->data[$i]->type;
		$id = $list_node_relationships->data[$i]->id;
		$override_student_numbers = $node_student_numbers[$id];

		$node = '{"type":"'. $type .'","id":"'. $id .'","meta":{"override_student_numbers":'. $override_student_numbers .'}}';
		if ($i === 0) {
			$template_data = $node;
		} else {
			$template_data .= "," . $node;
		}
	}

	$template = '{"data":[' . $template_data . ']}';
	echo_message_to_screen(DEBUG, "Patch body: $template");
	$templateArr = json_decode($template, true);
	return $templateArr;
}

// Patch listNodeRelationship
// Has two modes (DETACH and REATTACH)
function post_url($shortCode, $listID, $body, $TalisGUID, $token, $mode) {
	$patch_url = "https://rl.talis.com/3/" . $shortCode . "/lists/" . $listID . "/relationships/nodes";
	$ch2 = curl_init();

	curl_setopt($ch2, CURLOPT_URL, $patch_url);
	curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, 'PATCH');
	curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
		
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	));

	curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($body));

	$output2 = curl_exec($ch2);
	$info2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
	curl_close($ch2);
	
	if ($mode == 0) { // Detach mode
		if ($info2 !== 200){
			echo_message_to_screen(WARNING, "List not detached from its nodes: <pre>" . var_export($output2, true) . "</pre>");
			return false;
		} else {
			echo_message_to_screen(DEBUG, "List successfully detached from its node(s)");
			return true;
		}
	} elseif ($mode == 1) { // Attach mode
		if ($info2 !== 200){
			echo_message_to_screen(WARNING, "List was not successfully reattached to its nodes: <pre>" . var_export($output2, true) . "</pre>");
			return false;
		} else {
			echo_message_to_screen(DEBUG, "List successfully reattached to its node(s) - student numbers have been reset</br>");
			return true;
		}
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

fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");

fclose($file_handle);
fclose($myfile);

print("</br><a href=$logfile>Click Here to download the log file.</a>");
?>
