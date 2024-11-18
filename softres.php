<?php
$CSV = $_POST["CSV"];

$CSV_FILE = fopen("/var/www/html/softres/softres.csv", "w") or die("Unable to open file!");
fwrite($CSV_FILE, $CSV);

$output = shell_exec("/bin/bash /var/www/html/softres/softres.sh 1 5");
echo "<pre>$output</pre>";

?>
