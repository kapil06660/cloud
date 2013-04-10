<?php
ob_start();  //Buffer output to prevent sending messages or responses

error_reporting(E_ALL);
ini_set('display_errors', '1');
$my_log = "zgschedule_log.txt";
 
$my_array = $_GET;

$timezone_hour = 5;
$timezone_minute = 0;

    function sendResponse($response) {
        $len = strlen($response);

        if(ob_get_length() > 0)
        {
            //Clean up and log any console error messages
            file_put_contents($my_log,"PHP Script Messages: [".ob_get_contents()."]\n\n",FILE_APPEND);
        }
        ob_end_clean ();

        header('HTTP/1.1 200 OK');
        header('Content-Length: $len');
        header('Content-Type: text/plain');
        echo $response;
    }

   function sendError($error) {
        $len = strlen($error);
        
        if(ob_get_length() > 0)
        {
            //Clean up and log any console error messages
            file_put_contents($my_log,"PHP Script Messages: [".ob_get_contents()."]\n\n",FILE_APPEND);
        }
        ob_end_clean ();
        
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Length: $len');
        header('Content-Type: text/plain');
        echo $error;
    }

    function getRTCTime($hour, $minute, $selected_days) {
	$current_dow = date('N');
        $current_hour = date('H');
        $current_minute = date('i');
	$secs = $hour * 60 * 60 + $minute * 60;
        //Some high number
        $day_offset = 10000;
        $day_index = 1;
        foreach (array(2, 4, 8, 16, 32, 64, 1) as $week_days) {

            $dd = $selected_days & $week_days;
            if($dd == $week_days) {
                $day_diff = $day_index - $current_dow;
                if($day_diff < 0) {
                    $day_diff += 7;
                }
                if($day_diff == 0) {
                    if($hour < $current_hour) {
                        $day_diff = 7;
                    }
                    else if($hour == $current_hour) {
                        if($minute <= $current_minute) {
                            $day_diff = 7;
                        }
                    }
                } 
                if($day_diff < $day_offset) {
                    $day_offset = $day_diff;
                }
            }
            $day_index = $day_index + 1;
        }
        if($day_offset == 10000) {
                $day_offset = 0;
        }
        $next_date = mktime(0, 0, $secs, date("m"), date("d") + $day_offset , date("Y"));
	return $next_date;
    }


    function getSelectedDay($days, $is_selected) {
	if(($days & $is_selected) == $is_selected) {
		file_put_contents($my_log, "Days: $days and Day: $is_selected true\n", FILE_APPEND);
		return true;
	}
	else {
		file_put_contents($my_log, "Days: $days and Day: $is_selected false\n", FILE_APPEND);
		return false;
	} 
    }

file_put_contents($my_log, "Inside insert schedule", FILE_APPEND);
$zDebug = 2; 

$thermSN = trim($my_array['THERMSN']);
if(array_key_exists ('UPDATE', $my_array)) {
	$send_update = true;
} 
else 
{
	$rtc_time = trim($my_array['RTCTIME']);
	$dow = trim($my_array['WEEKDAY']);
	$action = trim($my_array['THERMACTION']);
	$flag = trim($my_array['FLAGS']);
	$value1 = trim($my_array['VALUE1']);
	$value2 = trim($my_array['VALUE2']);
	$schedule_type = trim($my_array['SCHEDULETYPE']);
	$schedule_time_type = trim($my_array['SCHEDULE_TIME_TYPE']);
	$objVal = "add/* ".$rtc_time."/".$dow."/".$action."/".$flag."/".$value1;
}

file_put_contents($my_log , "".$objVal."\n",FILE_APPEND);
file_put_contents($my_log, "".time()."\n", FILE_APPEND);

$database_name = "Home_db";
$con = mysql_connect("localhost","root","HXtmmPyuWI1l6pDE3l6V");
//or die("database error");

mysql_select_db($database_name, $con);
  if (!$con)
  {
  	file_put_contents($my_log, "SQL Connect Failed: ".mysql_error()."\n\n",FILE_APPEND);
	sendError("Database Error");
	return;
  }
  file_put_contents($my_log, "SQL Connect successful\n\n", FILE_APPEND);
  if($send_update == true) 
  {
	$first_out = 0;
	$response = "";
	$count = 1;
	file_put_contents($my_log, "Sending updates to user\n\n", FILE_APPEND);
	$sql = "SELECT * FROM Therm_Schedule WHERE Therm_SN = $thermSN";
	$result = mysql_query($sql, $con);
	if ($result) 
	{
		while ($row = mysql_fetch_object($result)) {
		 	if($first_out > 0)
                        {
                                $response .= "&";
                        }
			$first_out = 1;
			$response .= "Schedule".$count."=".$row->Schedule;
			$response .= "/$row->Schedule_Type";
			$response .= "/$row->Schedule_Time_Type";
			$count = $count + 1;
		}
		file_put_contents($my_log, "Sending response $response", FILE_APPEND);	
	}
  }
  else 
  {
	//Check if there exists an entry with same rtc time for the given thermostat
	$sql = "SELECT * FROM Therm_Schedule WHERE Therm_SN = $thermSN AND Schedule_Time = $rtc_time AND  Schedule_Type = $schedule_type AND Schedule_Time_Type = $schedule_time_type";
	$result = mysql_query($sql,$con);
	if (mysql_num_rows($result) > 0)
        {
		file_put_contents($my_log,"Entry present : ".mysql_error()."\n\n",FILE_APPEND);
		//sendError("Entry present");
		$row = mysql_fetch_object($result);
		if(!($row->DOW & $dow == $dow)) {
			$dow = $row->DOW + $dow; 
		}
		$objVal = "add/* ".$rtc_time."/".$dow."/".$action."/".$flag."/".$value1;
		$sql = "UPDATE Therm_Schedule SET Schedule = '$objVal', DOW = $dow, Set_Point = $value1, Schedule_Time_Type = $schedule_time_type, Schedule_Type = $schedule_type, External_Update = 1" 
			." WHERE Therm_SN = $thermSN AND Schedule_Time = $rtc_time";
		if (mysql_query($sql,$con))
                {
                                if($zDebug > 1)
                        {
                                file_put_contents($my_log,"SQL insert Succeeded: ".mysql_error()."\n\n",FILE_APPEND);
                        }
                }
                else
                {
			//Trap for format errors in data or for a failed SQL insert.
                        //On any error, dump the raw data and headers to a garbage file.
                        file_put_contents($my_log,"SQL Flag Clear Failed: ".mysql_error()."\n\n",FILE_APPEND);
                        file_put_contents($my_log,"SQL Request:[".$sql."]\n",FILE_APPEND);
                        sendError("Database error");
                        return;
		}
		
	}
	else
	{
		//Now find existing schedules for the given schedule_type say wkae_up, bed etc
		$sql = "SELECT * FROM Therm_Schedule WHERE Therm_SN = $thermSN AND Schedule_type = $schedule_type AND Schedule_Time_Type = $schedule_time_type AND THERM_ACTION = $action";
		$result = mysql_query($sql,$con); 
		file_put_contents($my_log, "Now find existing schedules for the given schedule_type say wkae_up, bed etc \n\n", FILE_APPEND);		
		if ($result)
                {
			while($row =  mysql_fetch_object($result)) 
			{
		    	    file_put_contents($my_log, "Now $dow , $row->DOW \n\n", FILE_APPEND);
			    $days_to_update = 0;
			    $new_time_oofset = 0;
			    foreach (array(1, 2, 4, 8, 16, 32, 64) as $week_days) {

            			//if(getSelectedDay($row->DOW, $week_day) && getSelectedDay($dow, $week_day)) {
				//if(($row->DOW & $week_days == $week_days) && ($dow & $week_days == $week_days)) { 
			          $dd1 = $row->DOW & $week_days;
				  $dd2 = $dow & $week_days;
				  if($dd1 == $week_days && $dd2 == $week_days) { 
				      file_put_contents($my_log,"=>$dd1  $dd2 \n",FILE_APPEND); 
				      $days_to_update = $days_to_update + $week_days;	
				  }
			    
			    }
				
			    if($days_to_update == 0)
                            {
            	                continue;
                            }
			    file_put_contents($my_log, "Days to delete: $days_to_update\n", FILE_APPEND);
				
			    $day_secs = ($row->Schedule_Time % (24 * 60 * 60));
			    $day_secs = $day_secs - (( $timezone_hour * 60 * 60) + ($timezone_minute * 60));	
			    $sch_hour = $day_secs / (60 * 60) ;
			    $sch_minute = ($day_secs % 60) / 60;			
		            file_put_contents($my_log,"hour: $sch_hour minute:$sch_minute\n", FILE_APPEND);		
			    $days_to_delete = $row->DOW ^ $days_to_update;
			    file_put_contents($my_log,"Days to update: $days_to_delete\n", FILE_APPEND);
				
			    $update_sql = "DELETE FROM Therm_Schedule WHERE Therm_SN = $thermSN AND Schedule_Time = $row->Schedule_Time";
			    if (mysql_query($update_sql,$con))
                            {
                                      //file_put_contents($my_log,"Added delete schedule for: $schedule_type \n\n",FILE_APPEND);        
                            }

		   	    file_put_contents($my_log, $update_sql, FILE_APPEND);
			    $delete_time = getRTCTime($sch_hour, $sch_minute, $days_to_delete);

                            if($days_to_delete != 0) 
			    {
				file_put_contents($my_log,"Update time : $delete_time\n", FILE_APPEND);
 	
                                $update_schedule = "add/* ".$delete_time."/".$days_to_delete."/".$row->THERM_ACTION."/1/".$row->Set_Point;
                                $update_sql = "INSERT INTO Therm_Schedule (Therm_SN, Schedule_Time, Schedule, Schedule_Type, External_Update, Schedule_Time_Type, DOW, Set_Point, Therm_Action) "
                                ."VALUES ($thermSN, $delete_time, '$update_schedule', $row->Schedule_Type ,1 ,$row->Schedule_Time_Type, $days_to_delete, $row->Set_Point, $row->THERM_ACTION)";
				if (mysql_query($update_sql,$con))
                               	{
				    file_put_contents($my_log,"Updated  schedule for: $schedule_type \n\n",FILE_APPEND);   		
                                }
			    }
			    file_put_contents($my_log, $update_sql, FILE_APPEND);
			    $delete_time = getRTCTime($sch_hour, $sch_minute, $days_to_update);
			    file_put_contents($my_log,"Delete time : $delete_time\n", FILE_APPEND);
			    $new_schedule = "delete/* $delete_time/$dow/$row->THERM_ACTION/1/$row->Set_Point";
                            $update_sql = "INSERT INTO Therm_Schedule (Therm_SN, Schedule_Time, Schedule, Schedule_Type, External_Update, Schedule_Time_Type, DOW, Set_Point, Therm_Action) "
                            ."VALUES ($thermSN, $delete_time, '$new_schedule', $row->Schedule_Type ,1 ,$row->Schedule_Time_Type, $days_to_update, $row->Set_Point, $row->THERM_ACTION)";
                            file_put_contents($my_log, $update_sql, FILE_APPEND);
                            if (mysql_query($update_sql,$con))
                            {    
                            	file_put_contents($my_log,"Added delete schedule for: $schedule_type \n\n",FILE_APPEND);        
                            }
			}	
		}
	
		$response = "";
		$sql = "INSERT INTO Therm_Schedule (Therm_SN, Schedule_Time, Schedule, Schedule_Type, External_Update, Schedule_Time_Type, DOW, Set_Point, Therm_Action) "
			."VALUES ($thermSN, $rtc_time, '$objVal', $schedule_type ,1 ,$schedule_time_type, $dow, $value1, $action)";

		if (mysql_query($sql,$con))
		{
				if($zDebug > 1)
			{
				file_put_contents($my_log,"SQL insert Succeeded: ".mysql_error()."\n\n",FILE_APPEND);
			}
		}
		else
	  	{
			//Trap for format errors in data or for a failed SQL insert.
		  	//On any error, dump the raw data and headers to a garbage file.
	  		file_put_contents($my_log,"SQL Flag Clear Failed: ".mysql_error()."\n\n",FILE_APPEND);
			file_put_contents($my_log,"SQL Request:[".$sql."]\n",FILE_APPEND);
    			sendError("Database error");
			return;
		}
	}
  }
	

 mysql_close($con);

 sendResponse($response);

?>
