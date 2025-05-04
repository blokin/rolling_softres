<!DOCTYPE html>
<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (isset($_GET['toggle_debug']) && ($_SESSION['role'] ?? '') === 'admin') {
  $_SESSION['debug'] = !($_SESSION['debug'] ?? false);
  header("Location: index.php?id=admin");
  exit;
}
?>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Rolling Soft Reserve Manager</title>
  <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
</head>
<body>

  <header>
    <h1>Rolling Soft Reserve Manager</h1>
    <nav>
      <a href="index.php">Home</a>
      <a href="?id=rules">Rules</a>
      <a href="?id=bonus">Bonuses</a>
      <a href="?id=logs">Logs</a>
      <?php if ($_SESSION['logged_in'] ?? false): ?>
        <a href="?id=admin">Admin</a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
          <a href="?id=admin&toggle_debug=1" class="button" style="background-color: <?= ($_SESSION['debug'] ?? false) ? '#22c55e' : '#ef4444' ?>;">
            <?= ($_SESSION['debug'] ?? false) ? 'Debug On' : 'Debug Off' ?>
          </a>
        <?php endif; ?>
        <a href="?id=logout" class="button">Logout</a>
      <?php else: ?>
        <a href="?id=login" class="button">Admin Login</a>
      <?php endif; ?>
    </nav>
  </header>
  <main>
    <?php
      $id = $_GET['id'] ?? '';
      switch($id) {
        default:
          include('home.php');
          break;
        case "logs": include('logs.php'); break;
        case "logview": include('logview.php'); break;
        case "bonus": include('bonus.php'); break;
        case "login": include('login.html'); break;
        case "logout": include('logout.php'); break;
        case "admin": include('softres.php'); break;
        case "rules": include('rules.html'); break;
      }
    ?>
  </main>
  <footer>Rolling Soft Reserve Manager v0.1 by <a href="http://www.github.com/blokin" target="_blank">blokin</a>.</footer>
</body>
</html>
