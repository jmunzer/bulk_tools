<?php

print("</br><a href='del_stu_note.html'>Back to Delete Student Note tool</a>");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<p>Starting</p>";

//*********GET DATE**********************

$date = date('Y-m-d\TH:i:s');
// $date1 = "2015-12-21T15:44:36";


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

$shouldPublishLists = filter_var($_REQUEST['PUBLISH_LISTS'], FILTER_VALIDATE_BOOLEAN) || FALSE;

echo "Should publish lists?: " . var_export($shouldPublishLists, true);
echo "</br>";
echo "</br>";

$publishListArray = array();

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

//**********CREATE LOG FILE TO WRITE OUTPUT*

$myfile = fopen("../../report_files/delstunote_output.log", "a") or die("Unable to open delstunote_output.log");
fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");
fwrite($myfile, "List name" . "\t" . "List ID" . "\t" . "Section GUID" . "\t" . "Section deleted" . "\t" . "List Published" . "\r\n");



//************GET_TOKEN***************
$token = getToken($clientID, $secret);

//***********READ**DATA******************

$file_handle = fopen($uploadfile, "rb");

while (!feof($file_handle) )  {

	$line_of_text = fgets($file_handle);
	$parts = explode(" ", $line_of_text);
	$itemID = trim($parts[0]);
	
	//************GRAB LIST DETAILS*************

	$ListDataArray = getList($TalisGUID, $token, $shortCode, $itemID);
		$listID = $ListDataArray[0];
		$etag = json_encode($ListDataArray[1]);

	if ($shouldWritetoLive == "true") {

	// writing list ID to array for bulk publish POST
	$forListArray = ['type' => 'draft_lists', 'id' => $listID]; //check this $listID value
	array_push($publishListArray, $forListArray);

	//**************DELETE STUDENT NOTE***************
	$deleteResponse = delete_student_note($shortCode, $itemID, $etag, $listID, $TalisGUID, $token);
	if ($deleteResponse !== 200){
		echo "<p>ERROR: There was an error deleting the note:</p>";
		continue;
	} else {
		echo "<p>Deleted student note</p>";
	}

	//print_r($publishListArray);
	//json_encode list array to prepare for API submisson
	$publishListArray_encoded = json_encode($publishListArray);

	//**************PUBLISH**LIST***************
	if ($shouldPublishLists === TRUE) {
		$publishResponse = publishlists($shortCode, $publishListArray_encoded, $TalisGUID, $token);
		if ($publishResponse !== 202){
			echo "<p>ERROR: There was an error publishing the lists:</p>";
			//fwrite($myfile, "Publish failed" . "\t");
			exit;
		} else {
			echo "Published changes to $listID</br>";
			//fwrite($myfile, "Published successfully" . "\t");
		}
	}
	
	}

	fwrite($myfile, "\n");
	echo "End of Record.";
	echo "---------------------------------------------------</br></br>";
}

fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");

fclose($file_handle);
fclose($myfile);

?>
