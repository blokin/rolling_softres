<?php

$webhook= 'PASTE WEBHOOK ADDRESS HERE'; 

$message = "TESTING";

$data = array('content' => $message);
$options = array(
    'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data)
        )
);

file_get_contents($webhook, false, stream_context_create($options));

echo "</pre><br><br><a href=javascript:history.back()>Go back</a>";



?>
