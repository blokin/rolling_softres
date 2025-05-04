<section>
  <center>
    <h1>Current Bonuses</h1>
  </center>
  <?php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);

  function getTableList() {
    include(__DIR__ . '/credentials.php');
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    if ($mysqli->connect_errno) return [];
    $tables = [];
    $result = $mysqli->query("SHOW TABLES WHERE Tables_in_{$DB_NAME} NOT LIKE 'HIDDEN%'");
    while ($row = $result->fetch_row()) {
      $tables[] = $row[0];
    }
    sort($tables);
    $mysqli->close();
    return $tables;
  }

  function getUniqueRaiders($tables) {
    include(__DIR__ . '/credentials.php');
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    $raiders = [];
    foreach ($tables as $table) {
      $result = $mysqli->query("SELECT DISTINCT Username FROM `$table`");
      while ($row = $result->fetch_assoc()) {
        $raiders[strtolower($row['Username'])] = $row['Username'];
      }
    }
    $mysqli->close();
    ksort($raiders);
    return array_values($raiders);
  }

  $bonus_table = $_GET['bonus_table'] ?? '';
  $selected_raider = $_GET['raider'] ?? '';
  $tables = getTableList();
  $raiders = getUniqueRaiders($tables);
  ?>
  <form method="GET" action="" style="margin-bottom: 1rem;">
    <input type="hidden" name="id" value="bonus">

    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
      <label for="bonus_table" style="font-weight:bold;">Select a raid:</label>
      <select name="bonus_table" id="bonus_table" onchange="document.getElementById('raider').selectedIndex = 0; this.form.submit();">
        <option value="">-- Select a table --</option>
        <?php
          foreach ($tables as $table) {
            $selected = ($bonus_table === $table) ? 'selected' : '';
            echo "<option value=\"$table\" $selected>$table</option>";
          }
        ?>
      </select>

      <label for="raider" style="font-weight:bold;">Select a raider:</label>
      <select name="raider" id="raider" onchange="this.form.submit()">
        <option value="">-- Select a raider --</option>
        <?php
          foreach ($raiders as $raider) {
            $selected = ($selected_raider === $raider) ? 'selected' : '';
            echo "<option value=\"$raider\" $selected>$raider</option>";
          }
        ?>
      </select>
    </div>
  </form>

  <?php
  if ($selected_raider) {
    include(__DIR__ . '/credentials.php');
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    echo "<h2>Bonuses for " . htmlspecialchars($selected_raider) . "</h2>";
    echo "<table width='100%' border='1' cellspacing='0' cellpadding='6'>";
    echo "<thead><tr style='background-color:#f0f0f0; font-weight:bold;'>
            <td>Raid</td><td>Item 1</td><td>Bonus 1</td><td>Item 2</td><td>Bonus 2</td>
          </tr></thead><tbody>";
    foreach ($tables as $table) {
      $stmt = $mysqli->prepare("SELECT ItemName, Bonus, ItemName_2, Bonus_2 FROM `$table` WHERE Username = ?");
      $stmt->bind_param('s', $selected_raider);
      $stmt->execute();
      $stmt->bind_result($item1, $bonus1, $item2, $bonus2);
      while ($stmt->fetch()) {
        echo "<tr><td>$table</td><td>$item1</td><td>$bonus1</td><td>$item2</td><td>$bonus2</td></tr>";
      }
      $stmt->close();
    }
    echo "</tbody></table>";
    $mysqli->close();
  } elseif ($bonus_table) {
    $escapedTable = urlencode($bonus_table);
    $cmd = 'bonus-table';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);
    $url = "http://$host$path/script.php?cmd=$cmd&table=$escapedTable";

    echo "<h2>" . htmlspecialchars($bonus_table) . "</h2>";

    $output = @file_get_contents($url);

    if ($output === false || empty(trim($output))) {
      echo "<p style='color:red;'>Failed to fetch bonus table data.</p>";
    } else {
      echo "<table width='100%' border='1' cellspacing='0' cellpadding='6'>";
      echo "<thead><tr style='background-color:#f0f0f0; font-weight:bold;'>
              <td>Username</td><td>Item 1</td><td>Bonus 1</td><td>Item 2</td><td>Bonus 2</td>
            </tr></thead><tbody>";
      echo $output;
      echo "</tbody></table>";
    }
  }
  ?>
</section>
