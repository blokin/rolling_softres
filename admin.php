<?php

$pass = $_POST["pass"];
$user = $_POST["user"];

if($user == "admin"
&& $pass == "password")
{
        include("softres.html");
}
else
{
        echo "Please log in with a valid username and password.";
}
?>
