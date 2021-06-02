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
//fwrite($myfile, "List name" . "\t" . "List ID" . "\t" . "Section Status" . "\t" . "Item Status" . "\t" . "Item Status" . "\t" . "Item Status" . "\t" . "List Published" . "\r\n");

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
//******* get existing item count from list */
echo "    item count is: " . $item_count . "</br>";

//******* iterate over each item_id on list */
foreach ($item_list as $itemID) {
	$item_id = $itemID->id;
	echo "Item ID is: " . $item_id . "</br>";



	$etag = etag_fetch($shortCode, $listID, $TalisGUID, $token);
	$input = delete_body($shortCode, $item_id, $etag, $listID);
    delete_post($shortCode, $TalisGUID, $token, $input, $item_id, $listID);
};


//*****ALMA API CALL************

$alma_lookup = 'https://api-eu.hosted.exlibrisgroup.com/almaws/v1/analytics/reports?path=%2Fshared%2FUniversity%20of%20Westminster%2FReports%2FContent%20and%20Digital%20Services%2FNew%20Acquisitions%20-%20TARL%20API&limit=25&col_names=true&apikey=l7xx94cb8b1505b64e7587a9fc5d323ebfb1';


$xml=simplexml_load_file($alma_lookup);

echo "</br></br>";
//*var_export($xml);

echo "</br></br>";

$record=$xml->QueryResult->ResultXml->rowset->Row;

foreach ($record as $v) {
$isbn=$v->Column8;
$resource_type = $v->Column3;
$lcn = $v->Column4;
$title = $v->Column6;
$full_name = $v->Column1;
$edition = $v->Column2;
$web_addresses = $v->Column7;
$publisher_name = $v->Column5;

echo "isbn= ". $isbn."</t>";
echo " / resource type = ".$resource_type."</t>";
echo " / lcn= ". $lcn."</t>";
echo " / title = ".$title."</t>";
echo " / web address= ".$web_addresses."</t>";
echo " / publisher = ".$publisher_name."</t>";
echo " / edition= ". $edition."</t>";
echo " / author= ". $full_name."</br>";


var_export ($web_addresses);
echo "</br></br>";


echo "------------</br>";
$resource_id = make_resource($shortCode, $title, $resource_type, $isbn, $TalisGUID, $token, $lcn, $full_name, $edition, $publisher_name, $web_addresses );
$etag = etag_fetch($shortCode, $listID, $TalisGUID, $token);
	$input_item = guidv4();	
	$input = itemBody($input_item, $etag, $listID, $resource_id);
	itemPost($shortCode, $TalisGUID, $token, $input);
	$etag = etag_fetch($shortCode, $listID, $TalisGUID, $token);
	$input_imp = impBody($input_item, $etag, $listID, $resource_id) ;
	impPost($shortCode, $TalisGUID, $token, $input_imp, $input_item);
	


}
$etag = etag_fetch($shortCode, $listID, $TalisGUID, $token);
publish_single_list($shortCode, $listID, $TalisGUID, $token, $etag);

//fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");



//*fclose($file_handle);
fclose($myfile);
?>