<?php
//Copyright Robert Dobrose 2011
ob_start();  //Buffer output to prevent sending messages or responses

error_reporting(E_ALL);
ini_set('display_errors', '1');
$my_log = "zgRPC1log.txt";
$outt = "";

//lets get the raw input
$raw = file_get_contents("php://input");

$logdata = "================== Got a packet.\n";
file_put_contents($my_log,$logdata,FILE_APPEND);




//if(strlen($outt) > 0)
//{
  $outt .= "\n";
//}
$len = strlen($outt);

file_put_contents($my_log,"RPC Process End -----------\n\n",FILE_APPEND);

ob_end_clean ();

//(c) Robert Dobrose 2011
header('HTTP/1.1 200 OK');
header('Content-Length: $len');
header('Content-Type: text/plain');
echo $outt;


?>
