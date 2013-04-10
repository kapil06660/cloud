<?php
//Copyright Robert Dobrose 2011

ob_start();  //Buffer output to prevent sending messages or responses
error_reporting(E_ALL);
ini_set('display_errors', '1');
$my_log = "ZT__new_log.txt";

$my_array = $_GET;

$translateRT = array(
				"SETPOINT" => "cool/setpoint",
				"ALERTCLEAR" => "alertclr",
				"CONTMODE" => "controlmode",
				"FANMODE" => "fanmode"
				);
				
$translateTR = array(
				"itemp" => "TEMP",
				"thermstatus" => "THERMFLAGS",
				"astatus" => "ALERTFLAGS",
				"setpoint" => "SETPOINT"
				);
				
$first_out = 0;
$packet_out = "";
$thermSN = "";

$logdata = "================== Got a packet.\n";
file_put_contents($my_log,$logdata,FILE_APPEND);

$logdata = "Time: ".date('r')."\n";
file_put_contents($my_log,$logdata,FILE_APPEND);

if(array_key_exists ('ID', $my_array))
{
	//$arr_out = print_r($my_array, true);
	//$logdata = "Request: $arr_out\n";
	//file_put_contents($my_log,$logdata,FILE_APPEND);
	//ID key exists.  Get value and use last 7 chars (3.5 bytes) as Remote SN.
	//(c) Robert Dobrose 2011
	$remID = $my_array['ID'];
	$logdata = "Incoming ID: $remID\n";
	//file_put_contents($my_log,$logdata,FILE_APPEND);
	$rempackID = pack("h*", strrev(substr($remID, -7)));
	$logdata = "pack remID: $rempackID \n";
	//file_put_contents($my_log,$logdata,FILE_APPEND);
	$remArray = unpack("Vrsn",pack("h*", strrev(substr($remID, -7))));
	$arr_out = print_r($remArray, true);
	//$logdata = "Array: $arr_out\n";
	//file_put_contents($my_log,$logdata,FILE_APPEND);
	$remSN = $remArray['rsn'];
	$logdata = "++++Device SN: $remSN\n";
	file_put_contents($my_log,$logdata,FILE_APPEND);
}
else 
{
	//No ID value was included with request
	$logdata = "   No  ID included!!!\n";
	file_put_contents($my_log,$logdata,FILE_APPEND);
	$arr_out = print_r($my_array, true);
	$logdata = "Request: $arr_out\n";
	file_put_contents($my_log,$logdata,FILE_APPEND);
	//Should error message and die here or flag error to avoid SQL processing
}

if(array_key_exists ('TGT', $my_array))
{
	$thermSN = $my_array['TGT'];
}
else 
{
	//No TGT value was included with request
	$logdata = "   No  TGT included!!!\n";
	file_put_contents($my_log,$logdata,FILE_APPEND);
}

$con = mysql_connect("localhost","root","HXtmmPyuWI1l6pDE3l6V");
if (!$con)
{
	file_put_contents($my_log,"SQL Connect Failed: ".mysql_error()."\n\n",FILE_APPEND);
}
else 
{
	//file_put_contents($my_log,"###Connected to Database\n",FILE_APPEND);

	mysql_select_db("Home_db", $con);
	//file_put_contents($my_log,"Selected Home Database\n",FILE_APPEND);

	if ($thermSN === "")
	{
		$logdata = "   No  thermSN found!!!\n";
		file_put_contents($my_log,$logdata,FILE_APPEND);
	}
	else
	{
		//We have a connected thermostat.  
		$logdata = "   Process thermSN $thermSN to Remote.\n";
		file_put_contents($my_log,$logdata,FILE_APPEND);
		

		//First find all thermostat variables that have changed and transfer to remote variables
		//Transfer only variables that correspond.  
	    $sql = "SELECT Object_Name, Object_Value "
	    	."FROM Product_Value_Objects "
			."WHERE (Value_Owner_Product_SN='$thermSN' and Owner_Update=1)";
	
		$result = mysql_query($sql,$con);
		if ($result)
		{
			//file_put_contents($my_log,"SQL Read Succeeded: ".mysql_error()."\n\n",FILE_APPEND);
			while ($row = mysql_fetch_object($result)) {
		  		$arr_out = print_r($row, true);
				$logdata = "Line: $arr_out\n";
				file_put_contents($my_log,$logdata,FILE_APPEND);
				
				//For each Therm Var with changes, find coresponding Remote var and update
				if(array_key_exists ($row->Object_Name, $translateTR))
				{
					//Key exists in T-R translation table, so transfer data
					$remoteKey = $translateTR[$row->Object_Name];
					//Update Remote var with new value and set external update flag
					$sql = "UPDATE Product_Value_Objects "
						."SET Object_Value='$row->Object_Value',External_Update=1 "
						."WHERE (Value_Owner_Product_SN='$remSN' and Object_Name='$remoteKey')";
					if (mysql_query($sql,$con))
					{
				  		//file_put_contents($my_log,"SQL Remote Var Update Succeeded: ".mysql_error()."\n\n",FILE_APPEND);
					}
					else
				  	{
				  		//Trap for format errors in data or for a failed SQL insert.
					  	//On any error, dump the raw data and headers to a garbage file.
				  		file_put_contents($my_log,"SQL Remote Var Update Failed: ".mysql_error()."\n\n",FILE_APPEND);
						file_put_contents($my_log,"SQL Request:[".$sql."]\n",FILE_APPEND);
				  	}
						
				  	//Clear owner update flag on therm vars that were transferred
					$sql = "UPDATE Product_Value_Objects "
						."SET Owner_Update=0 "
						."WHERE (Value_Owner_Product_SN='$thermSN' and Object_Name='$row->Object_Name')";
					if (mysql_query($sql,$con))
					{
				  		//file_put_contents($my_log,"SQL Therm Flag Clear Succeeded: ".mysql_error()."\n\n",FILE_APPEND);
					}
					else
				  	{
				  		//Trap for format errors in data or for a failed SQL insert.
					  	//On any error, dump the raw data and headers to a garbage file.
				  		file_put_contents($my_log,"SQL Thermostat Owner Flag Clear Failed: ".mysql_error()."\n\n",FILE_APPEND);
						file_put_contents($my_log,"SQL Request:[".$sql."]\n",FILE_APPEND);
				  	}
				  	
					$logdata = "    Therm Key: ".$row->Object_Name." ["
							.$row->Object_Value."] to Remote ".$remoteKey.".\n";
					file_put_contents($my_log,$logdata,FILE_APPEND);
				}
			}
		}
		else
	  	{
	  		//Trap for format errors in data or for a failed SQL Read.
		  	//On any error, dump the raw data and headers to a garbage file.
	  		file_put_contents($my_log,"SQL Thermostat Owner Update Read Failed: ".mysql_error()."\n\n",FILE_APPEND);
			file_put_contents($my_log,"SQL Request:[".$sql."]\n",FILE_APPEND);
	  	}
	}
  	
	while (list($key, $val) = each($my_array)) 
	{
		if ($key == 'ID')
		{
			//$logdata = "  Key: $key  Value: $remSN\n";
			$logdata = "  Key: $key  Value: $val\n";
			file_put_contents($my_log,$logdata,FILE_APPEND);
		}
		elseif ($key == 'TGT')
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
				
				$sql = "UPDATE Product_Value_Objects "
					."SET Object_Value='$val',Owner_Update=1 "
					."WHERE (Value_Owner_Product_SN='$remSN' and Object_Name='$key')";

				if (mysql_query($sql,$con))
				{
			  		//file_put_contents($my_log,"SQL Update Succeeded: ".mysql_error()."\n\n",FILE_APPEND);
				}
				else
			  	{
			  		//Trap for format errors in data or for a failed SQL insert.
				  	//On any error, dump the raw data and headers to a garbage file.
			  		file_put_contents($my_log,"SQL Write Failed: ".mysql_error()."\n\n",FILE_APPEND);
					file_put_contents($my_log,"SQL Request:[".$sql."]\n",FILE_APPEND);
			  	}
			}
			else 
			{
				//key is requesting value
				//$logdata = "  Need to look up value for $key.\n";
				//file_put_contents($my_log,$logdata,FILE_APPEND);
				//$temp_val = 42;
				//(c) Robert Dobrose 2011
				$sql = "UPDATE Product_Value_Objects "
					."SET External_Update='1' "
					."WHERE (Value_Owner_Product_SN='$remSN' and Object_Name='$key')";

				if (mysql_query($sql,$con))
				{
			  		//file_put_contents($my_log,"SQL Update Succeeded: ".mysql_error()."\n\n",FILE_APPEND);
				}
				else
			  	{
			  		//Trap for format errors in data or for a failed SQL insert.
				  	//On any error, dump the raw data and headers to a garbage file.
			  		file_put_contents($my_log,"SQL Write Failed: ".mysql_error()."\n\n",FILE_APPEND);
					file_put_contents($my_log,"SQL Request:[".$sql."]\n",FILE_APPEND);
			  	}
			}
		}
	}
		    
    $sql = "SELECT Object_Name, Object_Value "
    	."FROM Product_Value_Objects "
		."WHERE (Value_Owner_Product_SN='$remSN' and External_Update=1)";

	$result = mysql_query($sql,$con);
	if ($result)
	{
  		//file_put_contents($my_log,"SQL Read Succeeded: ".mysql_error()."\n\n",FILE_APPEND);
		while ($row = mysql_fetch_object($result)) {
	  		//$arr_out = print_r($row, true);
			//$logdata = "Line: $arr_out\n";
			//file_put_contents($my_log,$logdata,FILE_APPEND);
			if($first_out > 0)
			{
				$packet_out .= "&";
			}
			$packet_out .= $row->Object_Name."=".$row->Object_Value;
			$first_out = 1;
			$logdata = "    Current value of Key: ".$row->Object_Name." is: ".$row->Object_Value.".\n";
			file_put_contents($my_log,$logdata,FILE_APPEND);
		}
		//Sent all keys with External_Update set.  So clear External_Update flags.
		$sql = "UPDATE Product_Value_Objects "
			."SET External_Update=0 "
			."WHERE (Value_Owner_Product_SN='$remSN' and External_Update=1)";
		if (mysql_query($sql,$con))
		{
	  		//file_put_contents($my_log,"SQL Flag Clear Succeeded: ".mysql_error()."\n\n",FILE_APPEND);
		}
		else
	  	{
	  		//Trap for format errors in data or for a failed SQL insert.
		  	//On any error, dump the raw data and headers to a garbage file.
	  		file_put_contents($my_log,"SQL Flag Clear Failed: ".mysql_error()."\n\n",FILE_APPEND);
			file_put_contents($my_log,"SQL Request:[".$sql."]\n",FILE_APPEND);
	  	}
	}
	else
  	{
  		//Trap for format errors in data or for a failed SQL Read.
	  	//On any error, dump the raw data and headers to a garbage file.
  		file_put_contents($my_log,"SQL External Update Read Failed: ".mysql_error()."\n\n",FILE_APPEND);
		file_put_contents($my_log,"SQL Request:[".$sql."]\n",FILE_APPEND);
  	}
	
	//Update Thermostat vars from value of any Remote vars that have changed  
	if ($thermSN === "")
	{
		$logdata = "   No  thermSN found!!!\n";
		file_put_contents($my_log,$logdata,FILE_APPEND);
	}
	else
  		{
		//We have a connected thermostat.  
		$logdata = "   Process Remote to thermSN $thermSN.\n";
		file_put_contents($my_log,$logdata,FILE_APPEND);
		
  		//First find all remote variables that have changed and transfer to thermostat variables
		//Transfer only variables that correspond.  
	    $sql = "SELECT Object_Name, Object_Value "
	    	."FROM Product_Value_Objects "
			."WHERE (Value_Owner_Product_SN='$remSN' and Owner_Update=1)";
	
		$result = mysql_query($sql,$con);
		if ($result)
		{
	  		//file_put_contents($my_log,"SQL Read Succeeded: ".mysql_error()."\n\n",FILE_APPEND);
			while ($row = mysql_fetch_object($result)) {
		  		$arr_out = print_r($row, true);
				$logdata = "Line: $arr_out\n";
				file_put_contents($my_log,$logdata,FILE_APPEND);
				
				//For each Remote Var with changes, find coresponding Therm var and update
				if(array_key_exists ($row->Object_Name, $translateRT))
				{
					//Key exists in T-R translation table, so transfer data
					$thermKey = $translateRT[$row->Object_Name];
/*	*/			
					//Update Remote var with new value and set external update flag
					$sql = "UPDATE Product_Value_Objects "
						."SET Object_Value='$row->Object_Value',External_Update=1 "
						."WHERE (Value_Owner_Product_SN='$thermSN' and Object_Name='$thermKey')";
					if (mysql_query($sql,$con))
					{
				  		//file_put_contents($my_log,"SQL Therm Var Update Succeeded: ".mysql_error()."\n\n",FILE_APPEND);
					}
					else
				  	{
				  		//Trap for format errors in data or for a failed SQL insert.
					  	//On any error, dump the raw data and headers to a garbage file.
				  		file_put_contents($my_log,"SQL Therm Var Update Failed: ".mysql_error()."\n\n",FILE_APPEND);
						file_put_contents($my_log,"SQL Request:[".$sql."]\n",FILE_APPEND);
				  	}
						
					//Clear owner update flag on remote vars that were transferred
					$sql = "UPDATE Product_Value_Objects "
						."SET Owner_Update=0 "
						."WHERE (Value_Owner_Product_SN='$remSN' and Object_Name='$row->Object_Name')";
					if (mysql_query($sql,$con))
					{
				  		//file_put_contents($my_log,"SQL Remote Flag Clear Succeeded: ".mysql_error()."\n\n",FILE_APPEND);
					}
					else
				  	{
				  		//Trap for format errors in data or for a failed SQL insert.
					  	//On any error, dump the raw data and headers to a garbage file.
				  		file_put_contents($my_log,"SQL Remote Owner Flag Clear Failed: ".mysql_error()."\n\n",FILE_APPEND);
						file_put_contents($my_log,"SQL Request:[".$sql."]\n",FILE_APPEND);
				  	}
				  	
/*  */				
					$logdata = "    Remote Key: ".$row->Object_Name." ["
							.$row->Object_Value."] to Therm ".$thermKey.".\n";
					file_put_contents($my_log,$logdata,FILE_APPEND);
				}
			}
		}
		else
	  	{
	  		//Trap for format errors in data or for a failed SQL Read.
		  	//On any error, dump the raw data and headers to a garbage file.
	  		file_put_contents($my_log,"SQL Remote Owner Update Read Failed: ".mysql_error()."\n\n",FILE_APPEND);
			file_put_contents($my_log,"SQL Request:[".$sql."]\n",FILE_APPEND);
	  	}
	}

  	mysql_close($con);
}

//Set outgoing remote packet data and send packet	
//(c) Robert Dobrose 2011
$len = strlen($packet_out);

if(ob_get_length() > 0)
{
	//Clean up and log any console error messages
	file_put_contents($my_log,"PHP Script Messages: [".ob_get_contents()."]\n\n",FILE_APPEND);
}
ob_end_clean ();

header('HTTP/1.1 200 OK');
header('Content-Length: $len');
header('Content-Type: text/plain');
echo $packet_out;
  
?>
