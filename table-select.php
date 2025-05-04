<?php
$scriptUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/script.php?cmd=list-tables';
$output = @file_get_contents($scriptUrl);

if ($output === false) {
    echo "<option disabled>Error loading tables</option>";
    return;
}

// Normalize newlines and split by line
$lines = preg_split('/\r\n|\r|\n/', trim($output));

foreach ($lines as $table) {
    $table = trim($table);
    if ($table !== '') {
        echo '<option value="' . htmlspecialchars($table) . '">' . htmlspecialchars($table) . '</option>';
    }
}
?>
