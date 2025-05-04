<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Detect CLI or Web
$cli = (php_sapi_name() === 'cli');
$command = $cli ? ($argv[1] ?? null) : ($_GET['cmd'] ?? null);
$itemId   = $cli ? ($argv[2] ?? null) : ($_GET['item'] ?? null);
$dryRun = isset($argv) && in_array('--dry-run', $argv, true);

// Load credentials
$config = parse_ini_file(__DIR__ . '/credentials.ini');

// CLI Colors
function color($text, $color = 'reset') {
    $colors = [
        'red' => "\033[31m", 'green' => "\033[32m", 'yellow' => "\033[33m",
        'blue' => "\033[34m", 'magenta' => "\033[35m", 'cyan' => "\033[36m",
        'bold' => "\033[1m", 'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

function formatName($name) {
    return ucfirst(strtolower($name));
}

function out($text, $color = 'reset', $spacer = false) {
    global $cli, $dryRun;
    if ($cli && $spacer) {
        echo PHP_EOL;
    }
    if (!$dryRun) {
        logAction($text);  // log to file, no extra newline
    }
    if ($cli) {
        echo color($text, $color) . PHP_EOL;
        if ($spacer) {
            echo PHP_EOL;
        }
    } else {
        echo "<pre>" . htmlspecialchars($text) . "</pre>";
    }
}

function initLogFile($table, $raidId) {
    global $dryRun;
    if ($dryRun) return null;
    $dir = __DIR__ . "/logs/$table";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $timestamp = time();
    $file = "$dir/{$raidId}_$timestamp.log";
    return fopen($file, 'a');
}

function initErrorLogFile($table, $raidId) {
    global $dryRun;
    if ($dryRun) return null;
    $dir = __DIR__ . "/logs/error/$table";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $timestamp = time();
    $file = "$dir/{$raidId}_$timestamp.log";
    file_put_contents($file, "");
    return fopen($file, 'a');
}

$logFile = null;
$errorLogFile = null;

function logAction($text, $isError = false) {
    global $logFile, $errorLogFile;
    $line = date('[Y-m-d H:i:s] ') . $text . PHP_EOL;
    if ($isError && $errorLogFile) {
        fwrite($errorLogFile, $line);
    } elseif ($logFile) {
        fwrite($logFile, $line);
    }
}

function getAccessToken($clientId, $clientSecret) {
    $ch = curl_init('https://oauth.battle.net/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => "$clientId:$clientSecret",
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials'
    ]);
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function getItemName($itemId, $accessToken) {
    if (!$itemId) return '[Invalid Item ID]';

    $url = "https://us.api.blizzard.com/data/wow/item/$itemId?namespace=static-classic-us&locale=en_US";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $accessToken"],
        CURLOPT_TIMEOUT => 5
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        echo "[ERROR] curl_exec failed: " . curl_error($ch) . "\n";
        return '[API Timeout]';
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode !== 200) {
        echo "[ERROR] HTTP $httpCode returned for item $itemId\n";
        echo "[DEBUG] Response: $response\n";
        return '[Unknown Item]';
    }

    $data = json_decode($response, true);

    // Check both possible locations
    if (isset($data['name'])) {
        return $data['name'];
    } elseif (isset($data['preview_item']['name'])) {
        return $data['preview_item']['name'];
    } else {
        echo "[ERROR] Item name not found for ID $itemId\n";
        return '[Unknown Item]';
    }
}

function dbConnect($cfg) {
    $mysqli = new mysqli($cfg['DB_HOST'], $cfg['DB_USER'], $cfg['DB_PASS'], $cfg['DATABASE'], $cfg['DB_PORT']);
    if ($mysqli->connect_errno) {
        out("DB Connection failed: " . $mysqli->connect_error, 'red');
        exit(1);
    }
    return $mysqli;
}

function validateTable($mysqli, $table, $dbName) {
    $safe = $mysqli->real_escape_string($table);
    $query = "SHOW TABLES WHERE Tables_in_$dbName NOT LIKE 'HIDDEN%' AND Tables_in_$dbName = '$safe'";
    $res = $mysqli->query($query);
    return ($res && $res->num_rows > 0);
}

function fetchSoftResData($raidId) {
    $url = "https://softres.it/api/raid/$raidId";
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    return $data['reserved'] ?? [];
}

if ($command === 'list-raiders') {
    $table = $cli ? ($argv[2] ?? null) : ($_GET['table'] ?? null);
    if (!$table) {
        out("Usage: list-raiders <table>", 'yellow');
        exit(1);
    }

    $mysqli = dbConnect($config);
    if (!validateTable($mysqli, $table, $config['DATABASE'])) {
        out("Invalid table: $table", 'red');
        exit(1);
    }

    $query = "SELECT Username FROM `$table` ORDER BY Username ASC";
    $res = $mysqli->query($query);

    if (!$res) {
        out("Query failed: " . $mysqli->error, 'red');
        $mysqli->close();
        exit(1);
    }

    $rowCount = $res->num_rows;
    if ($rowCount === 0) {
        out("No raiders found in $table.", 'yellow');
    } else {
        out("Raiders in $table:", 'bold');
        while ($row = $res->fetch_assoc()) {
            $name = formatName($row['Username']);
            out("$name", 'cyan');
        }
    }

    $mysqli->close();
    exit;
}

if ($command === 'list-tables') {
    $isWeb = !$cli;

    if ($isWeb) {
        header('Content-Type: text/plain');
    }

    $mysqli = dbConnect($config);
    $dbName = $config['DATABASE'];
    $result = $mysqli->query("SHOW TABLES WHERE Tables_in_$dbName NOT LIKE 'HIDDEN%'");

    if (!$result) {
        $error = "Failed to list tables: " . $mysqli->error;
        if ($isWeb) {
            echo $error;
        } else {
            echo color($error, 'red') . PHP_EOL;
        }
        exit(1);
    }

    while ($row = $result->fetch_row()) {
        echo $row[0] . "\n";
    }

    $mysqli->close();
    exit;
}
if ($command === 'bonus-table') {
    $table = $cli ? ($argv[2] ?? null) : ($_GET['table'] ?? null);
    if (!$table) {
        out("Usage: bonus-table <table>", 'yellow');
        exit(1);
    }

    $mysqli = dbConnect($config);
    if (!validateTable($mysqli, $table, $config['DATABASE'])) {
        out("Invalid table: $table", 'red');
        exit(1);
    }

    $query = "SELECT Username, ItemName, Bonus, ItemName_2, Bonus_2 FROM `$table` ORDER BY Username ASC";
    $res = $mysqli->query($query);

    if (!$res) {
        out("Query failed: " . $mysqli->error, 'red');
        $mysqli->close();
        exit(1);
    }

    $rowCount = $res->num_rows;
    if ($rowCount === 0) {
        out("No raiders found in $table.", 'yellow');
    } else {
        if ($cli) out("Bonus Table for $table:", 'bold');
        while ($row = $res->fetch_assoc()) {
            $name = formatName($row['Username']);
            $i1 = $row['ItemName'] ?? '[None]';
            $b1 = (int)($row['Bonus'] ?? 0);
            $i2 = $row['ItemName_2'] ?? '[None]';
            $b2 = (int)($row['Bonus_2'] ?? 0);

            if ($cli) {
              out("$name: $i1 ($b1), $i2 ($b2)", 'cyan');
            } else {
                echo "<tr><td>$name</td><td>$i1</td><td>$b1</td><td>$i2</td><td>$b2</td></tr>\n";
            }



        }
    }
    $mysqli->close();
    if ($cli) exit;
    return;
}

if ($command === 'list-logs') {
    $logBaseDir = __DIR__ . "/logs";
    if (!is_dir($logBaseDir)) {
        out("No logs directory found.", 'red');
        exit(1);
    }

    $tables = array_filter(scandir($logBaseDir), function($entry) use ($logBaseDir) {
        return $entry !== '.' && $entry !== '..' && is_dir("$logBaseDir/$entry") && $entry !== 'error';
    });

    if (empty($tables)) {
        out("No log tables found.", 'yellow');
        exit(0);
    }

    foreach ($tables as $table) {
        $logDir = "$logBaseDir/$table";
        $logs = array_diff(scandir($logDir, SCANDIR_SORT_DESCENDING), ['.', '..']);
        if (!empty($logs)) {
            out("Logs for $table:", 'bold');
            foreach ($logs as $log) {
                $path = "$logDir/$log";
                $timestamp = date("Y-m-d H:i:s", filemtime($path));
                out("  $log (modified: $timestamp)", 'cyan');
            }
        }
    }

    exit;
}

if ($command === 'bonus-plan') {
    set_time_limit(60); // Prevent browser timeouts if run via web

    $increment = $cli ? ($argv[4] ?? 1) : ($_GET['inc'] ?? 1);
    $increment = is_numeric($increment) ? (int)$increment : 1;
    $table     = $cli ? ($argv[2] ?? null) : ($_GET['table'] ?? null);
    $raidId    = $cli ? ($argv[3] ?? null) : ($_GET['raid'] ?? null);

    if (!$table || !$raidId) {
        out("Usage: bonus-plan <table> <softres_id> [increment]", 'yellow');
        exit(1);
    }

    $logFile = initLogFile($table, $raidId);
    $errorLogFile = initErrorLogFile($table, $raidId);

    $mysqli = dbConnect($config);

    if (!validateTable($mysqli, $table, $config['DATABASE'])) {
        out("Invalid table: $table", 'red');
        exit(1);
    }

    $reservations = fetchSoftResData($raidId);
    if (empty($reservations)) {
        out("No reservation data from SoftRes.", 'yellow');
        exit(0);
    }

    $resMap = [];
    foreach ($reservations as $r) {
        $name = strtolower($r['name']);
        $resMap[$name] = $r['items'];
    }

    $result = $mysqli->query("SELECT * FROM `$table`");
    $dbData = [];
    while ($row = $result->fetch_assoc()) {
        $dbData[strtolower($row['Username'])] = $row;
    }

    $token = getAccessToken($config['CLIENT_ID'], $config['CLIENT_SECRET']);
    if (!$token) {
        out("Failed to fetch Blizzard access token", 'red');
        exit(1);
    }

    $absentRaiderBonuses = [];
    $presentNames = array_keys($resMap);

    out("Raid: $table");
    out("Softres.it/raid/$raidId");

    foreach ($resMap as $name => $items) {
        $db = $dbData[$name] ?? null;

        if (!$db) {
            $res1 = $items[0] ?? null;
            $res2 = $items[1] ?? $res1;

            if (!$res1) {
                out("Skipping $name: no reserved items", 'yellow');
                continue;
            }

            $username = ucfirst($name);
            $isId1 = is_numeric($res1);
            $isId2 = is_numeric($res2);

            $itemId1 = $isId1 ? (int)$res1 : 0;
            $itemId2 = $isId2 ? (int)$res2 : 0;

            $itemName1 = $isId1 ? getItemName($itemId1, $token) : $res1;
            $itemName2 = $isId2 ? getItemName($itemId2, $token) : $res2;

            if (!$itemName1) $itemName1 = '[Unknown Item]';
            if (!$itemName2) $itemName2 = '[Unknown Item]';

            $lastAttended = date('Y-m-d');
            $stmt = $mysqli->prepare("INSERT INTO `$table`
                (Username, ItemID, ItemID_2, ItemName, ItemName_2, Bonus, Bonus_2, lastattended)
                VALUES (?, ?, ?, ?, ?, 0, 0, ?)");
            $stmt->bind_param('siisss', $username, $itemId1, $itemId2, $itemName1, $itemName2, $lastAttended);

            $stmt->execute();
            $stmt->close();

            out("$username added to table with: $itemName1, $itemName2", 'green');

            $dbData[$name] = [
                'Username' => $username,
                'ItemID' => $itemId1,
                'ItemID_2' => $itemId2,
                'ItemName' => $itemName1,
                'ItemName_2' => $itemName2,
                'Bonus' => 0,
                'Bonus_2' => 0,
            ];

            $db = $dbData[$name];
        }

        // Now apply bonuses
        $id1 = $db['ItemID'];
        $id2 = $db['ItemID_2'];
        $bonus1 = (int)$db['Bonus'];
        $bonus2 = (int)$db['Bonus_2'];

        $res1 = $items[0] ?? null;
        $res2 = $items[1] ?? $res1;

        $match1 = in_array($res1, [$id1, $id2]);
        $match2 = in_array($res2, [$id1, $id2]);

        if ($match1 && $match2) {
            $itemName1 = getItemName($res1, $token);
            $itemName2 = getItemName($res2, $token);
            $b1 = ($res1 == $id1) ? $bonus1 : $bonus2;
            $b2 = ($res2 == $id2) ? $bonus2 : $bonus1;
            $newB1 = $b1 + $increment;
            $newB2 = $b2 + $increment;

            $new1 = ($res1 == $id1) ? $newB1 : $bonus1;
            $new2 = ($res2 == $id2) ? $newB2 : $bonus2;

            $lastAttended = date('Y-m-d');
            $stmt = $mysqli->prepare("UPDATE `$table`
                SET Bonus = ?, Bonus_2 = ?, lastattended = ?
                WHERE Username = ?");
            $stmt->bind_param('iiss', $new1, $new2, $lastAttended, $db['Username']);
            $stmt->execute();
            $stmt->close();

            if ($res1 === $res2) {
                out(formatName($name) . " bonus applied: $itemName1 ($b1 ➔ $newB1)", 'green');
            } else {
                out(formatName($name) . " bonus applied: $itemName1 ($b1 ➔ $newB1), $itemName2 ($b2 ➔ $newB2)", 'green');
            }
        }
    }

    foreach ($dbData as $name => $row) {
        if (!isset($resMap[$name])) {
            $old1 = (int)$row['Bonus'];
            $old2 = (int)$row['Bonus_2'];
            $absentRaiderBonuses[$name] = [$old1, $old2];

            $bonus1 = max(0, $old1 - $increment);
            $bonus2 = max(0, $old2 - $increment);

            $itemName1 = $row['ItemName'];
            $itemName2 = $row['ItemName_2'];

            $stmt = $mysqli->prepare("UPDATE `$table` SET Bonus = ?, Bonus_2 = ? WHERE Username = ?");
            $stmt->bind_param('iis', $bonus1, $bonus2, $row['Username']);
            $stmt->execute();
            $stmt->close();

            out(formatName($row['Username']) . " penalty: $itemName1 ($old1 ➔ $bonus1), $itemName2 ($old2 ➔ $bonus2)", 'magenta');
        }
    }

    foreach ($absentRaiderBonuses as $name => [$old1, $old2]) {
        if ($old1 === 0 && $old2 === 0) {
            $usernameRaw = $dbData[$name]['Username'];
            $stmt = $mysqli->prepare("DELETE FROM `$table` WHERE Username = ?");
            $stmt->bind_param('s', $usernameRaw);
            $stmt->execute();
            $stmt->close();

            out(formatName($usernameRaw) . " removed due to inactivity", 'red');
        }
    }

    $mysqli->close();
    exit;
}
