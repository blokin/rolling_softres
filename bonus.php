<h2>Current Bonuses:</h2>
<?php
  $output = shell_exec("/bin/bash softres.sh list-tables");
  $noquotes = str_replace("\"", "", $output);
  $arr =  explode(",", $noquotes);
  foreach($arr as $v){
    echo "<ul id=\"myUL\"><li><span class=\"caret\">$v</span><ul class=\"nested\">";
    $output = shell_exec("/bin/bash softres.sh list-raiders $v");
    $arra =  explode("  ", $output);
    foreach($arra as $x){
      echo $x;
    }
    echo "</ul></li></ul>";
  }
?>
</ul></li></ul>
