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
	return $info;
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
      
function make_resource($shortCode, $title, $resource_type, $isbn, $token, $lcn, $author, $edition, $publisher_name, $web_addresses ){

	$uuid = guidv4();
	$url = 'https://rl.talis.com/3/' . $shortCode . '/resources';
	 
	if (!empty ($author)) {
		$author  = '"' . $author . '"';
	} else {
		$author = "null";
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
		$online_resource = $web_addresses;
		$web_addresses = '["' . $web_addresses . '"]';

		$online_resource_block = 
			'"online_resource": {
				"source": "uri",
				"link": "' . $online_resource . '"
			},';

	} else {
		$web_addresses = "null";
		$online_resource_block = "";
	}
					 
	$body = '{
		"data": {
		  "id": "' . $uuid . '",
		  "type": "resources",
		  "attributes": {
            "authors": [
                {
                    "full_name": ' . $author . '          
                }
						],
            "isbn13s": ' . $isbn . ',
            "edition": ' . $edition . ',
            "lcn": ' . $lcn . ',
			' . $online_resource_block . '
            "publisher_names": ' . $publisher_name . ',
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

?>
