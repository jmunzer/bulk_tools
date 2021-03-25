<?php

// Resets supplied lists' student numbers to the values of any nodes it is attached to by removing any override_student_number data in the list node relationship

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

echo "Writing to live tenancy?: $shouldWritetoLive";
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
fwrite($myfile, "List ID" . "\t" . "Current Node Relationships" . "\t" . "Update Status" . "\r\n");

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
		trim($line[0], "\\xef\\xbb\\xbf");
	}

	$listID = trim(fgets($file_handle));
	if (!empty($listID)) {
		echo_message_to_screen(INFO, "List ID: $listID \t");
		fwrite($myfile, $listID . "\t");
	}

	// Get list node relationship data
	$list_node_relationships = get_list_node_relationships($shortCode, $listID, $TalisGUID, $token);
	if (!$list_node_relationships) {
		fwrite($myfile, "\t" . "Unable to retrieve list's node relationship data" . "\t" . "Skipping..." . "\r\n");
		continue;
	}

	// Does the list have an existing list node relationship?
	if (empty($list_node_relationships->data[0]->type)) {
		echo_message_to_screen(INFO, "List not attached to any nodes. Moving onto next list...");
		fwrite($myfile, "\t" . "List not attached to any nodes - nothing updated" . "\r\n");
	} else {
		// Log list's current node relationship and any override student numbers
		$current_node_relationships = friendly_node_data($list_node_relationships);
		fwrite($myfile, $current_node_relationships . "\t");	
		
		// Create attach template
		$attachBody = patch_attach_template($list_node_relationships);
		
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
					fwrite($myfile, " and reattached. Student numbers reset to parent node(s) value" . "\r\n");
				} else {
					// Log reattach not successful
					fwrite($myfile, " but not reattached. Student numbers NOT reset - check list's hierarchy relationships" . "\r\n");
				}
			} else {
				// Log detach not succesful
				fwrite($myfile, "List not detached from node. No further action taken." . "\r\n");
			}
		} else {
			fwrite($myfile, "Dry Run - nothing updated" . "\r\n");
		}
	}
}

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

	if ($info1 !== 200){
		echo_message_to_screen(WARNING, "Unable to retrieve the list's node relationship data: <pre>" . var_export($output, true) . "</pre>");
		return false;
	} else {
		echo_message_to_screen(DEBUG, "Successfully retrieved the list's node relationship data: <pre>" . var_export($output, true) . "</pre>");
		return $output_json;
	}
}

// Format node data for logs
function friendly_node_data($list_node_relationships) {
	for ($i = 0, $size = count($list_node_relationships->data); $i < $size; ++$i) {
		$type = $list_node_relationships->data[$i]->type;
		$id = $list_node_relationships->data[$i]->id;
		$overide_student_numbers = $list_node_relationships->data[$i]->meta->override_student_numbers;
		
		$node_data = "$type: $id";

		if ($overide_student_numbers) {
			$node_data .= " (Override student numbers: $overide_student_numbers)";
		}

		if ($i === 0) {
			$log_data = $node_data;
		} else {
			$log_data .= "; $node_data";
		}
	}
	return $log_data;
}

function patch_attach_template($list_node_relationships){
	for ($i = 0, $size = count($list_node_relationships->data); $i < $size; ++$i) {
		$type = $list_node_relationships->data[$i]->type;
		$id = $list_node_relationships->data[$i]->id;
		$node = '{"type":"'. $type .'","id":"'. $id .'"}';
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
