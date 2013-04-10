<?php

ob_start();  //Buffer output to prevent sending messages or responses
error_reporting(E_ALL);
ini_set('display_errors', '1');

$my_log = "vgTest_log.txt";

$my_array = $_GET;

//print_r($my_array);

//echo "<br><br>";
$logdata = "====Got a packet.\n";
file_put_contents($my_log,$logdata,FILE_APPEND);

$input_str = print_r($my_array, true);
$logdata = "Input = $input_str \n";
file_put_contents($my_log,$logdata,FILE_APPEND);


$first_out = 0;
$packet_out = "";


	while (list($key, $val) = each($my_array)) 
	{
		if (($key == 'ID') || ($key == 'TGT'))
		{
			$logdata = "  Key: $key  Value: $val\n";
			file_put_contents($my_log,$logdata,FILE_APPEND);
		}
		else
		{
			if(isset($val) && !($val === ""))
			{
				//key has a new value.
				$logdata = "  Key: $key Gets new Value: $val\n";
				file_put_contents($my_log,$logdata,FILE_APPEND);
				$temp_val = $val;
			}
			else 
			{
				//key is requesting value
				$logdata = "  Need to look up value for $key.\n";
				file_put_contents($my_log,$logdata,FILE_APPEND);
				$temp_val = 42;
			}
			$logdata = "    Current value of Key: $key is: $temp_val.\n";
			file_put_contents($my_log,$logdata,FILE_APPEND);
		    
				if($first_out > 0)
				{
					$packet_out .= "&";
				}
				$packet_out .= "$key=$temp_val";
				$first_out = 1;
		}
	}
	

$len = strlen($packet_out);
  
file_put_contents($my_log,"PHP Script Error Messages: [".ob_get_contents()."]\n\n",FILE_APPEND);
ob_end_clean ();

header('HTTP/1.1 200 OK');
header('Content-Length: $len');
header('Content-Type: text/plain');
echo $packet_out;
  
?>
