<?php

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