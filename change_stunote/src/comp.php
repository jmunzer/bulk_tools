<?php

print("</br><a href='change_stunote.html'>Back to Change Student Note tool</a>");

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
**/

require('../../user.config.php');
require('functions.php');

echo "Tenancy Shortcode set: " . $shortCode;
echo "</br>";

echo "Client ID set: " . $clientID;
echo "</br>";

echo "User GUID to use: " . $TalisGUID;
echo "</br>";


//**********CREATE LOG FILE TO WRITE OUTPUT*

$myfile = fopen("../../report_files/change_stunote_output.log", "a") or die("Unable to open change_stunote_output.log");
fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");
//fwrite($myfile, "List name" . "\t" . "List ID" . "\t" . "Section Status" . "\t" . "Item Status" . "\t" . "Item Status" . "\t" . "Item Status" . "\t" . "List Published" . "\r\n");

//**********Authenticate the session*
$token=token_fetch($clientID, $secret); 
echo "</br></br>";

$file_handle = fopen($uploadfile, "r");

if ($file_handle == FALSE) {
	echo_message_to_screen(ERROR, "Could not open csv file - Process Stopped.");
	exit;
}

while (($line = fgetcsv($file_handle, 1000, "\t")) !== FALSE) {

	$itemID = trim($line[0]);
	$stuNote = trim($line[1]);
	
	echo "Item ID: ". $itemID."</br>";
	echo "Student Note: ".$stuNote."</br>";
	echo "------------</br>";

	$listDetails = etag_fetch_fromItem($shortCode, $itemID, $TalisGUID, $token);
		$listID = $listDetails[0];
		$etag = $listDetails[1];

	$input = itemBody_StuNote($itemID, $stuNote, $listID, $etag);
	itemPatch($shortCode, $TalisGUID, $token, $input, $itemID);

}

//fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");


fclose($file_handle);

fclose($myfile);
?>
