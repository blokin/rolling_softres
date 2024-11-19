<?php
$CSV = $_POST["CSV"];

$CSV_FILE = fopen("softres.csv", "w") or die("Unable to open softres.csv!");
fwrite($CSV_FILE, $CSV);

$output = shell_exec("/bin/bash softres.sh 1 5");
echo "<pre>$output</pre>";

?>
