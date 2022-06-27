<?php

function writeToPage($fields){
	echo "<tr>";
	foreach ($fields as $f){
		echo "<td>" . $f . "</td>";
	}
	echo "</tr>";
}

function writeToAuditFile($fileResource, $fields){
	$line = implode("\t", $fields);
	return fwrite($fileResource, $line);
}

function writeToAuditFileOrExitOnFail($fileResource, $fields){
	if (writeToAuditFile($fileResource, $fields) == false) {
		echo "Audit failed to write, stopping execution\n";
		echo "Final audit values: \n";
		echo var_export($fields, true);
		exit;
	}
}

function token_fetch($clientID, $secret) {
	$tokenURL = 'https://users.talis.com/oauth/tokens';
	$content = "grant_type=client_credentials";

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
	} else {
		echo "Successfully received token</br>";
	}

	curl_close($ch);

	$jsontoken = json_decode($return);

	if (!empty($jsontoken->access_token)){
		$token = $jsontoken->access_token;
	} else {
		echo "<p>ERROR: Unable to get an access token</p>";
		exit;
	}
	return $token;
}

function update_resource($shortCode, $token, $resourceID) {
	$url = 'https://rl.talis.com/3/' . $shortCode . '/resources/' . $resourceID;
	 
	$body = json_encode([
		"data" => [
			"type" => "resources",
			"id" => $resourceID,
			"attributes" => [
				"online_resource" => [
					"source" => "open_url"
				],
			]
		]
	]);
	// var_export($body);
	
	$ch = curl_init();
	
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

	$output = curl_exec($ch);
	$info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	//echo $info;
	$output_json = json_decode($output);
	curl_close($ch);
	if ($info !== 200){
		echo "<p>ERROR: There was an error updating resource for $resourceID:</p><pre>" . var_export($output, true) . "</pre>";
	}
	return $info;
}

function getResourceDataFromItemID($shortCode, $itemID, $token, $TalisGUID) {
	$url = 'https://rl.talis.com/3/' . $shortCode . '/draft_items/' . $itemID . "?include=resource,list";
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	));

	$output = curl_exec($ch);
	$info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$outputjson = json_decode($output);

	curl_close($ch);

	$resourceID = $outputjson->data->relationships->resource->data->id;
	$itemTitle =  $outputjson->included[0]->attributes->title;
	$listTitle =  $outputjson->included[1]->attributes->title;

	$old_OnlineResource = "";
	$old_OnlineLink = "";

	if(!empty($outputjson->included[0]->attributes->online_resource)) {
		$old_OnlineResource =  $outputjson->included[0]->attributes->online_resource->source;
		
		if(!empty($outputjson->included[0]->attributes->online_resource->link)) {
			$old_OnlineLink =  $outputjson->included[0]->attributes->online_resource->link;
		}
	}

	$resourceData = array($resourceID, $itemTitle, $listTitle, $old_OnlineResource, $old_OnlineLink);
	return $resourceData;
}

function input_validator($file_handle) {
	
    // regex pattern for a valid UUID
    $UUID_valid = '/^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}$/';
    
    $line_of_text = fgets($file_handle);

    // clean up the input value - in this case, the itemID
    $parts = explode(" ", $line_of_text);
    $item = filter_var(trim($parts[0]), FILTER_VALIDATE_URL);

    if (empty($item)){
        // this is not a URL
        $itemId = trim($parts[0]);
        echo "this is not a URL</br>";
        echo "input string: $itemId</br>";
        
    } else {
        // this is a URL
        $itemLink = preg_split('/[\/\.]/', $item);
        $itemId = implode(" ",preg_grep($UUID_valid, $itemLink));
        echo "this is a URL</br>";
        echo "input string: $parts[0]";
    }

    // validate the UUID
    if (preg_match($UUID_valid, $itemId)) {
    echo "Valid UUID: $itemId";
    } else {
        echo "Error with reading a valid UUID, please verify input file: $itemId";
        $itemId = "";
    }
    
    return $itemId;
}

function dryRun() {

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

	return $shouldWritetoLive;

}