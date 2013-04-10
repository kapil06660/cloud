<?php
ob_start();  //Buffer output to prevent sending messages or responses^M

error_reporting(E_ALL);
ini_set('display_errors', '1');
$my_log = "zgschedule_log.txt";

$my_array = $_GET;

    function sendResponse($response) {
        $len = strlen($response);

        ob_end_clean ();

        header('HTTP/1.1 200 OK');
        header('Content-Length: $len');
        header('Content-Type: text/plain');
        echo $response;
    }

   function sendError($error) {
        $len = strlen($error);
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

	//Some high number
        $day_offset = 10000; 
        $response = "DOW=$current_dow,H=$current_hour,MIN=$current_minute";
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
        $next_date = mktime($hour, $minute, 0, date("m"), date("d") + $day_offset , date("Y"));
        //return strval($day_offset);
	$response .= "DIFF=$day_diff,OFF=$day_offset,DATE=$next_date";
        return $response;
    }

    sendResponse(getRTCTime($my_array["h"], $my_array["m"], $my_array["dow"]));

?>
