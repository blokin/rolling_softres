<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('credentials.php');

$user = $_POST['user'] ?? '';
$pass = $_POST['pass'] ?? '';

if (empty($user) || empty($pass)) {
    echo "<section>Please provide both username and password.</section>";
    exit;
}

$port = isset($DB_PORT) ? $DB_PORT : 3306;
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $port);
if ($mysqli->connect_errno) {
    echo "<section>Database connection failed.</section>";
    exit;
}

$stmt = $mysqli->prepare("SELECT password_hash, role FROM HIDDEN_users WHERE username = ?");
$stmt->bind_param('s', $user);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo "<section>Invalid login.</section>";
    exit;
}

$stmt->bind_result($hashedPassword, $role);
$stmt->fetch();

if (password_verify($pass, $hashedPassword)) {
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = $user;
    $_SESSION['role'] = $role;
    header("Location: index.php?id=admin");
    exit;
} else {
    echo "<section>Incorrect password.</section>";
    exit;
}
?>
