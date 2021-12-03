<?php

print("</br><a href='openURL.html'>Back to openURL tool</a>");

ini_set('max_execution_time', '0');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<p>Starting</p>";

require('functions.php');

try {
	require('../../SOURCE/models/UploadedFile.php');
	require('../../SOURCE/models/UserConf.php');
	require('../../SOURCE/models/Tool.php');

	$uploadedFile = new UploadedFile();	
	$userConf = new UserConf();	

	$columnConfig = [
		1 => "ITEM",
		2 => ""
	];
	$myOperation = function(){};
	$tool = new Tool($uploadedFile, $columnConfig, $myOpenURLTask);

} catch(Exception $e){
	echo_message_to_screen(ERROR, $e->message);
	exit(1);
}

/// old code below here!!

//**********CREATE LOG FILE TO WRITE OUTPUT*

$myfile = fopen("../../report_files/openurl_output.log", "a") or die("Unable to open openurl_output.log");
fwrite($myfile, "Started | Input File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n\r\n");
fwrite($myfile, "Item Link" . "\t" . "Resource Link" . "\t" ."Item Title" . "\t" . "List Title" . "\t" . "Old Online Resource" . "\t" . "Old Online Link" . "\t" . "Outcome" . "\r\n");


$tokenURL = 'https://users.talis.com/oauth/tokens';
$content = "grant_type=client_credentials";

$token=token_fetch($clientID, $secret);

// update_resource($shortCode, $token, $resourceID)

$file_handle = $uploadedFile->getFileHandler();
	echo "Item ID" . "\t" . "Item Link" . "\t" . "Resource Link" . "\t" ."Item Title" . "\t" . "List Title" . "\t" . "Old Online Resource" . "\t" . "Old Online Link" . "\t" . "Outcome";
	while (($line = fgetcsv($file_handle, 1000, ",")) !== FALSE) {
		
		$itemID = trim($line[0]);
		fwrite($myfile, $itemID . "\t");
		fwrite($myfile, "https://rl.talis.com/3/$shortCode/items/$itemID.html?lang=en-GB&login=1" . "\t");

		$resourceData = getResource($shortCode, $itemID, $token, $TalisGUID);

			$resourceID = $resourceData[0];
			$itemTitle =  $resourceData[1];
			$listTitle =  $resourceData[2];
			$old_OnlineResource = $resourceData[3];
			$old_OnlineLink = $resourceData[4];
			echo "</br>";
			echo $itemID . "\t";
			fwrite($myfile, "https://$shortCode.rl.talis.com/resources/$resourceID.html" . "\t");
			echo $itemTitle . "\t";
			fwrite($myfile, $itemTitle . "\t");
			echo $listTitle . "\t";
			fwrite($myfile, $listTitle . "\t");
			echo $old_OnlineResource . "\t";
			fwrite($myfile, $old_OnlineResource . "\t");
			echo $old_OnlineLink . "\t";
			fwrite($myfile, $old_OnlineLink . "\t");

		$PatchOutcome = update_resource($shortCode, $token, $resourceID);
			if ($PatchOutcome == 200) {
				echo "Successfully updated to openURL";
				fwrite($myfile, "Successfully updated to openURL\r\n");
			} else {
			echo "Not updated item - requires investigation.";
			fwrite($myfile, "Not updated item - requires investigation\r\n");
			}
	}


	fclose($file_handle);

echo "</br></br>Finished run";
fwrite($myfile, "\r\n" . "Stopped | End of File: $uploadfile | Date: " . date('d-m-Y H:i:s') . "\r\n");
fclose($myfile);
?>
