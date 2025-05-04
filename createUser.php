<?php
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    echo "<section class='alert'>Unauthorized access.</section>";
    return;
}

$user = $_POST['NEW_USER'] ?? '';
$pass = $_POST['NEW_PASS'] ?? '';
$role = $_POST['NEW_ROLE'] ?? '';

if (empty($user) || empty($pass) || empty($role)) {
    echo "<section class='alert'>All fields are required.</section>";
    return;
}

include('credentials.php');
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

$check = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
$check->bind_param('s', $user);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    echo "<section class='alert'>User <b>$user</b> already exists.</section>";
    return;
}
$check->close();

$hash = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $mysqli->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $user, $hash, $role);

if ($stmt->execute()) {
    echo "<section class='alert' style='color:green;'>User <b>$user</b> created successfully.</section>";
} else {
    echo "<section class='alert'>Failed to create user.</section>";
}
$stmt->close();
$mysqli->close();
?>
