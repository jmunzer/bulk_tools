<?php

function getToken($clientID, $secret) {
    $url = 'https://users.talis.com/oauth/tokens';
    $body = "grant_type=client_credentials";
   
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_USERPWD, "$clientID:$secret");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

    $return = curl_exec($ch);
    $info = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($info !== 200) {
        echo "<p>ERROR: There was an error getting a token:</p><pre>" . var_export($return, true) . "</pre>";
    } else {
        echo "Got Token</br></br>";
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

function getList($TalisGUID, $token, $shortCode, $itemID) {
    $url = 'https://rl.talis.com/3/' . $shortCode . '/draft_items/' . $itemID . '?include=list';

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

        if ($info !== 200){
			echo "<p>ERROR: There was an error getting the list information:</p><pre>" . var_export($output, true) . "</pre>";
		} else {
            $listID = $output_json->included[0]->id;
            $etag = $output_json->included[0]->meta->list_etag;
            $stuNote = $output_json->data->attributes->student_note;
    
            $ListDataArray = array();
            array_push($ListDataArray, $listID);
            array_push($ListDataArray, $etag);
            array_push($ListDataArray, $stuNote);
    
            return $ListDataArray;
		}
}

function delete_student_note($shortCode, $itemID, $etag, $listID, $TalisGUID, $token) {
    $url = 'https://rl.talis.com/3/' . $shortCode . '/draft_items/' . $itemID;

	$body =	'{
		"data": {
		  "id": "' . $itemID . '",
		  "type": "items",
		  "attributes": {
			"student_note": null
		  }
		},
		  "meta": {
			  "has_unpublished_changes": true,
			  "list_etag": ' . $etag . ',
			  "list_id": "' . $listID . '"
		  }
	  }';
   
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		
		"X-Effective-User: $TalisGUID",
		"Authorization: Bearer $token",
		'Cache-Control: no-cache'
	));

	curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

	$output = curl_exec($ch);
	$deleteResponse = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	curl_close($ch);

    return $deleteResponse;

}

function publishlists($shortCode, $publishListArray_encoded, $TalisGUID, $token) {
    $url = 'https://rl.talis.com/3/' . $shortCode . '/bulk_list_publish_actions';
    $body = '{
                "data": {
                    "type": "bulk_list_publish_actions",
                    "relationships": {
                        "draft_lists": {
                            "data": ' . $publishListArray_encoded . '
                        }
                    }
                }	
            }';

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(

        "X-Effective-User: $TalisGUID",
        "Authorization: Bearer $token",
        'Cache-Control: no-cache'
    ));

    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $output = curl_exec($ch);
    $publishResponse = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $publishResponse;

}

?>