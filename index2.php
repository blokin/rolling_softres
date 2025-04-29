<html>
  <head>
    <title>Rolling SoftRes - By Blokin</title>
    <link rel="stylesheet" href="style.css">
  </head>
  <body>
    <center>
      <table width=1000>
        <tr>
          <td>
<pre>______      _ _ _               _____        __ _  ______
| ___ \    | | (_)             /  ___|      / _| | | ___ \
| |_/ /___ | | |_ _ __   __ _  \ `--.  ___ | |_| |_| |_/ /___  ___
|    // _ \| | | | '_ \ / _` |  `--. \/ _ \|  _| __|    // _ \/ __|
| |\ \ (_) | | | | | | | (_| | /\__/ / (_) | | | |_| |\ \  __/\__ \
|_| \_\___/|_|_|_|_| |_|\__, | \____/ \___/|_|  \__\_| \_\___||___/
                         __/ |                By Blokin
                        |___/</pre>
          </td>
          <td align="right">
            <form method="POST" action="index.php?id=admin">
              User <input type="text" name="user"></input><br/>
              Pass <input type="password" name="pass"></input><br/>
              <input type="submit" name="submit" value="Go"></input>
            </form>
          </td>
        </tr>
        <tr>
          <td colspan=2>
            <table width=100%>
              <tr>
                <td valign=top style="width: 200px; border-right: 1px solid black;">
                  <h4>Current Bonuses</h4>
                    <?php
                      $output = shell_exec("/bin/bash softres.sh list-tables");
                      $noquotes = str_replace("\"", "", $output);
                      $arr =  explode(",", $noquotes);
                      foreach($arr as $v){
                        echo "<a href=index.php?id=bonus&bonus_table=$v>$v</a><br>";
                      }
                    ?>
                  <h4>Logs</h4>
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
                </td>
                <td valign=top>
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
