<?php
/*********mysql function to check new order******************/
function get_new_order()
{
$con=mysql_connect(HOST, USERNAME, PASSWORD);
mysql_select_db(DATABASE,  $con);
$sql="select  OrderId from customer_order where order_Status='0' "; //0 for new order
$query=mysql_query($sql,$con);
if(mysql_num_rows(  $query)>0)
{
return true;
}
else return  false;
}
/*************************************/
/********Socket Server*********************/
set_time_limit (0);
// Set the ip and port we will listen on
$address = '50.57.99.73';
$port = 8468;
// Create a TCP Stream socket
$sock = socket_create(AF_INET, SOCK_STREAM, 0); // 0 for  SQL_TCP
// Bind the socket to an address/port
socket_bind($sock, $address, $port) or die('Could not bind to address');  //0 for localhost
// Start listening for connections
socket_listen($sock);
//loop and listen
while (true) {
/* Accept incoming  requests and handle them as child processes */
$client =  socket_accept($sock);
// Read the input  from the client – 1024000 bytes
$input =  socket_read($client, 1024000);
// Strip all white  spaces from input
$output =  ereg_replace("[ \t\n\r]","",$input)."\0";
$message=explode('=',$output);
/*
if(count($message)==2)
{
if(get_new_order()) $response='NEW:1';
else  $response='NEW:0';
}
else $response='NEW:0';
*/
// Display output  back to client
socket_write($client, $input);
socket_close($client);
}
// Close the master sockets
socket_close($sock);
?>