<?php

function impPost($shortCode, $TalisGUID, $token, $input_imp, $input_item, $title) {
	
	//var_export($input_imp);
	$item_patch = 'https://rl.talis.com/3/' . $shortCode . '/draft_items/' . $input_item ;
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $item_patch);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	));

	curl_setopt($ch, CURLOPT_POSTFIELDS, $input_imp);

	
	$output = curl_exec($ch);
	$info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	// echo $info;

	$output_json_etag = json_decode($output);

	curl_close($ch);
	if ($info !== 200){
		echo "<p>ERROR: There was an error adding the importance to: $title</p><pre>" . var_export($output, true) . "</pre>";
	}
}

function impBody($input_item, $etag, $listID, $resource_id, $importanceID) {
					
	$input_imp= ' {
	"data": {
		"id": "' . $input_item . '",
		"type": "items",
		"relationships": {
		"importance": {
			"data": {
			"id": "' . $importanceID . '",
			"type": "importances"
			}
		}
		}
	},
	"meta": {
		"list_id": "' . $listID .'",
		"list_etag": "' . $etag . '"
	}
	}';

	return $input_imp;
}

function delete_body($shortCode, $item_id, $etag, $listID) {
	

	$input = '	{
					"meta": {
						"list_etag": "' . $etag . '",
						"list_id": "' . $listID . '"
					}
				}';
	return $input;
}

function delete_post($shortCode, $TalisGUID, $token, $input, $item_id, $listID) {
    //var_export($input);
	$delete_url = 'https://rl.talis.com/3/' . $shortCode . '/draft_items/' . $item_id;
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $delete_url);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	));

	curl_setopt($ch, CURLOPT_POSTFIELDS, $input);


	$output = curl_exec($ch);
	$info = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	curl_close($ch);
	if ($info !== 200){
		echo "<p>ERROR: There was an error deleting the item:</p><pre>" . var_export($output, true) . "</pre>";
		//fwrite($myfile, "Item not deleted - failed" . "\t");
		} else {
		echo "Deleted item $item_id from list $listID</br>";
		//fwrite($myfile, "Item deleted successfully" . "\t");
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

function publish_single_list($shortCode, $listID, $TalisGUID, $token, $etag) {

	$body = '{
    "data": {
        "type": "list_publish_actions"
    },
    "meta": {
        "list_etag": "' . $etag . '"
			}
	}';
	//var_export ($etag);
	//var_export ($body);
	
	$url = 'https://rl.talis.com/3/' . $shortCode . '/draft_lists/' . $listID . '/publish_actions';
	//echo $url;
	$ch = curl_init();
	
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	
	));
	$output = curl_exec($ch);
	$info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$output_json = json_decode($output);
	curl_close($ch);
	 
     if ($info !== 202){
		echo "<p>ERROR: There was an error publishing list $listID:</p><pre>" . var_export($output, true) . "</pre>";
		
	} else {
		echo "</br></br>List: $listID changes successfully published - script complete</br>";
	}
}

function etag_fetch($shortCode, $listID, $TalisGUID, $token) {
	$url = 'https://rl.talis.com/3/' . $shortCode . '/draft_lists/' . $listID;
	
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
	$output_json = json_decode($output);
	curl_close($ch);
	
	$etag = $output_json->data->meta->list_etag;
	
	/* uncomment for debugging
	echo "    </br>";
	echo "    Updated ETag: " . $etag . "</br>";
	echo "</br>";
	*/

	return $etag;
}
      
function make_resource($shortCode, $title, $resource_type, $isbn, $token, $lcn, $full_name, $edition, $publisher_name, $web_addresses) {

	$uuid = guidv4();
	$url = 'https://rl.talis.com/3/' . $shortCode . '/resources';
	 
	if (!empty ($full_name)) {
		$full_name  = '"' . $full_name . '"';
	} else {
		$full_name = "null";
	}	 
	 
	if (!empty ($isbn)) {
		$isbn = '["' . $isbn . '"]';
	} else {
		$isbn = "null";
	}
			
	if (!empty ($edition)) {
		$edition = '"' . $edition . '"';
	} else {
		$edition = "null";
	}
		 
	if (!empty ($title)) {
		$title = '"' . $title . '"';
	} else {
		$title = "null";
	}
	
	if (!empty ($lcn)) {
		$lcn = '"' . $lcn . '"';
	} else {
		$lcn = "null";
	}
		
	if (!empty ($publisher_name)) {
		$publisher_name = '["' . $publisher_name . '"]';
	} else {
		$publisher_name = "null";
	}
	
	if (!empty ($resource_type)) {
		$resource_type = '"' . $resource_type . '"';
	} else {
		$resource_type = "null";
	}
			 
	if (!empty ($web_addresses)) {
		$web_addresses = '["' . $web_addresses . '"]';
	} else {
		$web_addresses = "null";
	}
					 
	$body = '{
		"data": {
		  "id": "' . $uuid . '",
		  "type": "resources",
		  "attributes": {
            "authors": [
                {
                    "full_name": ' . $full_name . '          
                }
						],
            "isbn13s": ' . $isbn . ',
            "edition": ' . $edition . ',
            "lcn": ' . $lcn . ',
            "publisher_name": ' . $publisher_name . ',
            "resource_type": ' . $resource_type . ',
            "title": ' . $title . ',
            "web_addresses": ' . $web_addresses . '
						},
		  "links": {},
		  "meta": {},
		  "relationships": {}
				}
	  }';
	
	//var_export($body);
	
	$ch = curl_init();
	
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
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
		echo "<p>ERROR: There was an error creating resource for $isbn:</p><pre>" . var_export($output, true) . "</pre>";
	}
	
	return $uuid;
}

function update_resource($shortCode, $token, $resourceID) {

	$url = 'https://rl.talis.com/3/' . $shortCode . '/resources/' . $resourceID;
	 
	$body = '{
				"data": {
					"type": "resources",
					"id": "' . $resourceID . '",
					"attributes": {
						"online_resource": {
							"source": "open_url"
						}
					}
				}
			}';
	
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

function guidv4($data = null) {

    // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);

    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    // Output the 36 character UUID.
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function itemPost($shortCode, $TalisGUID, $token, $input, $title) {
	
	
	//var_export($input);
	$item_patch = 'https://rl.talis.com/3/' . $shortCode . '/draft_items/';
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $item_patch);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	));

	curl_setopt($ch, CURLOPT_POSTFIELDS, $input);

	
	$output = curl_exec($ch);
	$info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	//echo $info;

	$output_json_etag = json_decode($output);
	//$etag = $output_json_etag->meta->list_etag;

	curl_close($ch);
	if ($info !== 201){
		echo "<p>ERROR: There was an error adding the item: $title:</p><pre>" . var_export($output, true) . "</pre>";
	}	
}

function itemBody($input_item, $etag, $listID, $resource_id) {
	//$uuid = guidv4();		
			
	$input = ' {"data": {
	"id": "' . $input_item . '",
	"type": "items",
	"relationships": {
		"container": {
		"data": {
			"id": "' . $listID . '",
			"type": "lists"
		},
		"meta": {
			"index": 0
		}
		},

		
		"resource": {
		"data": {
			"id": "' . $resource_id . '",
			"type": "resources"
		}
		}

	}
	},

	"meta": {
	"list_etag": "' . $etag . '",
	"list_id": "' . $listID . '"
	}
	}';

			return $input;
}

function getResource($shortCode, $itemID, $token, $TalisGUID) {
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