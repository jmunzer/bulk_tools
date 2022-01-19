<?php

print("</br><a href='change_owner.html'>Back to Change Owner tool</a>");

ini_set('max_execution_time', '0');
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

$myfile = fopen("../../report_files/change_owner_output.log", "a") or die("Unable to open change_owner_output.log");
fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");
fwrite($myfile, "Owner GUID" . "\t" . "List ID" . "\t" . "Outcome" . "\r\n");

$tokenURL = 'https://users.talis.com/oauth/tokens';
$content = "grant_type=client_credentials";

$token=token_fetch($clientID, $secret); 

$file_handle = fopen($uploadfile, "r");
if ($file_handle == FALSE) {
		echo_message_to_screen(ERROR, "Could not open text file - Process Stopped.");
		exit;
    }

while (($line = fgetcsv($file_handle, 1000, ",")) !== FALSE) {

	$ownerID = trim($line[0]);
	$listID = trim($line[1]);

	fwrite($myfile, $ownerID . "\t");
	fwrite($myfile, $listID . "\t");
		
	$etag = etag_fetch($shortCode, $listID, $TalisGUID, $token);

	if (empty($etag)) {
		continue;
	} else {

		$input = patchBody($etag, $listID, $ownerID);
		ownerPatch($shortCode, $TalisGUID, $token, $input, $listID, $ownerID, $myfile);

	}
}

fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");
fclose($file_handle);
fclose($myfile);
?>