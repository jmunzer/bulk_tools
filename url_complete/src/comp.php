<?php

print("</br><a href='url.html'>Back to url tool</a>");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<p>Starting...</p>";

// Functions go here

function modify_url($resourceID, $web_addresses, $oldURL_index, $newURL) {
	
	$web_addresses[$oldURL_index] = $newURL;  // update the found address

	$template = '{
				"data": {
					"type": "resources",
					"id": "' . $resourceID . '",
					"attributes": {
						"web_addresses": [],
						"online_resource": {
							"source": "uri",
							"link": "' . $newURL. '"
						}
					} 
				}
			}';
	$template_obj = json_decode($template, true);
	$template_obj['data']['attributes']['web_addresses'] = $web_addresses;

	return json_encode($template_obj);
}

function post_url($shortCode, $resourceID, $input, $TalisGUID, $token) {
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

	curl_setopt($ch2, CURLOPT_POSTFIELDS, $input);

	$output2 = curl_exec($ch2);
	$info2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
	curl_close($ch2);
	
	if ($info2 !== 200){
		echo "<p>ERROR: There was an error updating the URL:</p><pre>" . var_export($output2, true) . "</pre>";
		fwrite($myfile, "ERROR: Resource URL Not Updated \t");
	} else {
		echo "Resource URL Updated Successfully</br>";
		fwrite($myfile, "Resource URL Updated Successfully" . "\t");
	}
}

/**
 * Get the user config file. This script will fail disgracefully if it has not been created and nothing will happen.
 */
require('../../user.config.php');

echo "Tenancy Shortcode set: " . $shortCode;
echo "</br>";

echo "Client ID set: " . $clientID;
echo "</br>";

echo "User GUID to use: " . $TalisGUID;
echo "</br>";

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

	$tokenURL = 'https://users.talis.com/oauth/tokens';
	$content = "grant_type=client_credentials";
	$date = date('Y-m-d\TH:i:s'); // "2015-12-21T15:44:36"

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

	// Creating a report file...
$logfile = "../../report_files/urlcomplete_output.log";
$myfile = fopen($logfile, "a") or die("Unable to open urlcomplete_output.log");
fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");
fwrite($myfile, "Item ID,Resource ID, \r\n");

$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $tokenURL);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_USERPWD, "$clientID:$secret");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $content);

	$return = curl_exec($ch);
	$info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
	if ($info !== 200){
		echo "<p>ERROR: There was an error getting a token:</p><pre>" . var_export($return, true) . "</pre>";
	}

curl_close($ch);

$jsontoken = json_decode($return);

if (!empty($jsontoken->access_token)){
	$token = $jsontoken->access_token;
} else {
	echo "<p>ERROR: Unable to get an access token</p>";
	exit;
}

//***********READ**DATA******************

$row = 1;
if (($file_handle = fopen($uploadfile, "r")) !== FALSE) {
	while (($line = fgetcsv($file_handle, 1000, ",")) !== FALSE) {

		$num = count($line);
		$row++;

		$itemID = $line[0];
		$oldURL = $line[1];
		$newURL = $line[2];

		echo $itemID . "\t";
		echo $oldURL . "\t";
		echo $newURL . "\t";

		//************GET_RESOURCE_ID***************

		$item_lookup = "https://rl.talis.com/3/" . $shortCode . "/draft_items/" . $itemID . "?include=resource";

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
			echo "<p>ERROR: There was an error getting the draft item:</p><pre>" . var_export($output, true) . "</pre>";
			continue;
		}
		
		# if we have everything we need to proceed
		# we want the item link, the resource id and the web_address,
		# Online_resource is optional and handled separately 
		if (!empty($output_json->included[0]->id)){
			$self = $output_json->data->links->self;
			fwrite($myfile, $self ."\t");
		} else {
			echo "There was no link to self for this item. This should never happen!";
			continue;
		}

		if (!empty($output_json->data->links->self)){
			$resourceID = $output_json->included[0]->id;
			fwrite($myfile, $resourceID ."\t");
		} else {
			echo "There was no Resource ID on this item. We cannot proceed to update this item.";
			continue;
		}

		if (!empty($output_json->included[0]->attributes->web_addresses)) {
			$web_addresses = $output_json->included[0]->attributes->web_addresses;
		} else {
			echo "There are no web addresses on this item. But that is probably Okay.";
			# note that in this situation this is OK if we want to go on and add one later.
		}

		if (!empty($output_json->included[0]->attributes->online_resource->link)){
			$online_resource = $output_json->included[0]->attributes->online_resource->link;
		} else {
			echo "There was no online_resource selected.";
		}

		$oldURL_found = array_search($oldURL, $web_addresses);
		
		if (isset($oldURL_found)) {
			echo "Found Matching URL \t";
			fwrite($myfile, "Found Matching URL at index: [$oldURL_found]");
			$input = modify_url($resourceID, $web_addresses, $oldURL_found, $newURL);

			if ($shouldWritetoLive == "true") {
				post_url($shortCode, $resourceID, $input, $TalisGUID, $token);
			} else {
				echo "Resource URL Not Updated - Dry Run";
				fwrite($myfile, "Resource URL Not Updated - Dry Run");
			}

		} else {
			echo "\t ERROR: no matching URL found in web address array. Moving onto next row...";
			continue;
		}

	}

}
fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");

fclose($file_handle);
fclose($myfile);

echo $myfile;
print("</br><a href=$logfile>Click Here to download your output.log file.</a>");

?>

