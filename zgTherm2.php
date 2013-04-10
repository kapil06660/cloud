<?php
//Copyright Robert Dobrose 2011
ob_start();  //Buffer output to prevent sending messages or responses

error_reporting(E_ALL);
ini_set('display_errors', '1');
$my_log = "zgTherm2log_new.txt";
$outt = "";
$TempDataFound = false;
$zDebug = 2;


//lets get the raw input
$raw = file_get_contents("php://input");

$logdata = "================== Got a packet.\n";
file_put_contents($my_log,$logdata,FILE_APPEND);

if(strlen($raw) < 21)
{
	$pkt_dump = print_r($_REQUEST, true);
	$pkt_dump .= print_r($_SERVER, true);
	$logdata = "***** Bad packet [$pkt_dump]\n";
	file_put_contents($my_log,$logdata,FILE_APPEND);
	die();
}

//raw to hex
$hexout = bin2hex($raw);

//lets do data processing
//remove first 5 chars from data - 64 61 74 61 3D 
$rawstrip =substr($raw,5,strlen($raw)-5);

$rawheader = substr($rawstrip, 0, 16);
$rawheaddata = substr($rawstrip, 16, 14);
$rawwoheader = substr($rawstrip, 30);

$hexheader = bin2hex($rawheader);

//split header into parts

//$proheader = unpack('Vtimestamp/Vversion/Vserialno/vinterval/Cnumrec/Cpad1/fci/fitemp/fihumid/Castatus/Cthermstatus',$rawheader); 
$proheader = unpack('Vtimestamp/Vversion/Vserialno/vinterval/Cnumrec/Cpad1',$rawheader); 
$cur_data_array = unpack('fci/fitemp/fihumid/Castatus/Cthermstatus',$rawheaddata); 
$TempDataFound = true;

$timestamp = $proheader['timestamp'];

//version and serial = 4 bytes - unsigned long - V
$version = $proheader['version'];
$serial = $proheader['serialno'];

//Interval - 2 bytes
$interval = $proheader['interval'];

//Number of records - 1 byte
$numrec = $proheader['numrec'];

//current Comfort Index value
$ciVal = $cur_data_array['ci'];

//current inside temperature value
$iTemp = $cur_data_array['itemp'];

//current inside humidity value
$iHumid = $cur_data_array['ihumid'];

//thermostat status flags - 1 byte
$tstatus = $cur_data_array['thermstatus'];

//alerts status flags - 1 byte
$astatus = $cur_data_array['astatus'];

//$logdata = "================== Got a packet.\n";
$logdata = "Time: ".date('r')
			."\nFrom: ".$serial
			."\n";
file_put_contents($my_log,$logdata,FILE_APPEND);

$nextnull = strpos($rawwoheader, 0);
$formatstr = substr($rawwoheader, 0, $nextnull + 1);
$rawdata = substr($rawwoheader, $nextnull + 1);
$rawdatalen = strlen($rawdata);
$hexdata = bin2hex($rawdata);
$testlen = $numrec * 10;  //Calculate expected data length
$samp_rec = "";


if($testlen != $rawdatalen)
{
	$logdata = "*********** Bad Data Packet Length: expected $testlen, got $rawdatalen\n";
	file_put_contents($my_log,$logdata,FILE_APPEND);
}
else 
{
	//*************************
	//Create CSV format of temperature records for Sample_Data_Record field
	for($rec=0;$rec < $numrec;$rec++)
	{
		$this_rec = substr($rawdata, ($rec*10), 10);
		$this_rec_array = unpack("Ctype/Cthermstatus/vitemp/vihumid/votemp/vohumid", $this_rec);
		if($this_rec_array['type'] == 1)
		{
			$samp_rec .= vsprintf("%u, %u, %d, %d, %d, %d\n", $this_rec_array);
		}
	}
	if($zDebug > 1)
	{
		$logdata = "Sample Table: [".$samp_rec."]\n";
		file_put_contents($my_log,$logdata,FILE_APPEND);
	}
}


if($zDebug > 0)
{
	$logdata = "====\n#Time: ".date('r')
				."\n#User-Agent: ".$_SERVER['HTTP_USER_AGENT']
				."\n#Raw:\n".$hexout
				."\n#Custom Header: ".$hexheader
				."\nHeader-Timestamp: ".$timestamp
				."\nHeader-Version: ".$version
				."\nHeader-Serial: ".$serial
				."\nHeader-Interval: ".$interval
				."\nHeader-NumRec: ".$numrec
				."\nHeader-ThermFlags: ".$tstatus
				."\nHeader-AlertFlags: ".$astatus
				."\nFormat:  ".addslashes($formatstr)
				."\nRaw data:\n".$hexdata."\n";
	file_put_contents($my_log,$logdata,FILE_APPEND);
}

//insert qry

$con = mysql_connect("localhost","root","HXtmmPyuWI1l6pDE3l6V");
if (!$con)
  {
  	file_put_contents($my_log,"SQL Connect Failed: ".mysql_error()."\n\n",FILE_APPEND);
  }
  else 
  {
//	file_put_contents($my_log,"###Connected to Database\n",FILE_APPEND);

	mysql_select_db("Home_db", $con);
//	file_put_contents($my_log,"Selected Home Database\n",FILE_APPEND);

	$dbsamp_rec = mysql_real_escape_string($samp_rec);
	if($zDebug > 1)
	{
		$logdata = "DB Convert: [".$dbsamp_rec."]\n";
		file_put_contents($my_log,$logdata,FILE_APPEND);
	}

	$sql = "INSERT INTO Product_Raw_Data_Sample ("
		."Customer_Zeus_Product_Product_SN, Sample_Timestamp, Sample_Version, "
		."Sample_Header_Format, Data_Record_Count, Sample_Data_Record, Sample_Length, Sample_Data_Binary)" 
		."VALUES ($serial, NOW(), $version, '"
		.mysql_real_escape_string($formatstr)
		."', $numrec, '$dbsamp_rec', $rawdatalen, '"
		.mysql_real_escape_string($rawdata)."')";

	if (!mysql_query($sql,$con))
  	{
  		//Trap for format errors in data or for a failed SQL insert.
	  	//On any error, dump the raw data and headers to a garbage file.
  		file_put_contents($my_log,"SQL Write Failed: ".mysql_error()."\n\n",FILE_APPEND);
		file_put_contents($my_log,"SQL Request:[".$sql."]\n",FILE_APPEND);
  	}

/* */  	
//Copyright Robert Dobrose 2011
  	//Update variables in cloud connected to this device if they exist
  	if($TempDataFound)
  	{
	  	reset($cur_data_array);
		while (list($key, $val) = each($cur_data_array)) 
		{
			if($key != 'type')
			{
				if(($key == 'thermstatus') || ($key == 'astatus'))
				{
					$dbval = sprintf("%u", $val);
				}
				else 
				{
					//$dbval = sprintf("%.2f", ($val / 100));
					$dbval = sprintf("%.2f", $val);
				}
		  		$sql = "UPDATE Product_Value_Objects "
					."SET Object_Value='$dbval',Owner_Update=1 "
					."WHERE (Value_Owner_Product_SN='$serial' and Object_Name='$key')";
			
				if (mysql_query($sql,$con))
				{
					if($zDebug > 3)
					{
						file_put_contents($my_log,"SQL Update Succeeded for $key: ".mysql_error()."\n\n",FILE_APPEND);
					}
				}
				else
			  	{
			  		//Trap for format errors in data or for a failed SQL insert.
				  	//On any error, dump the raw data and headers to a garbage file.
			  		file_put_contents($my_log,"SQL Write Failed for $key: ".mysql_error()."\n\n",FILE_APPEND);
					file_put_contents($my_log,"SQL Request:[".$sql."]\n",FILE_APPEND);
			  	}
			}
		}
		// update timestamp for the record if all data is received properly - its used by mobile app
		//if($testlen = $rawdatalen)
		//{
			$rtc_time = time();
			$sql = "UPDATE Product_Value_Objects "
				."SET Object_Value= '$rtc_time' ,Owner_Update=1 "
				."WHERE (Value_Owner_Product_SN='$serial' and Object_Name='timestamp')";
		//}
		if (mysql_query($sql,$con))
		{
			if($zDebug > 4)
			{
				file_put_contents($my_log,"SQL Update Succeeded for $key: ".mysql_error()."\n\n",FILE_APPEND);
			}
		}
		else
		{
			//Trap for format errors in data or for a failed SQL insert.
			//On any error, dump the raw data and headers to a garbage file.
			file_put_contents($my_log,"SQL Write Failed for $key: ".mysql_error()."\n\n",FILE_APPEND);
			file_put_contents($my_log,"SQL Request:[".$sql."]\n",FILE_APPEND);
		}
  	}
	
	//create RPC packet data
	if($tstatus & 32)
	{
		//RTC has not been set, so send an RTC Write RPC command at top of packet.
		$rtc_time = time();
		$outt .= "/rtc/write ".$rtc_time."\r";
		$logdata = "    Current RTC Set Value is: ".$rtc_time.".\n";
		file_put_contents($my_log,$logdata,FILE_APPEND);
	}
	//Search database for variables on this device changed externally.  Add to RPC packet.
    $sql = "SELECT Object_Name, Object_Value, Object_Type, Object_Precision "
    	."FROM Product_Value_Objects "
		."WHERE (Value_Owner_Product_SN='$serial' and External_Update=1)";

	$result = mysql_query($sql,$con);
	if ($result)
	{
  		//file_put_contents($my_log,"SQL Read Succeeded: ".mysql_error()."\n\n",FILE_APPEND);
		while ($row = mysql_fetch_object($result)) {
	  		$arr_out = print_r($row, true);
			$logdata = "Line: $arr_out\n";
//			file_put_contents($my_log,$logdata,FILE_APPEND);
			if($row->Object_Type == 'Int')
			{
				$outval = sprintf("%u", $row->Object_Value);
			}
			elseif ($row->Object_Type == 'Float')
			{
				$valformat = sprintf("%%01.%uf", $row->Object_Precision);
				$outval = sprintf($valformat, $row->Object_Value);
			}
			elseif ($row->Object_Type == 'varchar')
			{
				$outval = "";
			}
			if($row->Object_Name == 'alertclr')
			{
				$row->Object_Name = "astatus";
			}
			if($row->Object_Name ==  'schedule')
			{
				$outt .= "/".$row->Object_Name."/".$row->Object_Value."\r";
			}
			else
			{
				$outt .= "/".$row->Object_Name."/write ".$outval."\r";
			}	
			
			$first_out = 1;
			if($zDebug > 1)
			{
				$logdata = "    Current value of Key: ".$row->Object_Name." is: ".$outval.".\n";
				file_put_contents($my_log,$logdata,FILE_APPEND);
			}
		}
		//Sent all keys with External_Update set.  So clear External_Update flags.
		$sql = "UPDATE Product_Value_Objects "
			."SET External_Update=0 "
			."WHERE (Value_Owner_Product_SN='$serial' and External_Update=1)";
		if (mysql_query($sql,$con))
		{
			if($zDebug > 3)
			{
				file_put_contents($my_log,"SQL Flag Clear Succeeded: ".mysql_error()."\n\n",FILE_APPEND);
			}
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
/* */
	
    mysql_close($con);
  }
  
  


//********************
//start output
//Get RPC packet and put in outt
  
  
//$rtc_time = time(); 
//$rtc2 = $rtc_time +100;// add 6 min to RTC for debugging time
//everything is hardcoded as of now because we dont know the format
//$outt  = "/humid/setpoint/write 12.34\r\n";
//$outt  = "/fanmode/write 0\r\n";
//$outt  .= "/schedule/2/add/* $rtc_time/4/19/1/65.0\r\n/add/* $rtc2/6/4/1/70.0\r\n";
//Copyright Robert Dobrose 2011
  if(strlen($outt) > 0)
{
  $outt .= "\n";
}
$len = strlen($outt);

if($zDebug > 1)
{
	file_put_contents($my_log,"Output Message: size = $len [$outt]\n",FILE_APPEND);

	file_put_contents($my_log,"PHP Script Messages: [".ob_get_contents()."]\n\n",FILE_APPEND);
}
ob_end_clean ();

//(c) Robert Dobrose 2011
header('HTTP/1.1 200 OK');
header('Content-Length: $len');
header('Content-Type: text/plain');
echo $outt;


?>
