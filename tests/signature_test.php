<?php

// payload is the array passed to the `payload` method of the webhook
// secret is the string given to the `signUsingSecret` method on the webhook.
$secret = '1234567890';
$payloadPath = __DIR__.'/payload.json';
$payloadContents = file_get_contents($payloadPath);

if ($payloadContents === false) {
    fwrite(STDERR, "Unable to read payload file at {$payloadPath}.".PHP_EOL);
    exit(1);
}

$payloadData = json_decode($payloadContents, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, 'Invalid JSON payload: '.json_last_error_msg().PHP_EOL);
    exit(1);
}

$payloadJson = json_encode($payloadContents);
if ($payloadJson === false) {
    fwrite(STDERR, 'Failed to encode payload to JSON.'.PHP_EOL);
    exit(1);
}

$signature = hash_hmac('sha256', $payloadJson, $secret);
if ($signature !== '1e20eba0f624ab666f714619b07b39db1ac1878cb031893b248bbe2a3ccf4819') {
    fwrite(STDERR, 'Signature does not match.'.PHP_EOL);
}
echo $signature.PHP_EOL;
