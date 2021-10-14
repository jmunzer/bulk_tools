<?php

print("</br><a href='imp.html'>Back to New Acquisitions tool</a>");

ini_set('max_execution_time', '0');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<p>Starting</p>";

//classes
//functions

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

/**
 * Get the user config file. This script will fail disgracefully if it has not been created and nothing will happen.
 */
require('../../user.config.php');
require('functions.php');

echo "Tenancy Shortcode set: " . $shortCode;
echo "</br>";

echo "Client ID set: " . $clientID;
echo "</br>";

echo "User GUID to use: " . $TalisGUID;
echo "</br>";


//**********CREATE LOG FILE TO WRITE OUTPUT*

$myfile = fopen("../../report_files/bulkimp_output.log", "a") or die("Unable to open bulkimp_output.log");
fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");
fwrite($myfile, "Item ID" . "\t" . "List ID" . "\t" . "Outcome" . "\r\n");

$tokenURL = 'https://users.talis.com/oauth/tokens';
$content = "grant_type=client_credentials";

$token=token_fetch($clientID, $secret); 


	
	$file_handle = fopen($uploadfile, "r");
    if ($file_handle == FALSE) {
		echo_message_to_screen(ERROR, "Could not open tsv file - Process Stopped.");
		exit;
    }

	while (($line = fgetcsv($file_handle, 1000, "\t")) !== FALSE) {
		
		$input_item = trim($line[0]);
				
		$etag = etag_fetch($shortCode, $listID, $TalisGUID, $token);
		$input_imp = impBody($input_item, $etag, $listID, $resource_id, $importanceID) ;
		impPost($shortCode, $TalisGUID, $token, $input_imp, $input_item, $title);
		
		
		echo "</br> - $title: Successfully created resource and added to list $listID";
		fwrite($myfile, "https://rl.talis.com/3/$shortCode/items/$input_item.html?lang=en-GB&login=1" . "\t" . $listID . "\t" . "Successfully created resource " . "\r\n");
	}

	//https://rl.talis.com/3/yorksj/items/063525ae-fa67-4229-8c2b-08c08e354eb4?include=list,resource,importance
	fclose($file_handle);


// Here we publish the list.
$etag = etag_fetch($shortCode, $listID, $TalisGUID, $token);
publish_single_list($shortCode, $listID, $TalisGUID, $token, $etag);

fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");
fclose($myfile);
?>