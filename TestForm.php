<?php
//Copyright Robert Dobrose 2011

   //$myscript = $_SERVER['SCRIPT_FILENAME'];
   //$mydir = dirname($myscript);

$form_name = "Zeus Grid";

require("template_top.inc"); 
  

if ($_GET['error'] == "1") {
   $error_code = 1;  //this means that there's been an error and we need to notify the customer
}

echo "<br>Server: <br>";
print_r($_SERVER);
echo "<br>Env: <br>";
print_r($_ENV);
echo "<br>Session: <br>";
print_r($_SESSION);
echo "<br>Files: <br>";
print_r($_FILES);
echo "<br>Request: <br>";
print_r($_REQUEST);

?>


Content goes here<br>
This will be some very long content to test the margins and width of the display area 
by stretching the text out using a very verbose sequence of words going 
to the edge and beyond the limits of what can be displayed in one line.<br>


<?
//(c) Robert Dobrose 2011
require("template_bottom.inc"); 
   //require($_SERVER['DOCUMENT_ROOT']."/Lesson12/template_bottom.inc"); 
?>

