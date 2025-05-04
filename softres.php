<?php
include('credentials.php');

function logAudit($message) {
    $logFile = __DIR__ . '/logs/audit.log';
    $timestamp = date("Y-m-d H:i:s");
    $entry = "[$timestamp] $message\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
if ($mysqli->connect_error) {
    echo "<section>Database connection failed: " . $mysqli->connect_error . "</section>";
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo "<section>Please log in with a valid username and password.</section>";
    exit;
}

$role = $_SESSION['role'] ?? '';
$toastMessage = '';

try {
    // Add Softres Data
    if (!empty($_POST['DB_TABLE']) && !empty($_POST['URL'])) {
        $table = $_POST['DB_TABLE'];
        $url = $_POST['URL'];
        $inc = $_POST['INCREMENT'] ?? 2;

        $escapedTable = urlencode($table);
        $escapedRaid = urlencode($url);
        $escapedInc = urlencode($inc);
        $target = "http://softres.justinmancini.net/softresv10/script.php?cmd=bonus-plan&table=$escapedTable&raid=$escapedRaid&inc=$escapedInc";

        flush();
        $output = @file_get_contents($target);

        $logDir = __DIR__ . "/logs/$table";
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $timestamp = time();
        $logFile = "$logDir/{$url}_{$timestamp}.log";

        if ($output === false) {
            $errorDir = __DIR__ . "/logs/error/$table";
            if (!is_dir($errorDir)) {
                mkdir($errorDir, 0777, true);
            }
            $errorLog = "$errorDir/{$url}_{$timestamp}.log";
            file_put_contents($errorLog, "Failed to retrieve bonus-plan from script.php\n");
            echo "<p style='color:red;'>Failed to apply softres data.</p>";
        } else {
            file_put_contents($logFile, $output);
            logAudit("Softres data added to '$table' from raid URL '$url' by {$_SESSION['username']}");
            echo "<section><pre>$output</pre>";
        }

        echo "<a href='javascript:history.back()'>Go back</a></section>";
        exit;
    }

    // Create Admin User
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['NEW_USER'])) {
        $newUser = $_POST['NEW_USER'];
        $newPass = $_POST['NEW_PASS'];
        $newRole = $_POST['NEW_ROLE'];
        $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);

        $stmt = $mysqli->prepare("INSERT INTO HIDDEN_users (username, password_hash, role) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $newUser, $hashedPass, $newRole);

        if ($stmt->execute()) {
            $toastMessage = "<div class='toast success'>✅ User '" . htmlspecialchars($newUser) . "' created successfully.</div>";
            logAudit("Admin user '$newUser' created with role '$newRole' by {$_SESSION['username']}");
        } else {
            $errorMsg = $stmt->errno === 1062
                ? "❌ Username '" . htmlspecialchars($newUser) . "' already exists."
                : "❌ Error creating user: " . htmlspecialchars($stmt->error);
            $toastMessage = "<div class='toast error'>$errorMsg</div>";
        }
        $stmt->close();
    }

    // Delete Admin User
    if (isset($_POST['DELETE_ACCOUNT']) && isset($_POST['SELECT_USER'])) {
        $selectedUser = $_POST['SELECT_USER'];

        if ($selectedUser === $_SESSION['username']) {
            $toastMessage = "<div class='toast error'>❌ You cannot delete your own account.</div>";
        } else {
            $stmt = $mysqli->prepare("DELETE FROM HIDDEN_users WHERE username = ?");
            $stmt->bind_param('s', $selectedUser);
            if ($stmt->execute()) {
                $toastMessage = "<div class='toast success'>✅ User '" . htmlspecialchars($selectedUser) . "' deleted successfully.</div>";
                logAudit("Admin user '$selectedUser' deleted by {$_SESSION['username']}");
            } else {
                $toastMessage = "<div class='toast error'>❌ Failed to delete user '" . htmlspecialchars($selectedUser) . "'.</div>";
            }
            $stmt->close();
        }
    }

    // Create Raid
    if (isset($_POST['NEW_TABLE'])) {
        $newTable = $_POST['NEW_TABLE'];
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $newTable)) {
            $toastMessage = "<div class='toast error'>❌ Invalid raid name.</div>";
        } else {
            $stmt = $mysqli->prepare("CREATE TABLE `$newTable` LIKE HIDDEN_Template");
            if ($stmt && $stmt->execute()) {
                $toastMessage = "<div class='toast success'>✅ Raid '$newTable' created successfully.</div>";
                logAudit("Raid '$newTable' created by {$_SESSION['username']}");
                @mkdir("logs/$newTable", 0777, true);
            } else {
                $toastMessage = "<div class='toast error'>❌ Failed to create raid '$newTable'.</div>";
            }
            $stmt?->close();
        }
    }

    // Delete Raid
    if (isset($_POST['DELETE_TABLE'])) {
        $deleteTable = $_POST['DELETE_TABLE'];
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $deleteTable)) {
            $toastMessage = "<div class='toast error'>❌ Invalid table name.</div>";
        } else {
            $timestamp = time();
            $renamedTable = "HIDDEN_DELETED-{$deleteTable}-{$timestamp}";
            $stmt = $mysqli->prepare("RENAME TABLE `$deleteTable` TO `$renamedTable`");
            if ($stmt && $stmt->execute()) {
                $toastMessage = "<div class='toast success'>✅ Raid '$deleteTable' deleted (renamed to '$renamedTable').</div>";
                logAudit("Raid '$deleteTable' deleted (renamed to '$renamedTable') by {$_SESSION['username']}");
                if (is_dir("logs/$deleteTable")) {
                    @rename("logs/$deleteTable", "logs/DELETED_{$deleteTable}_{$timestamp}");
                }
            } else {
                $toastMessage = "<div class='toast error'>❌ Failed to delete raid '$deleteTable'.</div>";
            }
            $stmt?->close();
        }
    }

    // Delete Raider
    if (isset($_POST['DELETE_USER_TABLE']) && isset($_POST['DELETE_USER'])) {
        $table = $_POST['DELETE_USER_TABLE'];
        $user = $_POST['DELETE_USER'];

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            $toastMessage = "<div class='toast error'>❌ Invalid table name.</div>";
        } else {
            $stmt = $mysqli->prepare("DELETE FROM `$table` WHERE Username = ?");
            $stmt->bind_param('s', $user);
            if ($stmt->execute()) {
                $toastMessage = "<div class='toast success'>✅ Raider '$user' removed from '$table'.</div>";
                logAudit("Raider '$user' deleted from '$table' by {$_SESSION['username']}");
            } else {
                $toastMessage = "<div class='toast error'>❌ Failed to delete raider '$user' from '$table'.</div>";
            }
            $stmt->close();
        }
    }

} catch (Exception $e) {
    $toastMessage = "<div class='toast error'>❌ Unexpected error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<?= $toastMessage ?>

<?php if (!empty($toastMessage)): ?>
  <script>
    setTimeout(() => {
      const toast = document.querySelector('.toast');
      if (toast) {
        toast.style.transition = 'opacity 0.5s ease';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 500);
      }
    }, 5000);
  </script>
<?php endif; ?>


<style>
.toast {
  position: fixed;
  bottom: 20px;
  right: 20px;
  background-color: #fff;
  border: 1px solid #ccc;
  border-left: 5px solid;
  padding: 1rem 1.5rem;
  z-index: 1000;
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
  border-radius: 4px;
  font-weight: bold;
}
.toast.success { border-color: #22c55e; color: #16a34a; }
.toast.error { border-color: #ef4444; color: #b91c1c; }
</style>

<section>
  <h3>Manage bonus points</h3>
  <center>
<fieldset>
<legend>Add new soft res data</legend>
    <form action="index.php?id=admin" method="POST" id="softres-form">
      Select a table:
      <select name="DB_TABLE" required>
        <option value=""> </option>
        <?php include 'table-select.php'; ?>
      </select><br><br>
      http://www.softres.it/raid/<input name="URL" required>
      Bonus increment: <input name="INCREMENT" value="2" size="2" type="number" min="1" required>
      <input type="submit" value="Apply" class="styled-button">
    </form>
    <div id="loader" style="display:none; text-align:center; margin-top:20px; font-size:1.2em;">
      <span class="spinner">⏳</span>Loading, please wait...
    </div>
  </center>
</fieldset>
</section>

<?php if ($role === 'admin'): ?>
<section>
  <h3>Manage raids</h3>
  <center>
<fieldset>
<legend>Create new raid</legend>
    <form action="index.php?id=admin" method="POST">
      Enter new table name: <input type="text" name="NEW_TABLE" required>
      <input type="submit" value="Create" class="styled-button">
    </form>
</fieldset>
<fieldset>
<legend>Delete a raid</legend>
    <form id="delete-raider-form" action="index.php?id=admin" method="POST">
      Select a table:
      <select name="DELETE_USER_TABLE" id="DELETE_USER_TABLE" required>
        <option value=""> </option>
        <?php include 'table-select.php'; ?>
      </select><br><br>

      <div id="raider-select-container" style="display:none;">
        Select a raider:
        <select name="DELETE_USER" id="DELETE_USER" size="5" style="width: 200px; overflow-y: auto;" required>
          <!-- Options will be populated dynamically -->
        </select><br><br>
        <button type="submit" class="styled-button" onclick="return confirmDeleteRaider();">Delete Raider</button>
      </div>
    </form>
</fieldset>
  </center>
</section>

<section>
  <h3>Manage Admin Users</h3>
  <center>
    <fieldset>
      <legend>Create Admin User</legend>
      <form action="index.php?id=admin" method="POST">
        <label for="username">Username:</label>
        <input type="text" name="NEW_USER" required>
        <label for="password">Password:</label>
        <input type="password" name="NEW_PASS" required>
        <label for="role">Role:</label>
        <select name="NEW_ROLE" required>
          <option value="lootmaster">Loot Master</option>
          <option value="admin">Admin</option>
        </select><br>
        <input type="submit" value="Create User" class="styled-button">
      </form>
    </fieldset>

    <?php
      $users = $mysqli->query("SELECT username FROM HIDDEN_users ORDER BY username ASC");
    ?>

    <form id="admin-user-form" action="index.php?id=admin" method="POST">
      <fieldset>
        <legend>Modify existing user</legend>
        <select name="SELECT_USER" id="SELECT_USER" size="5" style="width: 200px;" required>
          <?php while ($u = $users->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($u['username']) ?>">
              <?= htmlspecialchars($u['username']) ?>
            </option>
          <?php endwhile; ?>
        </select><br><br>

        <div id="user-buttons">
          <button type="submit" name="DELETE_ACCOUNT" value="1" class="styled-button"
            onclick="return confirmDelete();">Delete User</button>
          <button type="button" onclick="showPasswordChange()" class="styled-button">Change Password</button>
          <button type="button" onclick="showRoleChange()" class="styled-button">Change Role</button>
        </div>

        <div id="password-change" style="display:none;">
          <input type="password" name="NEW_PASSWORD" id="NEW_PASSWORD" placeholder="New Password">
          <button type="submit" name="CHANGE_PASSWORD" value="1" class="styled-button">Update Password</button>
          <button type="button" onclick="hidePasswordChange()" class="styled-button">Cancel</button>
        </div>

        <div id="role-change" style="display:none;">
          <select name="NEW_ROLE_SELECTION" id="NEW_ROLE_SELECTION">
            <option value="">Select new role</option>
            <option value="lootmaster">Loot Master</option>
            <option value="admin">Admin</option>
          </select>
          <button type="submit" name="CHANGE_ROLE" value="1" class="styled-button">Update Role</button>
          <button type="button" onclick="hideRoleChange()" class="styled-button">Cancel</button>
        </div>
      </fieldset>
    </form>
  </center>
</section>
<?php endif; ?>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('softres-form');
    const loader = document.getElementById('loader');
    if (form && loader) {
      form.addEventListener('submit', function () {
        loader.style.display = 'block';
      });
    }
  });

  function showPasswordChange() {
    document.getElementById('user-buttons').style.display = 'none';
    document.getElementById('password-change').style.display = 'block';
  }

  function hidePasswordChange() {
    document.getElementById('password-change').style.display = 'none';
    document.getElementById('user-buttons').style.display = 'block';
  }

  function hideRoleChange() {
    document.getElementById('role-change').style.display = 'none';
    document.getElementById('user-buttons').style.display = 'block';
  }

  function confirmDelete() {
    const select = document.getElementById('SELECT_USER');
    const username = select.value;
    if (!username) {
      alert("Please select a user.");
      return;
    }
    if (confirm(`Are you sure you want to delete ${username}?`)) {
      const form = document.getElementById('admin-user-form');
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'DELETE_ACCOUNT';
      input.value = '1';
      form.appendChild(input);
      form.submit();
    }
  }

  function confirmPasswordChange() {
    const select = document.getElementById('SELECT_USER');
    const username = select.value;
    if (!username) {
      alert("Please select a user.");
      return;
    }
    showPasswordChange();
  }

  function confirmRoleChange() {
    const select = document.getElementById('SELECT_USER');
    const username = select.value;
    if (!username) {
      alert("Please select a user.");
      return;
    }
    showRoleChange();
  }

function showRoleChange() {
  document.getElementById('user-buttons').style.display = 'none';
  document.getElementById('role-change').style.display = 'block';
}
function hideRoleChange() {
  document.getElementById('role-change').style.display = 'none';
  document.getElementById('user-buttons').style.display = 'block';
}

</script>
<script>
  // Log all form submissions
  document.getElementById('admin-user-form').addEventListener('submit', function (e) {
    console.log('Form submitted');
    console.log('Selected User:', document.getElementById('SELECT_USER')?.value);
    console.log('New Password:', document.querySelector('input[name="NEW_PASSWORD"]')?.value);
    console.log('CHANGE_PASSWORD value:', document.querySelector('button[name="CHANGE_PASSWORD"]')?.value);
  });

  // Extra: log click on the Update Password button directly
  document.querySelector('button[name="CHANGE_PASSWORD"]').addEventListener('click', function () {
    console.log('Update Password button clicked');
  });
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const adminForm = document.getElementById('admin-user-form');
  const softresForm = document.getElementById('softres-form');
  const loader = document.getElementById('loader');

  // Show loader for softres form
  if (softresForm && loader) {
    softresForm.addEventListener('submit', function () {
      loader.style.display = 'block';
    });
  }

  if (adminForm) {
    adminForm.addEventListener('submit', function (e) {
      // Validate role change
      const roleSection = document.getElementById('role-change');
      const roleSelect = document.querySelector('select[name="NEW_ROLE_SELECTION"]');
      if (roleSection && roleSection.style.display === 'block') {
        if (!roleSelect.value) {
          e.preventDefault();
          alert('Please select a new role.');
          roleSelect.focus();
          return;
        }
      }

      // Validate password change
      const passSection = document.getElementById('password-change');
      const passInput = document.querySelector('input[name="NEW_PASSWORD"]');
      if (passSection && passSection.style.display === 'block') {
        if (!passInput.value) {
          e.preventDefault();
          alert('Please enter a new password.');
          passInput.focus();
          return;
        }
      }
    });
  }
});
</script>
<script>
document.getElementById('DELETE_USER_TABLE').addEventListener('change', function () {
  const table = this.value;
  const container = document.getElementById('raider-select-container');
  const raiderSelect = document.getElementById('DELETE_USER');

  if (!table) {
    container.style.display = 'none';
    return;
  }

  fetch(`get-raiders.php?table=${encodeURIComponent(table)}`)
    .then(response => response.text())
    .then(data => {
      raiderSelect.innerHTML = data;
      container.style.display = 'block';
    })
    .catch(error => {
      console.error('Error fetching raiders:', error);
      container.style.display = 'none';
    });
});

function confirmDeleteRaider() {
  const table = document.getElementById('DELETE_USER_TABLE').value;
  const raider = document.getElementById('DELETE_USER').value;
  if (!table || !raider) return false;
  return confirm(`Do you really want to delete ${raider} from ${table}?`);
}
</script>
