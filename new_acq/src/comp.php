<?php

print("</br><a href='acq.html'>Back to New Acquisitions tool</a>");

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

$listID = $_REQUEST['ListID'];
//$listID = "";

echo "List ID to use: " . $listID;
echo "</br>";


$sourceselect = filter_var($_REQUEST['SOURCE_TYPE'], FILTER_VALIDATE_BOOLEAN) || FALSE;

echo "Use TSV: " . var_export($sourceselect, true);
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

$myfile = fopen("../../report_files/newacq_output.log", "a") or die("Unable to open newacq_output.log");
fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");
fwrite($myfile, "Item ID" . "\t" . "List ID" . "\t" . "Outcome" . "\r\n");

$tokenURL = 'https://users.talis.com/oauth/tokens';
$content = "grant_type=client_credentials";

$token=token_fetch($clientID, $secret); 

//***************GRAB LIST ITEMS (TO DELETE) ****** */
$ListItemsUrl = 'https://rl.talis.com/3/' . $shortCode . '/lists/' . $listID . '/items?draft=1';
$chLIU = curl_init();

curl_setopt($chLIU, CURLOPT_URL, $ListItemsUrl);
curl_setopt($chLIU, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($chLIU, CURLOPT_HTTPHEADER, array(
	
	"X-Effective-User: $TalisGUID",
	"Authorization: Bearer $token",
	'Cache-Control: no-cache'


));

$outputLIU = curl_exec($chLIU);
$infoLIU = curl_getinfo($chLIU, CURLINFO_HTTP_CODE);
$outputjsonLIU = json_decode($outputLIU);
curl_close($chLIU);

if ($infoLIU !== 200){
	echo "<p>ERROR: There was an error getting the list items:</p><pre>" . var_export($outputLIU, true) . "</pre>";
} else {
	echo "    Got items from list </br>";
}

$item_count = $outputjsonLIU->meta->item_count;
$item_list = $outputjsonLIU->data;

//******* get existing item count from list 
echo "    item count is: " . $item_count . "</br>";

//******* iterate over each item_id on list to delete
foreach ($item_list as $itemID) {
	$item_id = $itemID->id;
	$resource_id = $itemID->relationships->resource->data->id;
	$etag = etag_fetch($shortCode, $listID, $TalisGUID, $token);
	$input = delete_body($shortCode, $item_id, $etag, $listID);
    $diditdelete = delete_post($shortCode, $TalisGUID, $token, $input, $item_id, $listID);
	if ($diditdelete == 200) {
		fwrite($myfile, "$resource_id\t$listID\tItem deleted\r\n");	
	} else {
		fwrite($myfile, $resource_id . "\t" . $listID . "\t" . "Item not deleted" . "\r\n");
	}
}

if ($sourceselect === FALSE) {
// 	$sourceselect === FALSE; means use Alma API feed
	$xml=simplexml_load_file($alma_lookup);

	echo "</br></br>";
	// var_export($xml);

	echo "</br></br>";

	$record=$xml->QueryResult->ResultXml->rowset->Row;

		foreach ($record as $v) {
			$author = $v->Column1;
			$edition = $v->Column2;
			$resource_type = $v->Column3;
			$lcn = $v->Column4;
			$publisher_name = $v->Column5;
			$title = $v->Column6;
			$web_addresses = $v->Column7;
			$isbn=$v->Column8;
			
		// Below are the steps to create resources, add items to list, set importances.
		$resource_id = make_resource($shortCode, $title, $resource_type, $isbn, $token, $lcn, $author, $edition, $publisher_name, $web_addresses );
			
			if ($resource_id == null) {
				echo "<p>ERROR: There was an error creating resource for $title:</p><pre>";
				fwrite($myfile, "\t" . $listID . "Failed to create resource for $title" . "\r\n");
				continue;
			}

		$etag = etag_fetch($shortCode, $listID, $TalisGUID, $token);
		$input_item = guidv4();
		$input = itemBody($input_item, $etag, $listID, $resource_id);
		itemPost($shortCode, $TalisGUID, $token, $input, $title);
		$etag = etag_fetch($shortCode, $listID, $TalisGUID, $token);
		
		if (!empty ($importanceID)) {
			$input_imp = impBody($input_item, $etag, $listID, $resource_id, $importanceID) ;
			impPost($shortCode, $TalisGUID, $token, $input_imp, $input_item, $title);
		}
		
		echo "</br> - $title: Successfully created resource and added to list $listID";	
		fwrite($myfile, "https://rl.talis.com/3/$shortCode/items/$input_item.html?lang=en-GB&login=1" . "\t" . $listID . "\t" . "Successfully created resource " . "\r\n");
	}
} ELSE {
	
	$file_handle = fopen($uploadfile, "r");
    if ($file_handle == FALSE) {
		echo_message_to_screen(ERROR, "Could not open tsv file - Process Stopped.");
		exit;
    }

	while (($line = fgetcsv($file_handle, 1000, "\t")) !== FALSE) {
		
		$author = trim($line[0]);
		$edition = trim($line[1]);
		$resource_type = trim($line[2]);
		$lcn = trim($line[3]);
		$publisher_name = trim($line[4]);
		$title = trim($line[5]);
		$web_addresses = trim($line[6]);
		$isbn = trim($line[7]);
		
		// Below are the steps to create resources, add items to list, set importances.
		$resource_id = make_resource($shortCode, $title, $resource_type, $isbn, $token, $lcn, $author, $edition, $publisher_name, $web_addresses);
			if ($resource_id == null) {
				echo "<p>ERROR: There was an error creating resource for $title:</p><pre>";
				fwrite($myfile, "\t" . $listID . "Failed to create resource for $title" . "\r\n");
				continue;
			}
		$etag = etag_fetch($shortCode, $listID, $TalisGUID, $token);
		$input_item = guidv4();	
		$input = itemBody($input_item, $etag, $listID, $resource_id);
		itemPost($shortCode, $TalisGUID, $token, $input, $title);
		$etag = etag_fetch($shortCode, $listID, $TalisGUID, $token);

		if (!empty ($importanceID)) {
			$input_imp = impBody($input_item, $etag, $listID, $resource_id, $importanceID) ;
			impPost($shortCode, $TalisGUID, $token, $input_imp, $input_item, $title);
		}
		
		echo "</br> - $title: Successfully created resource and added to list $listID";
		fwrite($myfile, "https://rl.talis.com/3/$shortCode/items/$input_item.html?lang=en-GB&login=1" . "\t" . $listID . "\t" . "Successfully created resource " . "\r\n");
	}

	fclose($file_handle);
}

// Here we publish the list.
$etag = etag_fetch($shortCode, $listID, $TalisGUID, $token);
publish_single_list($shortCode, $listID, $TalisGUID, $token, $etag);

fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");
fclose($myfile);
?>