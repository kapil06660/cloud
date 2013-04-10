<?php
// don't timeout
set_time_limit (0);

// set some variables
$host = "50.57.99.73";
$port = 8469;

// create socket
$socket = socket_create(AF_INET, SOCK_STREAM, 0) or die("Could not create socket\n");

// bind socket to port
$result = socket_bind($socket, $host, $port) or die("Could not bind to socket\n");

// start listening for connections
$result = socket_listen($socket, 3) or die("Could not set up socket listener\n");

//echo "Waiting for connections...\n";

// accept incoming connections
// spawn another socket to handle communication

$spawn = socket_accept($socket) or die("Could not accept incoming connection\n");

//echo "Received connection request\n";

// write a welcome message to the client
$welcome = "Thanks";
socket_write($spawn, $welcome, strlen ($welcome)) or die("Could not send connect string\n");
// keep looping and looking for client input
do
{
// read client input
//$input = @socket_read($spawn, 1024, 1) or die("Could not read input\n");
$input = @socket_read($spawn, 1024, 1);
if (trim($input) != "")
{
//echo "Received input: $input\n";
// if client requests session end
if (trim($input) == "END")
{
// close the child socket
// break out of loop
socket_close($spawn);
break;
}
// otherwise...
else
{
// reverse client input and send back
$output = strrev($input) . "\n";
socket_write($spawn, $input, strlen ($input)) or die("Could not write output\n");
//echo "Sent output: " . trim($input) . "\n";
}
}
} while (true);
// close primary socket
//socket_close($socket);
//echo "Socket terminated\n";
?>