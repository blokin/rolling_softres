<section style="margin-top: 40px;">
  <center>
    <h1>Logs</h1>
  </center>
  <div style="display: flex; flex-direction: column; align-items: center;">
    <?php
      $logBaseDir = __DIR__ . "/logs";
      if (!is_dir($logBaseDir)) {
        echo "<div style='color:red;'>No logs directory found.</div>";
      } else {
        $tables = array_filter(scandir($logBaseDir), function ($entry) use ($logBaseDir) {
          return $entry !== '.' && $entry !== '..' && is_dir("$logBaseDir/$entry") && $entry !== 'error' && $entry !== 'deleted';
        });

        if (empty($tables)) {
          echo "<div style='color:gray;'>No logs found.</div>";
        } else {
          foreach ($tables as $table) {
            $logDir = "$logBaseDir/$table";
            $logs = array_diff(scandir($logDir, SCANDIR_SORT_DESCENDING), ['.', '..']);

            if (!empty($logs)) {
              echo "<div class='log-card'>";
              echo "<h3 onclick=\"toggleLogFiles('$table')\">🗂️ $table</h3>";
              echo "<div class='log-files' id='log-files-$table'>";
              echo "<ul style='list-style-type:none; padding-left:0;'>";

              foreach ($logs as $logFile) {
                $fileId = md5($table . '_' . $logFile);
                $timestamp = date("Y-m-d H:i:s", filemtime("$logDir/$logFile"));
                $path = htmlspecialchars("logs/$table/$logFile");

                echo "<li class='log-entry'>";
                echo "<a onclick=\"loadLogFile('$path', 'log-content-$fileId')\">$logFile</a><br>";
                echo "<small style='color:gray;'>$timestamp</small>";
                echo "<div class='log-content' id='log-content-$fileId'></div>";
                echo "</li>";
              }

              echo "</ul></div></div>";
            }
          }
        }
      }
    ?>
  </div>

  <script>
    function toggleLogFiles(table) {
      const section = document.getElementById('log-files-' + table);
      if (section.style.display === 'block') {
        section.style.display = 'none';
      } else {
        section.style.display = 'block';
      }
    }

    function loadLogFile(filePath, contentId) {
      const contentBox = document.getElementById(contentId);
      if (contentBox.style.display === 'block') {
        contentBox.style.display = 'none';
        return;
      }

      contentBox.innerHTML = 'Loading...';
      fetch(filePath)
        .then(response => {
          if (!response.ok) throw new Error("Failed to load log file");
          return response.text();
        })
        .then(text => {
          contentBox.textContent = text;
          contentBox.style.display = 'block';
        })
        .catch(err => {
          contentBox.innerHTML = "<span style='color:red;'>Error loading file</span>";
        });
    }
  </script>
</section>
