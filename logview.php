<pre><?php
$log = $_GET['logfile'];
$log_dir = $_GET['log_dir'];
echo "<h2>Log file: $log_dir $log</h2>";
include("logs/" . $log_dir . "/" . $log);
?></pre>
