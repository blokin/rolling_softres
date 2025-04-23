    <?php include("softres.html"); ?>
    <center>
      <form action="index.php?id=softres" method="POST">
        Select a user to delete from <b><?php echo $DELETE_USER_TABLE; ?>: </b>:
        <select name="DELETE_USER">
          <option value=""> </option>
          <?php
            $output = shell_exec("/bin/bash softres.sh list-raiders $DELETE_USER_TABLE");
            $arr =  explode("\n", $output);
            foreach($arr as $v){
              echo "<option value=" . $v . ">" . "$v" . "</option>";
            }
          ?>
        </select><br><br>
        <input type="hidden" name="DELETE_USER_TABLE" value=<?php echo $DELETE_USER_TABLE; ?>>
        <input type=submit>
      </form>
    </center>
