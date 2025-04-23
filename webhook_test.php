<?php

$webhook= 'https://discord.com/api/webhooks/1070896679059988581/zO8x6REnY5C8N52vh4_c6n0Jfuzbx3QXQnhfeY1XOEzzIIVzS03-IzN2Nj-JvVxOoSXw'; //example https://discord.com/api/webhooks/818892216943509504/iaF6RJ2SA1eH4dyWq4iMWNNigAHCzzLGK6e_DBOzPCkh0C6-R0UQ8TWjW87vi51K30Ei

$message = "TESTING";

$data = array('content' => $message);
// use key 'http' even if you send the request to https://...
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
