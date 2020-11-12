<?php
/* 
A file to test a local network connection.
*/

// User set variables
// Get the user config file. This script will fail disgracefully if it has not been created and nothing will happen.
require('../user.config.php');

function echo_message_to_screen($message){
    echo "</br> $message";
}
echo "<p>";

if (!empty($shortCode)) {
    echo "Tenancy Shortcode set: " . $shortCode;
    echo "<br>";
}

if (!empty($clientID)) {
    echo "Client ID set: " . $clientID;
    echo "<br>";
}

if (!empty($secret)) {
    echo "Secret is set: REDACTED";
    echo "<br>";
}

if (!empty($TalisGUID)) {
    echo "User GUID to use: " . $TalisGUID;
    echo "<br>";
}

echo "</p>";

echo_message_to_screen("Check to see if DNS lookup for <code>users.talis.com</code> is working. 'true' is good...");
echo_message_to_screen( var_export(dns_check_record('users.talis.com', 'A'),true));

// Get an API token
echo_message_to_screen("Check to see if we can get an API token");
$tokenURL = 'https://users.talis.com/oauth/tokens';
$content = "grant_type=client_credentials";

// debug output
ob_start();
$out = fopen('php://output', 'w');

$ch = curl_init();

// these two lines for debug only
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_STDERR, $out);

curl_setopt($ch, CURLOPT_URL, $tokenURL);
curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_USERPWD, "$clientID:$secret");
curl_setopt($ch, CURLOPT_POSTFIELDS, $content);

$return = curl_exec($ch);

fclose($out);
$debug = ob_get_clean();

$info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$all_info = curl_getinfo($ch);

    
if ($info !== 200){
    echo_message_to_screen("Unable to retrieve an API token: <pre>" . var_export($return, true) . "</pre>");
    echo_message_to_screen("ALL INFO <pre>" . var_export($all_info, true) . "</pre>");
    echo_message_to_screen("DEBUG <pre>" . var_export($debug, true) . "</pre>");
    exit;
} else {
    echo_message_to_screen("Success: got an API token: <pre>" . var_export($return, true) . "</pre>");
    echo_message_to_screen("ALL INFO<pre>" . var_export($all_info, true) . "</pre>");
    echo_message_to_screen("DEBUG <pre>" . $debug . "</pre>");
}

curl_close($ch)

?>