<?php

ob_start();  //Buffer output to prevent sending messages or responses

error_reporting(E_ALL);
ini_set('display_errors', '1');

//schedule_time_type  (0 = from, 1 = to)
$FROM_TIME = 0;
$TO_TIME = 1;
$zDebug = 2;
$my_log = "insert_schedule_log.txt";
 
$my_array = $_GET;

$AWAY_SCH = 4;

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


function sqlWriteQuery($query, $con) {
    if (mysql_query($query, $con))
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


file_put_contents($my_log, "Inside insert schedule", FILE_APPEND);
 

$thermSN = trim($my_array['THERMSN']);
if(array_key_exists ('UPDATE', $my_array)) {
    $send_update = true;
} 
else 
{
    $rtc_time = trim($my_array['RTCTIME']);
    $dow = trim($my_array['WEEKDAY']);
    $schedule_dow = trim($my_array['SCH_WEEKDAY']);
    $action = trim($my_array['THERMACTION']);
    $flag = trim($my_array['FLAGS']);
    $value1 = trim($my_array['VALUE1']);
    $value2 = trim($my_array['VALUE2']);
    $schedule_type = trim($my_array['SCHEDULETYPE']);
    $schedule_time_type = trim($my_array['SCHEDULE_TIME_TYPE']);
    $objVal = "add/* ".$rtc_time."/".$schedule_dow."/".$action."/".$flag."/".$value1."/".$value2;
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
    $sql = "SELECT * FROM Therm_Schedule WHERE Therm_SN = $thermSN AND Schedule_Type != $AWAY_SCH";
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
    //Check if there exists an entry with same rtc time, for same day for the given thermostat
    $sql = "SELECT * FROM Therm_Schedule WHERE Therm_SN = $thermSN AND Schedule_Time = $rtc_time AND Schedule_Type = $schedule_type AND Schedule_Time_Type = $schedule_time_type AND DOW = $dow";
    $result = mysql_query($sql,$con);
    if (mysql_num_rows($result) > 0)
    {
        file_put_contents($my_log,"Entry present : ".mysql_error()."\n\n",FILE_APPEND);
        //sendError("Entry present");
        $row = mysql_fetch_object($result);
        
        $objVal = "add/* ".$rtc_time."/".$schedule_dow."/".$action."/".$flag."/".$value1."/".$value2;
        $sql = "UPDATE Therm_Schedule SET Schedule = '$objVal', Set_Point = $value1, THERM_ACTION = $action, External_Update = 1" 
                ." WHERE Therm_SN = $thermSN AND Schedule_Time = $rtc_time AND Schedule_Type = $schedule_type AND Schedule_Time_Type = $schedule_time_type AND DOW = $dow AND SCH_DOW = $schedule_dow";
        sqlWriteQuery($sql, $con);
         
    }
    else 
    {

        //Check if there exists an entry with for the same day and same schedule type
        //If yes, add 'delete' schedule for the existing day
	$sql = "SELECT * FROM Therm_Schedule WHERE Therm_SN = $thermSN AND Schedule_Type = $schedule_type AND Schedule_Time_Type = $schedule_time_type AND THERM_ACTION = $action AND DOW = $dow";
	$result = mysql_query($sql, $con);
	if (mysql_num_rows($result) > 0)
        {
            file_put_contents($my_log,"Entry present : ".mysql_error()."\n\n",FILE_APPEND);
        
            $row = mysql_fetch_object($result);
            $update_schedule = "delete/* ".$row->Schedule_Time."/".$row->SCH_DOW."/".$row->THERM_ACTION."/".$row->FLAGS."/".$row->Set_Point."/1/";
            $sql = "UPDATE Therm_Schedule SET Schedule = '$update_schedule', External_Update = 1" 
                ." WHERE Therm_SN = $thermSN AND Schedule_Type = $schedule_type AND Schedule_Time_Type = $schedule_time_type AND THERM_ACTION = $action AND DOW = $dow";
            sqlWriteQuery($sql, $con);
        }
        //Add 'add' schedule for the new time
        if($schedule_time_type == $FROM_TIME)
        {
            
            $sql = "DELETE FROM Therm_Schedule WHERE Therm_SN = $thermSN AND Schedule_Time = $rtc_time AND Schedule_Time_Type = $TO_TIME AND THERM_ACTION = $action";
            sqlWriteQuery($sql, $con);
                
            
            $response = "";
            $sql = "INSERT INTO Therm_Schedule (Therm_SN, Schedule_Time, Schedule, Schedule_Type, External_Update, Schedule_Time_Type, DOW, SCH_DOW, Set_Point, Therm_Action, FLAGS) "
                    ."VALUES ($thermSN, $rtc_time, '$objVal', $schedule_type ,1 ,$schedule_time_type, $dow, $schedule_dow, $value1, $action, $flag)";
            sqlWriteQuery($sql, $con); 
        }
        else 
        {
            $sql = "SELECT * FROM Therm_Schedule WHERE Therm_SN = $thermSN AND Schedule_Time = $rtc_time AND  Schedule_Type = $schedule_type AND Schedule_Time_Type = $schedule_time_type and Schedule_Time_Type = $FROM_TIME AND THERM_ACTION = $action";
            $result = mysql_query($sql,$con);
            if (mysql_num_rows($result) <= 0)
            {
                $response = "";
                $sql = "INSERT INTO Therm_Schedule (Therm_SN, Schedule_Time, Schedule, Schedule_Type, External_Update, Schedule_Time_Type, DOW, SCH_DOW, Set_Point, Therm_Action, FLAGS) "
                    ."VALUES ($thermSN, $rtc_time, '$objVal', $schedule_type ,1 ,$schedule_time_type, $dow, $schedule_dow, $value1, $action, $flag)";
                sqlWriteQuery($sql, $con); 
                
            }
        }    
        
    }
}

mysql_close($con);

sendResponse($response);
?>

