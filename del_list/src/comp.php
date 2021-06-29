<?php

// Deletes supplied lists

print("</br><a href='del.html'>Back to delete list tool</a>");

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
$logfile = "../../report_files/del_list_output.log";
$myfile = fopen("../../report_files/del_list_output.log", "a") or die("Unable to open del_list_output.log");

fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");
fwrite($myfile, "List ID" . "\t" . "Status" . "\r\n");

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
	} else {
		echo_message_to_screen(INFO, "No List ID, skipping line... \t");
		fwrite($myfile, "No list ID\tSkipping... \r\n");
		continue;
	}   

	// Get draft list data
	$list_data = get_draft_list($shortCode, $listID, $TalisGUID, $token);
	if (!$list_data) {
		fwrite($myfile, "List data not retrieved. Skipping... \r\n");
		continue;
	}

	// Check for reviews
	$review = $list_data->data->links->review;
	if (!empty($review)) {
		echo_message_to_screen(WARNING, "List ID: $listID has an open <a href=\"$review\" target=\"_blank\">review</a>. Review must be closed to delete list. \r\n");
		fwrite($myfile, "List has an open review: $review. Review must be closed to delete list. \r\n");
		continue;
	}

	// If not dry run - update
	if ($shouldWritetoLive === "true") {
		$delete_data = delete_list($shortCode, $listID, $TalisGUID, $token);
		if ($delete_data) {
			fwrite($myfile, "Error: $delete_data \r\n");
		} else {
			fwrite($myfile, "List deleted. \r\n");
		}
	} else {
		fwrite($myfile, "Dry Run - nothing deleted \r\n");
	}

}

// Get draft list data
function get_draft_list($shortCode, $listID, $TalisGUID, $token) {
	$list_lookup = "https://rl.talis.com/3/" . $shortCode . "/draft_lists/" . $listID;

	$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $list_lookup);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			
			"X-Effective-User: $TalisGUID",
			"Authorization: Bearer $token",
			'Cache-Control: no-cache'

		));
		$output = curl_exec($ch);
		$info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$output_json = json_decode($output);
	
	curl_close($ch);

	if ($info !== 200){
		echo_message_to_screen(WARNING, "WARNING: Unable to retrieve list data: <pre>" . var_export($output, true) . "</pre>");
		return false;
	} else {
		echo_message_to_screen(DEBUG, "Successfully retrieved list data: <pre>" . var_export($output, true) . "</pre>");
		return $output_json;
	}

}

// Delete list
function delete_list($shortCode, $listID, $TalisGUID, $token) {
	$delete_list = "https://rl.talis.com/3/" . $shortCode . "/lists/" . $listID;

	$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $delete_list);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			
			"X-Effective-User: $TalisGUID",
			"Authorization: Bearer $token",
			'Cache-Control: no-cache'

		));
		$output = curl_exec($ch);
		$info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$output_json = json_decode($output);
	
	curl_close($ch);

	if ($info == 204){
		echo_message_to_screen(INFO, "Successfully deleted list: $listID \r\n");
		return false;
	} else {
		echo_message_to_screen(WARNING, "WARNING: Unable to delete list: <pre>" . var_export($output, true) . "</pre>");
		return $output_json;
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
