<?php

ob_start();  //Buffer output to prevent sending messages or responses

error_reporting(E_ALL);
ini_set('display_errors', '1');

$zDebug = 2;
$my_log = "insert_away.log";
 
$my_array = $_GET;

$dow_value_mapping = array(1 => 2, 2 => 4, 3 => 8, 4 => 16, 5 => 32, 6 => 64, 7 => 1);

$HEAT_CONTROL = 1;
$COOL_CONTROL = 2;
$AWAY_SCHEDULE = 4;

$FROM_TIME_TYPE = 0;
$TO_TIME_TYPE = 1;

$ONE_TIME_SCHEDULE = 1;

$SCH_CONTROL_MODE = 48;
$SCH_HEAT_ACTION = 3;
$SCH_COOL_ACTION = 19;

$FLAG =  1;

date_default_timezone_set("UTC");

function resetAway($thermId, $rtc_time_from, $rtc_time_to, $con, $my_log) 
{
    $FROM_TIME_TYPE = 0;
    $AWAY_SCHEDULE = 4;
    $sql = "SELECT * FROM Therm_Away WHERE Therm_SN = $thermId";
    $result = mysql_query($sql, $con);
    file_put_contents($my_log, "SQL old away $sql: $result\n\n",FILE_APPEND);
    if ($result) 
    {   
        if(mysql_num_rows($result) > 0) {
                $row = mysql_fetch_object($result);
                
                foreach (array($row->RTC_FROM, $row->RTC_FROM + 30) as $rtc_time) 
                {
            
                //Just delete the from schedules

                $sql_schedule = "SELECT * FROM Therm_Schedule WHERE Therm_SN = $thermId AND Schedule_Time = $rtc_time AND Schedule_Type = $AWAY_SCHEDULE AND Schedule_Time_Type = $FROM_TIME_TYPE";
                file_put_contents($my_log, "SQL old away sch $sql_schedule: $result\n\n",FILE_APPEND);

                $result1 = mysql_query($sql_schedule, $con);

                if (mysql_num_rows($result1) > 0)
                {

                    while($away_row = mysql_fetch_object($result1)) 
                    {
                        $sql = "DELETE FROM Therm_Schedule WHERE Therm_SN = $thermId AND Schedule_Time = $rtc_time AND Schedule_Type = $AWAY_SCHEDULE";

                        file_put_contents($my_log, "Delete SQL $sql\n\n",FILE_APPEND);

                        mysql_query($sql, $con);

                        $delete_schedule = "delete/* ".$rtc_time."/".$away_row->SCH_DOW."/".$away_row->THERM_ACTION."/".$away_row->FLAGS."/".$away_row->Set_Point."/1";

                        $sql = "INSERT INTO Therm_Schedule (Therm_SN, Schedule_Time, Schedule, Schedule_Type, External_Update, Schedule_Time_Type, DOW, SCH_DOW, Set_Point, Therm_Action, FLAGS) "
                                    ."VALUES ($thermId, $rtc_time, '$delete_schedule', $away_row->Schedule_Type ,1 , $away_row->Schedule_Time_Type, $away_row->DOW, $away_row->SCH_DOW, 1, $away_row->THERM_ACTION, $away_row->FLAGS)";

                        file_put_contents($my_log, "Insert-delete SQL $sql\n\n",FILE_APPEND);
                        mysql_query($sql, $con);
                    }
                }
            }
            
            $current_time = time();
            file_put_contents($my_log, "current time $current_time\n\n",FILE_APPEND);
            if($current_time < (int) $rtc_time_to) {
                //If away schedule is running then reset it
                foreach (array(2, 4, 8, 16, 32, 64, 1) as $week_days) 
                {
                    file_put_contents($my_log, "current time $current_time < $rtc_time_to\n\n",FILE_APPEND);
                    $dow = $week_days;

                    //Check if there exists an entry with same rtc time, for same day for the given thermostat
                    $sql = "SELECT * FROM Therm_Schedule WHERE Therm_SN = $thermId AND DOW = $dow AND Schedule_Type != $AWAY_SCHEDULE";
                    $sch_result = mysql_query($sql, $con);

                    if (mysql_num_rows($sch_result) > 0)
                    {
                        
                        while ($sch_row = mysql_fetch_object($sch_result)) 
                        {

                            $existing_time_seconds = $sch_row->Schedule_Time % (24 * 60 * 60);
                            $update_timestamp = getAwayDay($current_time, $existing_time_seconds, $dow, $my_log);
                            $new_add_schedule = "add/* ".$update_timestamp."/".$sch_row->SCH_DOW."/".$sch_row->THERM_ACTION."/".$sch_row->FLAGS."/".$sch_row->Set_Point."/1";
                            $sql = "INSERT INTO Therm_Schedule (Therm_SN, Schedule_Time, Schedule, Schedule_Type, External_Update, Schedule_Time_Type, DOW, SCH_DOW, Set_Point, Therm_Action, FLAGS) "
                                ."VALUES ($thermId, $update_timestamp, '$new_add_schedule', $sch_row->Schedule_Type ,1 , $sch_row->Schedule_Time_Type, $sch_row->DOW, $sch_row->SCH_DOW, 1, $sch_row->THERM_ACTION, $sch_row->FLAGS)";
                            file_put_contents($my_log, "Add SQL $sql\n\n",FILE_APPEND);
                            mysql_query($sql, $con);
                        }
                    }
                }
            }
            
            $delete_away_sql = "DELETE FROM Therm_Away WHERE Therm_SN = $thermId";
            $result = mysql_query($delete_away_sql, $con);
        }
        
    }
}
function getObject($thermId, $objectId, $con) 
{
    $sql = "SELECT Object_Value FROM Product_Value_Objects WHERE Value_Owner_Product_SN = $thermId AND Object_Name = '$objectId'";
    $result = mysql_query($sql, $con);
    if (mysql_num_rows($result) > 0)
    {
        $row = mysql_fetch_object($result);
        return $row->Object_Value;
    }
    return null;
    
}


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



function getAwayDay($rtc_time, $seconds, $dow, $my_log) {
    $day_mapping = array(2 => 1, 4 => 2, 8 => 3, 16 => 4, 32 => 5, 64 => 6, 1 => 7);
    
    $away_dom = date("j", $rtc_time);
    $away_dow = date("N", $rtc_time);
    $away_month = date("m", $rtc_time);
    $away_year = date("Y", $rtc_time);
    $current_hour = date('H', $rtc_time);
    $current_minute = date('i', $rtc_time);
    $current_seconds = ($current_hour * 60 * 60) + ($current_minute * 60);
    $day_diff = $day_mapping[$dow] - $away_dow;
    
    if($day_diff < 0) 
    {            
        $day_diff = 7;
        //$day_diff = $day_diff1 + $day_diff;
    }   
    else if($day_diff == 0) 
    {    
        if($seconds <= $current_seconds) {
        
            $day_diff = 7;
            
        }               
     } 
            
     file_put_contents($my_log,"$day_diff $away_dom $away_dow $day_mapping[$dow]\n\n",FILE_APPEND);
     
     return mktime(0, 0, $seconds, $away_month, $away_dom + $day_diff , $away_year);
}

function getSecondsOfDay($utcTime) 
{
    return $utcTime - ($utcTime % (24 * 60 * 60));
}
file_put_contents($my_log, "Inside away", FILE_APPEND);
 
$send_update = false;
$reset = false;

$thermSN = trim($my_array['THERMSN']);
if(array_key_exists ('UPDATE', $my_array)) 
{
    $send_update = true;
} 
else if(array_key_exists ('RESET', $my_array)) 
{
    $reset = true;
} 
else
{
    $rtc_time_from = trim($my_array['RTC_TIME_FROM']);
    $rtc_time_to = trim($my_array['RTC_TIME_TO']);
    file_put_contents($my_log, "from: $rtc_time_from to: $rtc_time_to\n\n", FILE_APPEND);
    $setpoint = 60;
}


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
    
    file_put_contents($my_log, "Sending updates to user\n\n", FILE_APPEND);
    $sql = "SELECT * FROM Therm_Away WHERE Therm_SN = $thermSN";
    $result = mysql_query($sql, $con);
    if ($result) 
    {
        while ($row = mysql_fetch_object($result)) {
            
            $first_out = 1;
            $response .= $row->RTC_FROM;
            $response .= "&";
            $response .= $row->RTC_To;
            
        }
        file_put_contents($my_log, "Sending response $response", FILE_APPEND);	
        
        
    }
}else if($reset == true) {
    
    file_put_contents($my_log, "reset Away  $response", FILE_APPEND);	
    resetAway($thermSN, $rtc_time_from, $rtc_time_to, $con, $my_log);
}
else {
    foreach (array(2, 4, 8, 16, 32, 64, 1) as $week_days) {
        $dow = $week_days;
        
        //Check if there exists an entry with same rtc time, for same day for the given thermostat
        $sql = "SELECT * FROM Therm_Schedule WHERE Therm_SN = $thermSN AND DOW = $dow AND Schedule_Type != $AWAY_SCHEDULE AND Schedule_Time <= $rtc_time_to";
        $result = mysql_query($sql, $con);

        if (mysql_num_rows($result) > 0)
        {
            file_put_contents($my_log,"Entry present : $row->Schedule_Time, $row->DOW\n\n",FILE_APPEND);

            while ($row = mysql_fetch_object($result)) {
                
                $overlap = false;
                
                $existing_time_seconds = $row->Schedule_Time % (24 * 60 * 60);
                
                $away_dow = $dow_value_mapping[date("N", $rtc_time_to)];
                
                file_put_contents($my_log,"away_dow = $away_dow \n\n",FILE_APPEND);
                
                file_put_contents($my_log,"row->Schedule_Time_Type = $row->Schedule_Time_Type \n\n",FILE_APPEND);
                file_put_contents($my_log,"row->DOW = $row->DOW \n\n",FILE_APPEND);
                if($row->Schedule_Time_Type == $FROM_TIME_TYPE && $away_dow == $row->DOW)
                {
                    $away_time_sec =  $rtc_time_to % (24 * 60 * 60);
                    if($existing_time_seconds <= $away_time_sec) {
                        $sql_to = "SELECT * FROM Therm_Schedule WHERE Therm_SN = $thermSN AND DOW = $dow AND Schedule_Type = $row->Schedule_Type AND THERM_ACTION = $row->THERM_ACTION AND Schedule_Time_Type = $TO_TIME_TYPE";
                        file_put_contents($my_log,"SQL TO : $sql_to\n\n",FILE_APPEND);
                        
                        $to_result = mysql_query($sql_to, $con);
                        if(mysql_num_rows($to_result) > 0) {
                            
                            $to_row = mysql_fetch_object($to_result);
                            $to_time_sec = $to_row->Schedule_Time % (24 * 60 * 60);
                            
                            file_put_contents($my_log,"TO time : $to_row->Schedule_Time\n\n",FILE_APPEND);
                            
                            if($to_row->DOW == $to_row->SCH_DOW) 
                            {
                                if($to_time_sec >= $away_time_sec) 
                                {
                                    $overlap = true;
                                }
                                
                            }   
                            else 
                            {
                                $overlap = true;
                            }
                        }
                        
                    }    
                }
                file_put_contents($my_log, "overlap $overlap\n\n",FILE_APPEND);
                
                
                $update_timestamp = getAwayDay( $rtc_time_from, $existing_time_seconds, $row->SCH_DOW, $my_log);
                file_put_contents($my_log, "Delete schedule timestamp $update_timestamp\n\n",FILE_APPEND);

                $delete_schedule = "delete/* ".$row->Schedule_Time."/".$row->SCH_DOW."/".$row->THERM_ACTION."/".$row->FLAGS."/".$row->Set_Point."/1";

                $sql = "DELETE FROM Therm_Schedule WHERE Therm_SN = $thermSN AND Schedule_Time = $row->Schedule_Time AND DOW = $row->DOW";

                file_put_contents($my_log, "__Delete SQL $sql\n\n",FILE_APPEND);
                mysql_query($sql, $con);

                $sql = "INSERT INTO Therm_Schedule (Therm_SN, Schedule_Time, Schedule, Schedule_Type, External_Update, Schedule_Time_Type, DOW, SCH_DOW, Set_Point, Therm_Action, FLAGS) "
                            ."VALUES ($thermSN, $row->Schedule_Time, '$delete_schedule', $row->Schedule_Type ,1 , $row->Schedule_Time_Type, $row->DOW, $row->SCH_DOW, $row->Set_Point, $row->THERM_ACTION, $row->FLAGS)";

                file_put_contents($my_log, "__Insert SQL $sql\n\n",FILE_APPEND);

                mysql_query($sql, $con);

                if(strpos($row->Schedule, "add") == 0) 
                {   
                    $update_timestamp = getAwayDay( $rtc_time_to, $existing_time_seconds, $row->DOW, $my_log);


                    //$update_timestamp = getAwayDay( $rtc_time_to, $existing_time_seconds, $dow, $my_log);
                    file_put_contents($my_log, "Add schedule timestamp $update_timestamp\n\n",FILE_APPEND);
                    $new_add_schedule = "add/* ".$update_timestamp."/".$row->SCH_DOW."/".$row->THERM_ACTION."/".$row->FLAGS."/".$row->Set_Point."/1";
                    $sql = "INSERT INTO Therm_Schedule (Therm_SN, Schedule_Time, Schedule, Schedule_Type, External_Update, Schedule_Time_Type, DOW, SCH_DOW, Set_Point, Therm_Action, FLAGS) "
                            ."VALUES ($thermSN, $update_timestamp, '$new_add_schedule', $row->Schedule_Type ,1 , $row->Schedule_Time_Type, $row->DOW, $row->SCH_DOW, $row->Set_Point, $row->THERM_ACTION, $row->FLAGS)";
                    file_put_contents($my_log, "++Add SQL $sql\n\n",FILE_APPEND);
                    mysql_query($sql, $con);

                    if(overlap == true) 
                    {
                        $temp_dow = $dow_value_mapping[date("N", $rtc_time_to)];
                        $update_schedule_time = $rtc_time_to + 30;
                        if($row->THERM_ACTION != $SCH_CONTROL_MODE)
                        {
                            $update_schedule_time += 30;
                        }
                        $new_add_schedule = "add/* ".$update_schedule_time."/".$temp_dow."/".$row->THERM_ACTION."/1/".$row->Set_Point."/1";
                    
                        $sql = "INSERT INTO Therm_Schedule (Therm_SN, Schedule_Time, Schedule, Schedule_Type, External_Update, Schedule_Time_Type, DOW, SCH_DOW, Set_Point, Therm_Action, FLAGS) "
                            ."VALUES ($thermSN, $update_schedule_time, '$new_add_schedule', $row->Schedule_Type ,1 , $row->Schedule_Time_Type, $temp_dow, $temp_dow, $row->Set_Point, $row->THERM_ACTION, 1)";
                        file_put_contents($my_log, "++Add temp Schedule SQL $sql\n\n",FILE_APPEND);
                        mysql_query($sql, $con);
                    }

                }
                
            }
    
            
        }
    }
    //Delete old therm schedule
    $sql = "DELETE FROM Therm_Away WHERE Therm_SN = $thermSN";
    $result = mysql_query($sql, $con);
    
    $sql = "INSERT INTO Therm_Away (Therm_SN, Set_Point, THERM_ACTION, DOW, RTC_FROM, RTC_TO) VALUES ($thermSN, $setpoint, 1, $dow, $rtc_time_from, $rtc_time_to)";
    file_put_contents($my_log, "SQL $sql \n\n",FILE_APPEND);
    $result = mysql_query($sql, $con);
    if ($result) 
    {

        $control_mode = getObject($thermSN, "controlmode", $con);


        if($control_mode == "0") 
        {
            $therm_action = $SCH_HEAT_ACTION;
            $setpoint = 60;
        }    
        if($control_mode == "1") 
        { 
            $therm_action = $SCH_HEAT_ACTION;
            $setpoint = 60;
        }
        else 
        {
            $therm_action = $SCH_COOL_ACTION;
            $setpoint = 80;
        }


        $away_dow = $dow_value_mapping[date("N", $rtc_time_from)];
        $away_add_schedule = "add/* ".$rtc_time_from."/".$away_dow."/".$SCH_CONTROL_MODE."/".$ONE_TIME_SCHEDULE."/1/1";

        $sql = "INSERT INTO Therm_Schedule (Therm_SN, Schedule_Time, Schedule, Schedule_Type, External_Update, Schedule_Time_Type, DOW, SCH_DOW, Set_Point, Therm_Action, FLAGS) "
                        ."VALUES ($thermSN, $rtc_time_from, '$away_add_schedule', $AWAY_SCHEDULE ,1 , $FROM_TIME_TYPE, $away_dow, $away_dow, 1, $SCH_CONTROL_MODE, $ONE_TIME_SCHEDULE)";
                file_put_contents($my_log, "AWAY Add SQL $sql\n\n",FILE_APPEND);

                        mysql_query($sql, $con);

        $rtc_time_from1 = $rtc_time_from + 30;
        $away_add_schedule = "add/* ".$rtc_time_from1."/".$away_dow."/".$therm_action."/".$ONE_TIME_SCHEDULE."/".$setpoint."/1";

        $sql = "INSERT INTO Therm_Schedule (Therm_SN, Schedule_Time, Schedule, Schedule_Type, External_Update, Schedule_Time_Type, DOW, SCH_DOW, Set_Point, Therm_Action, FLAGS) "
                        ."VALUES ($thermSN, $rtc_time_from1, '$away_add_schedule', $AWAY_SCHEDULE ,1 , $FROM_TIME_TYPE, $away_dow, $away_dow, $setpoint, $therm_action, $ONE_TIME_SCHEDULE)";

        file_put_contents($my_log, "AWAY Add SQL $sql\n\n",FILE_APPEND);
        mysql_query($sql, $con);

        //Do not add delete records for 
        //$away_dow = $dow_value_mapping[date("N", $rtc_time_to)];
        //file_put_contents($my_log, "TO DOW ".date("N", $rtc_time_to)." \n\n",FILE_APPEND);
        //$away_delete_schedule = "delete/* ".$rtc_time_to."/".$away_dow."/".$SCH_CONTROL_MODE."/".$ONE_TIME_SCHEDULE."/".$SCH_HEAT_ACTION."/1";

        //$sql = "INSERT INTO Therm_Schedule (Therm_SN, Schedule_Time, Schedule, Schedule_Type, External_Update, Schedule_Time_Type, DOW, SCH_DOW, Set_Point, Therm_Action, FLAGS) "
        //                ."VALUES ($thermSN, $rtc_time_to, '$away_delete_schedule', $AWAY_SCHEDULE ,1 , $TO_TIME_TYPE, $away_dow, $away_dow, 1, $SCH_CONTROL_MODE, $ONE_TIME_SCHEDULE)";
        //        file_put_contents($my_log, "insert DELETE SQL $sql\n\n",FILE_APPEND);
        //        mysql_query($sql, $con);

        //$rtc_time_to1 = $rtc_time_to + 30;
        //$away_delete_schedule = "delete/* ".$rtc_time_to1."/".$away_dow."/".$therm_action."/".$ONE_TIME_SCHEDULE."/".$setpoint."/1";

        //$sql = "INSERT INTO Therm_Schedule (Therm_SN, Schedule_Time, Schedule, Schedule_Type, External_Update, Schedule_Time_Type, DOW, SCH_DOW, Set_Point, Therm_Action, FLAGS) "
        //                ."VALUES ($thermSN, $rtc_time_to1, '$away_delete_schedule', $AWAY_SCHEDULE ,1 , $TO_TIME_TYPE, $away_dow, $away_dow, 1, $therm_action, $ONE_TIME_SCHEDULE)";
        //file_put_contents($my_log, "INSERT delete SQL $sql\n\n",FILE_APPEND);
        //mysql_query($sql, $con);

    }

}

sendResponse($response);

mysql_close($con);


?>
