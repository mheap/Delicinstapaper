#!/usr/bin/php
<?php


$deliciousUsername = "username";
$deliciousPrivateKey = "zHSnz.8MsmS82UIw17vKE-"; // It should look something like this
$instapaperUsername = "USERNAME";
$instapaperPassword = "PASSWORD";

$itemCount = 10;

// DON'T EDIT BELOW HERE

$instapaperAddURL = "https://www.instapaper.com/api/add";
$inboxURL = "http://feeds.delicious.com/v2/json/inbox/".$deliciousUsername."?private=".$deliciousPrivateKey;

$script_directory = substr($_SERVER['SCRIPT_FILENAME'], 0, strrpos($_SERVER['SCRIPT_FILENAME'], '/'));
$timestampFile = $script_directory.'/last_run.txt';

echo "\n";

echo "Running Delicious -> Instapaper Import\n\n";

// Check last time the script was run
$last_run = file_get_contents($timestampFile);

echo "The Script Was Last Run At: ".@date('dS M Y, H:i:s', $last_run)."\n\n";


// Get from inbox RSS

$feed = $inboxURL.'&count='.$itemCount;

$ch = curl_init(); 
curl_setopt($ch, CURLOPT_URL, $feed); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
$output = curl_exec($ch); 
curl_close($ch);  

// json decode it
$output = json_decode($output);

// Error checking
if (stristr($output[0]->d,"Please visit Delicious for a new private feed subscription")){
	echo "Error Accessing Delicious Bookmarks.\nPlease visit Delicious for a new private feed subscription\n\n"; die;
}

// Set up our POST curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $instapaperAddURL);

// Loop through

$success = array();
$error = array();
$new_last_updated = 0;

foreach ($output as $o){

	// Check against most recently added time

	// If this is older than the last one we added, break out
	if ($last_run >= strtotime($o->dt) ){
		break;
	}

	// Build post data
	$pData = array(
		"username" => $instapaperUsername,
		"password" => $instapaperPassword,
		"title" => $o->d.' - From '.$o->a,
		"url" => $o->u
	);

	$postString = '';
	foreach ($pData as $k => $v){
		$postString .= '&'.$k.'='.urlencode($v);
	}

	$postString = ltrim($postString, '&');

	// Post to instapaper
	curl_setopt($ch, CURLOPT_POST, count($pData));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$result = curl_exec($ch);

	if ($result == 201){
		$success[] = $o->d;
		if (strtotime($o->dt) > $new_last_updated){
			$new_last_updated = strtotime($o->dt);
		}
	}else{
		if ($result == 403){ $error[0] = "\nYou receieved a 403 response from Instapaper. Are you sure your login credentials are correct?\n"; }
		$error[] = $result.' | '.$o->d;
	}

}
curl_close($ch);

echo "---------------------------------\n";
echo "SUCCESS\n";
echo "---------------------------------\n";
if (count($success)){
	foreach ($success as $s){
		echo $s."\n";
	}
}else{
	echo "No Links Added\n";
}
echo "---------------------------------\n";

echo "---------------------------------\n";
echo "ERROR\n";
echo "---------------------------------\n";
if (count($error)){
	foreach ($error as $e){
		echo $e."\n";
	}
}else{
	echo "No Links Errored\n";
}
echo "---------------------------------\n";


// Update our file with the most recent item selected
if ($new_last_updated > 0){
	file_put_contents( $timestampFile, $new_last_updated );
}


echo "\n\n";
