<html>
  <head>
    <title>Rolling SoftRes - By Blokin</title>
    <link rel="stylesheet" href="style.css">
  </head>
  <body>
    <center>
      <table width=1250>
        <tr>
          <td>
<pre>______      _ _ _               _____        __ _  ______
| ___ \    | | (_)             /  ___|      / _| | | ___ \
| |_/ /___ | | |_ _ __   __ _  \ `--.  ___ | |_| |_| |_/ /___  ___
|    // _ \| | | | '_ \ / _` |  `--. \/ _ \|  _| __|    // _ \/ __|
| |\ \ (_) | | | | | | | (_| | /\__/ / (_) | | | |_| |\ \  __/\__ \
|_| \_\___/|_|_|_|_| |_|\__, | \____/ \___/|_|  \__\_| \_\___||___/
                         __/ |        By <a href="https://youtu.be/Qm9bUYVx8aI" target="new">High-Warlord Bearijuana</a>
                        |___/</pre>
          </td>
        </tr>
        <tr>
          <td colspan=2>
            <table class=content width=100%>
              <tr>
                <td>
                  <h4 class="nav_header">Navigation</h4>
                    <a href="index.php">Rules</a><br>
                  <h4 class="nav_header">Current Bonuses</h4>
                    <?php
                      $output = shell_exec("/bin/bash softres.sh list-tables");
                      $noquotes = str_replace("\"", "", $output);
                      $arr =  explode(",", $noquotes);
                      foreach($arr as $v){
                        echo "<a href=index.php?id=bonus&bonus_table=$v>$v</a><br>";
                      }
                    ?>
                  <h4 class="nav_header">Logs</h4>
                  <?php
                   $output = shell_exec("/bin/bash softres.sh list-logs");
                   $arr =  explode(",", $output);
                   foreach($arr as $v){
                     echo "<h5>$v</h5><ul class=nested>";
                     $output = shell_exec("/bin/bash softres.sh list-logs $v");
                     $arra =  explode(",", $output);
                     foreach($arra as $x){
                       echo "<li><a href=\"index.php?id=logview&log_dir=" . $v . "&logfile=" . $x . "\">" . $x . "</a></li>";
                     }
                     echo "</ul>";
                   }
                  ?>
                  <h4 class="nav_header">Admin Login</h4>
                    <form method="POST" action="index.php?id=admin">
                      <input type="text" name="user" value="Username"></input><br/>
                      <input type="password" name="pass" value="Password"></input><br/>
                      <input type="submit" name="submit" value="Log In"></input>
                    </form>

                </td>
                <td>
                  <?php
                    $id = $_GET['id'];
                      switch($id) {
                        default:
                          include('home.php');
                          break;
                          case "logview":include('logview.php');
                          break;
                          case "current":include('bonus.php');
                          break;
                          case "admin":include('admin.php');
                          break;
                          case "softres":include('softres.php');
                          break;
                          case "bonus":include('bonus_list.php');
                    }
                  ?>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </center>
  </body>
</html>
