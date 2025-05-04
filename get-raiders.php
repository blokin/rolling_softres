<?php
include('credentials.php');
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
$table = $_GET['table'] ?? '';

if (preg_match('/^\w+$/', $table)) {
    $escaped = $mysqli->real_escape_string($table);
    $result = $mysqli->query("SELECT DISTINCT Username FROM `$escaped` ORDER BY Username ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo '<option value="' . htmlspecialchars($row['Username']) . '">' . htmlspecialchars($row['Username']) . '</option>';
        }
    }
}
?>
