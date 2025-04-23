<?php

$DB_TABLE = $_POST["DB_TABLE"];
$URL = $_POST["URL"];
$NEW_TABLE = $_POST["NEW_TABLE"];
$DELETE_TABLE = $_POST["DELETE_TABLE"];
$DELETE_USER_TABLE = $_POST["DELETE_USER_TABLE"];
$DELETE_USER = $_POST["DELETE_USER"];

include (getcwd() . '/credentials.php');

if(!$NEW_TABLE == "") {
  $existing_check = shell_exec("mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DB_NAME -e \"SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE '$NEW_TABLE';\" ");
  if($existing_check == "") {
    shell_exec("mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DB_NAME -e \"SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'; CREATE TABLE \`$NEW_TABLE\` LIKE \`HIDDEN_Template\`;\" ");
    $success_check = shell_exec("mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DB_NAME -e \"SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE '$NEW_TABLE';\" | awk '{print $2}'");
    if(!$success_check == "") {
      echo "Successfully created " . $NEW_TABLE;
    }
    else {
      echo "Failed to create " . $NEW_TABLE;
    }
  }
  else {
    echo "<b>Error</b>:  $NEW_TABLE already exists!<br>";
  }
  include('softres.html');
}
elseif(!$DELETE_TABLE == "") {
  $existing_check = shell_exec("mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DB_NAME -e \"SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE '$DELETE_TABLE';\" ");
  if(!$existing_check == "") {
    $date = date("Y-m-d-" . time());
    shell_exec("mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DB_NAME -e \"ALTER TABLE \`$DELETE_TABLE\` RENAME TO \`HIDDEN_DELETED-$DELETE_TABLE-$date\`;\" ");
    shell_exec("mv logs/$DELETE_TABLE deleted_logs/$DELETE_TABLE-$DATE");
    $success_check = shell_exec("mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DB_NAME -e \"SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'HIDDEN_DELETED-$DELETE_TABLE-$date';\" ");
    if(!$success_check == "") {
      echo "Successfully deleted " . $DELETE_TABLE;
    }
    else {
      echo "Failed to delete " . $DELETE_TABLE;
    }
  }
  else {
    echo "<b>Error</b>:  $DELETE_TABLE doesn't exist!<br>";
  }
  include('softres.html');
}
elseif(!$DELETE_USER == "") {
  shell_exec("mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DB_NAME -e \"DELETE FROM $DELETE_USER_TABLE WHERE Username = '$DELETE_USER';\" ");
  echo "<center>$DELETE_USER has been deleted from $DELETE_USER_TABLE.<br>";
  echo "<center><a href=javascript:history.back()>Go back</a>";
}
elseif(!$DELETE_USER_TABLE == "") {
  $existing_check = shell_exec("mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DB_NAME -e \"SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE '$DELETE_USER_TABLE';\" ");
  if(!$existing_check == "") {
    include("user_delete.php");
  }
  else {
    echo "<b>Error:</b>  Unable to get users from $DELETE_USER_TABLE!";
  }
}
elseif($URL == "") {
	echo "Please enter the softres URL!";
	include('softres.html');

}
elseif($DB_TABLE == "") {
	echo "Please select a DB Table!";
	include('softres.html');
}
else {
	if (!file_exists('logs/' . $DB_TABLE)) {
		mkdir('logs/' . $DB_TABLE, 0777, true);
	}


ob_implicit_flush(true);
ob_end_flush();

$date = date("Y-m-d-" . time());
$logfile = "./logs/$DB_TABLE/$date.log";
$cmd = "/bin/bash softres.sh " . $DB_TABLE . " 5 " . $URL . "| tee -a " . $logfile;

$descriptorspec = array(
   0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
   1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
   2 => array("pipe", "w")    // stderr is a pipe that the child will write to
);


$process = proc_open($cmd, $descriptorspec, $pipes, realpath('./'), array());

echo "<pre>";
if (is_resource($process)) {

    while ($s = fgets($pipes[1])) {
        print $s;

    }
}

// If strict types are enabled i.e. declare(strict_types=1);
$file = file_get_contents($logfile, true);
// Otherwise
$file = file_get_contents($logfile, FILE_USE_INCLUDE_PATH);

echo "</pre><br><br><a href=javascript:history.back()>Go back</a>";

}
?>
