<?php
  $bonus_table = $_GET['bonus_table'];
  echo "<h2>" . $bonus_table . "</h2>";
  echo "<table width=100%><tr><td>Username</td><td>Item 1</td><td>Bonus 1</td><td>Item 2</td><td>Bonus 2</td>";
  $output = shell_exec("/bin/bash softres.sh bonus-table $bonus_table");
  $arra =  explode("  ", $output);
  foreach($arra as $x){
    echo $x;
  }
  echo "</table>";
?>
