<?php
//Copyright Robert Dobrose 2011

/*
 *   
//Get screen height and width
if (isset($_GET['width']) AND isset($_GET['height'])) {
  // output the geometry variables
  echo "Screen width is: ". $_GET['width'] ."<br />\n";
  echo "Screen height is: ". $_GET['height'] ."<br />\n";
} else {
  // pass the geometry variables
  // (preserve the original query string
  //   -- post variables will need to handled differently)

  echo "<script language='javascript'>\n";
  echo "  location.href=\"${_SERVER['SCRIPT_NAME']}?${_SERVER['QUERY_STRING']}"
            . "&width=\" + screen.width + \"&height=\" + screen.height;\n";
  echo "</script>\n";
  exit();
}
*/


//****************************
//*** Function Definitions ***
//Copyright Robert Dobrose 2011
//****************************
function GraphYGrid($filename, $g_font, $g_width, $g_height, $s_maxval, $s_minval, $s_tickstep)
{
	$ims = @imagecreatetruecolor($g_width, $g_height)
	or die ("Cannot Initialize new GD Grid ticks image stream");
	
	imagealphablending ($ims, true);
	$background_color = ImageColorAllocate ($ims, 200, 220, 200);
	$text_color = ImageColorAllocate ($ims, 0, 0, 0);
	imagefill($ims, 0, 0, $background_color);
	$g_scale = $g_height /($s_maxval-$s_minval); //scale of the y-axis range (visual magnitude of each y-axis count)
	
	imagestringup($ims, $g_font, 5, ($g_height - 100), "Temperature - Humidity - Status", $text_color);
	$fontshift = imagefontheight($g_font) / 2;
	for($ts=1,$g_val = $s_minval + ($ts * $s_tickstep); $g_val < $s_maxval; $ts++,$g_val = $s_minval + ($ts * $s_tickstep))
	{
		$gys = $g_height - (($g_val - $s_minval) * $g_scale);
		imagestring ($ims, $g_font, 25, ($gys - $fontshift), "$g_val -",$text_color); // Drawing the line between two points
	}
	
	ImagePng($ims, $filename); # store the image to file
	
	imagedestroy($ims);
}


//********************************
//*** END Function Definitions ***
//********************************


//include "db.php"; 

//$host = "50.57.99.73:3306";  //This is the server where your database resides
//Copyright Robert Dobrose 2011
$host = "localhost:3306";  //This is the server where your database resides
$user = "root";           //This should be your database login
$pw = 'HXtmmPyuWI1l6pDE3l6V'; //This should be your database password
$database = "Home_db";        //This should be your database name

$x_gap=1; // The gap between each point in y axis
$x_start=0; //open space at left of graph before first point
$y_maxval = 100;  //Max numeric y-axis value on the graph display
$y_minval = 0;  //Min numeric y-axis value on the graph display


$x_max=1000; // Maximum width of the graph or horizontal axis
$x_scale=60; // Width of the y-axis grid bar
$y_tickstep = 10;  //counts between tick marks on y-axis grid
$y_max=500; // Maximum height of the graph or vertical axis
$y_scale = $y_max /($y_maxval-$y_minval); //scale of the y-axis range (visual magnitude of each y-axis count)
// Above variables will be used to create a canvas of the image//

if($_GET['Serial'])
{
	$DeviceSN = $_GET['Serial'];
}
else 
{
	$DeviceSN = 0;
}
if($_GET['Days'])
{
	$DaysBack = $_GET['Days'];
}
else 
{
	$DaysBack = 0;
}
if($_GET['Scale'])
{
	$DataScale = $_GET['Scale'];
}
else 
{
	$DataScale = 1;
}
if($_GET['Debug'])
{
	$Debug = $_GET['Debug'];
}
else 
{
	$Debug = 0;
}

session_start(); 
$SID = session_id();
$_SESSION['sid'] = $SID;

$db = mysql_connect($host,$user,$pw)
        or die("Cannot connect to MySQL.");
mysql_select_db($database,$db)
        or die("Cannot connect to database.");

if($DeviceSN != 0)
{
	$command = "SELECT CityName,State FROM Customer_House "
				."WHERE (Customer_Location_Id=(SELECT Customer_House_Customer_Location_Id "
				."FROM Customer_Zeus_Product WHERE Product_SN='$DeviceSN'))";
				$result = mysql_query($command);
	$data = mysql_fetch_object($result);
	$cname = $data->CityName;
	$sname = $data->State;
}
else 
{
	$cname = "Test";
	$sname = "USA";
}

$form_name = "Zeus Grid Device Data Chart";
$sub_name = "Customer house in $cname, $sname";
if(($DaysBack == 0) && ($DataScale < 100))
{
	$Page_Refresh = 1;
}
else 
{
	$Page_Refresh = 0;
}
require("template_top.inc");

//header ("Content-type: image/png");
$rec_found = false;
//print_r($tt);
//print_r($ht);
$mylimit = 300;
$rowstart = 0;
$tt = array(); 
$ht = array();
$st = array();

//header('Content-Disposition: Attachment;filename=image.png'); 

$d_count = 0;	//Count of number of records saved for display

$s_count = 0;	//Count of records since last scale dec
$s_accum_t = 0;
$s_accum_h = 0;
$last_tval = 0;
$last_hval = 0;

//$prev_ts = time();
$prev_ts = date_timestamp_get(date_sub(date_create(), date_interval_create_from_date_string($DaysBack.' days')));

if($DeviceSN != 0)
{
	do 
	{
		//Query for a limited set of data records to avoid memory overflows
		//Do multiple queries while there are still records in the dataset
		$table_name = 'Product_Raw_Data_Sample';
		$command = "SELECT Sample_Timestamp,Sample_Length,Sample_Data_Record,Data_Record_Count,Product_Data_Sample_Id "
		    	."FROM $table_name "
				."WHERE (Customer_Zeus_Product_Product_SN='$DeviceSN' "
				."and Sample_Timestamp < DATE_ADD(CAST(DATE_SUB(CURDATE(), INTERVAL '$DaysBack' DAY) as DATETIME), INTERVAL 24 HOUR)) "
				."ORDER BY Sample_Timestamp DESC "
				."LIMIT $rowstart,$mylimit";
//				."and Sample_Timestamp >= CAST(DATE_SUB(CURDATE(), INTERVAL '$DaysBack' DAY) as DATETIME) "
		if($Debug >= 2)
		{
			echo "<br>SQL: ";
			print_r($command);
			echo "<br>";
		}
		$result = mysql_query($command);
		//$result = mysql_unbuffered_query($command);
		$row_count = mysql_num_rows($result);	//Keep track of the number of records selected
		if($Debug >= 1)
		{
			echo "Record Rows Found: ";
			print_r($row_count);
			echo "<br>";
		}
		
		
		while($rdata = mysql_fetch_object($result))
		{
			if($Debug >= 3)
			{
				print_r($rdata->Sample_Timestamp);
				echo " - ";
				print_r($rdata->Product_Data_Sample_Id);
				echo ":  ";
				//Work with each record in the query select list
				print_r($rdata->Sample_Data_Record);
				//print_r($rdata);
				echo "<br>";
			}
			$rec_ts = date_timestamp_get(date_create($rdata->Sample_Timestamp));
			//if($DaysBack == 0)
			//{
			//	$prev_ts = $rec_ts;
			//}
			$delta_ts = ($prev_ts - $rec_ts) - $rdata->Data_Record_Count;
			if($Debug >= 1)
			{
				echo "prev_ts = $prev_ts : rec_ts = $rec_ts : delta_ts = $delta_ts<br>";
			}
			//if($DaysBack == 0)
			//{
			$rec_found = true;
			//Compare this timestamp to the previous timestamp plus sample count
				if($delta_ts > 0)
				{
					//If greater, then a gap exists.  Calculate Pad for gap.
					for($gidx = 0;$gidx < $delta_ts;$gidx++)
					{
						$s_accum_t += $last_tval;
						$s_accum_h += $last_hval;
						$s_count++;
						if($s_count >= $DataScale)
						{
							$tt[] = $s_accum_t / $s_count;
							$ht[] = $s_accum_h / $s_count;
							$st[] = -1;
							$s_accum_t = 0;
							$s_accum_h = 0;
							$s_count = 0;
							$d_count++;
							if($d_count > $x_max)
							{
								//$rec_found = true;
								//display array is full, so stop gathering 
								break 3;
							}
						}
					}
				}
			//}
			//After initial search, set base time
			$prev_ts = $rec_ts;
			//$rec_found = true;
			
			if((strlen($rdata->Sample_Data_Record) > 10) && ($rdata->Sample_Length == ($rdata->Data_Record_Count * 10)))
			{
				//Get each record and create data arrays from raw data
				//Copyright Robert Dobrose 2011
				$line_array = array_reverse(explode("\n", $rdata->Sample_Data_Record));
				foreach($line_array as $myline)
				{
					//Sample data exists here.  Parse as CSV and load into array.
					$tmp_array = str_getcsv($myline);
					//print_r($tmp_array);
					if($tmp_array[0] == 1)
					{
						$last_tval = $tmp_array[2]/100;
						$last_hval = $tmp_array[3]/100;
						$s_accum_t += $last_tval;
						$s_accum_h += $last_hval;
						$s_count++;
						if($s_count >= $DataScale)
						{
							$tt[] = $s_accum_t / $s_count;
							$ht[] = $s_accum_h / $s_count;
							$st[] = $tmp_array[1];;
							$s_accum_t = 0;
							$s_accum_h = 0;
							$s_count = 0;
							$d_count++;
							if($d_count > $x_max)
							{
								//display array is full, so stop gathering 
								break 3;
							}
						}
					}
				} 
			}
			else 
			{
				//Bad sample record.  Pad for expected samples.
				$delta_ts = $rdata->Data_Record_Count;
				for($gidx = 0;$gidx < $delta_ts;$gidx++)
				{
					$s_accum_t += $last_tval;
					$s_accum_h += $last_hval;
					$s_count++;
					if($s_count >= $DataScale)
					{
						$tt[] = $s_accum_t / $s_count;
						$ht[] = $s_accum_h / $s_count;
						$st[] = -1;
						$s_accum_t = 0;
						$s_accum_h = 0;
						$s_count = 0;
						$d_count++;
						if($d_count > $x_max)
						{
							//display array is full, so stop gathering 
							break 3;
						}
					}
				}
			}
		}
		mysql_free_result($result);
		if($Debug > 0)
		{
			echo "Records Processed: ";
			$row_count = mysql_num_rows($result);	//Keep track of the number of records selected
			print_r($row_count);
			echo "<br>";
		}
		$rowstart += $mylimit;
	} while($row_count >= $mylimit);
}

if(!$rec_found)
{
	if($Debug > 0)
	{
		echo "No data records found.<br>";
	}
	for($idx=0;$idx < $x_max; $idx++)
	{
		$tt[] = 10*sin(deg2rad($idx)) + 75;
		$ht[] = 25*sin(deg2rad($idx*2)) + 70;
		if($tt[$idx] > 80)
		{
			$st[] = 6;
		}
		elseif($tt[$idx] > 75)
		{
			$st[] = 4;
		}
		elseif($tt[$idx] < 70)
		{
			$st[] = 1;
		}
		else 
		{
			$st[] = 0;
		}
	}
}

if($Debug >= 4)
{
	echo "Temp Array:  ";
	print_r($tt);
	echo "<br>";
}
if($Debug >= 5)
{
	echo "Humid Array:  ";
	print_r($ht);
	echo "<br>";
}

$gridfont = 4;
$scale_file = "imagetmp/myscale.png";
GraphYGrid($scale_file, $gridfont, $x_scale, $y_max, $y_maxval, $y_minval, $y_tickstep);


$im = @imagecreatetruecolor ($x_max, $y_max)
or die ("Cannot Initialize new GD graph image stream");

imagealphablending ($im, true);
imagesetthickness ($im, 3);

//$background_color = ImageColorAllocate ($im, 180, 224, 224);
//$background_color = ImageColorAllocate ($im, 135, 255, 150);
$background_color = ImageColorAllocate ($im, 200, 200, 200);
imagefill($im, 0, 0, $background_color);

//$text_color = ImageColorAllocate ($im, 233, 14, 91);
$text_color = ImageColorAllocate ($im, 0, 0, 0);
//$graph_color = ImageColorAllocate ($im,25,25,25);
$graph_color = ImageColorAllocate ($im,255,0,15);
//$graph_color2 = ImageColorAllocate ($im,0,125,10);
$graph_color2 = ImageColorAllocate ($im,0,0,130);

$graph_color_gap = ImageColorAllocate ($im,80,80,80);
$graph_color_gap2 = ImageColorAllocateAlpha ($im,150,150,150,50);

$graph_color_heat = ImageColorAllocateAlpha ($im,255,100,100,60);
$graph_color_cool = ImageColorAllocateAlpha ($im,100,150,255,80);
$graph_color_fan = ImageColorAllocateAlpha ($im,250,255,150,50);

//$q_accum = 0;
//$r_accum = 0;
//$q_count = 0;
//$decim_count = 0; 
$x1=$x_max;
$y1=0;
$rx1=$x_max;
$ry1=0;
$first_one="yes";
while(list($key, $val) = each($tt)){
	$rval = $ht[$key];
	$sflags = $st[$key];
//	$q_accum += $val;
//	$r_accum += $rval;
//	$q_count++;
//	$decim_count++;
//	if($decim_count >= $decim)
//	{
//		$decim_count = 0;
//	}
//	if($decim_count < 1)
//	{
		//Crossed decimation boundary.  Calculate new average value and reset accums.
//		$q_avg = $q_accum / $q_count;
//		$q_accum = 0;
//		$r_avg = $r_accum / $q_count;
//		$r_accum = 0;
//		$q_count = 0;
		if($val > $y_maxval)
		{
			$val = $y_maxval;
		}
		if($val < $y_minval)
		{
			$val = $y_minval;
		}
		if($rval > $y_maxval)
		{
			$rval = $y_maxval;
		}
		if($rval < $y_minval)
		{
			$rval = $y_minval;
		}
		//echo "$nt[month], $nt[sales]";
		$y2 = $y_max - (($val - $y_minval) * $y_scale); // Coordinate of Y axis
		$ry2 = $y_max - (($rval - $y_minval) * $y_scale); // Coordinate of Y axis
		$hy2 = $y_max / 2;
		//ImageString($im,2,$x2,$y2,$key,$graph_color); 
		//Line above is to print month names on the graph
		if($first_one=="no"){ // this is to prevent from starting $x1=0 and $y1=0
			$x2=$x1-$x_gap; // Shifting in X axis
			$rx2=$rx1-$x_gap; // Shifting in X axis
			if($sflags == -1)
			{
				if($y2 >= ($y_max - 10))
				{
					imagefilledrectangle($im,$x1,$y_max,$x2,$hy2,$graph_color_gap2);
					imageline ($im,$x1,$hy2,$x2,$hy2,$graph_color_gap); // Drawing the line between two points
				}
				else 
				{
					imagefilledrectangle($im,$x1,$y1,$x2,$ry2,$graph_color_gap2);
					imageline ($im,$x1,$y1,$x2,$y2,$graph_color_gap); // Drawing the line between two points
					imageline ($im,$rx1,$ry1,$rx2,$ry2,$graph_color_gap); // Drawing the line between two points
				}
			}
			else 
			{
				if($sflags & 0x01)
				{
					//Heat is On, fill Heat status color
					imagefilledrectangle($im,$x1,$y1,$x2,$y_max,$graph_color_heat);
				}
				elseif($sflags & 0x02)
				{
					//Cool is On, fill Cool status color
					imagefilledrectangle($im,$x1,$y1,$x2,$y_max,$graph_color_cool);
				}
				elseif($sflags & 0x04)
				{
					//Fan is On, fill Fan status color
					imagefilledrectangle($im,$x1,$y1,$x2,$y_max,$graph_color_fan);
				}
				imageline ($im,$x1,$y1,$x2,$y2,$graph_color); // Drawing the line between two points
				imageline ($im,$rx1,$ry1,$rx2,$ry2,$graph_color2); // Drawing the line between two points
			}
		}
		else 
		{
			$x2 = $x_max - $x_start;
			$rx2 = $x_max - $x_start; 
		}
		$x1=$x2; // Storing the value for next draw
		$y1=$y2;
		$rx1=$rx2; // Storing the value for next draw
		$ry1=$ry2;
		$first_one="no"; // Now flag is set to allow the drawing
//	}
}



//Copyright Robert Dobrose 2011
$graph_file = "imagetmp/mygraph$SID.png";
ImagePng($im, $graph_file); # store the image to file

imagedestroy($im);
// ImagePng ($im);

//Add a link bar for Days traversing.  Always add Prev Day.
echo "<TABLE><colgroup span=\"2\" width=\"70\"></colgroup><colgroup span=\"12\" width=\"55\"></colgroup><colgroup></colgroup><TR>";
$PrevDay = $DaysBack + 1;
echo "<TD><a href=\"http://50.57.99.73/devch2.php?Serial=$DeviceSN&Days=$PrevDay&Scale=$DataScale\">Prev Day</a></TD>";
if($DaysBack > 0)
{
	//Only add Next Day if Days > 0
	$NextDay = $DaysBack - 1;
	echo "<TD><a href=\"http://50.57.99.73/devch2.php?Serial=$DeviceSN&Days=$NextDay&Scale=$DataScale\">Next Day</a></TD>";
}
else 
{
	echo "<TD style=\"color:dimgray\">Next Day</TD>";
}
$ScaleDouble = ceil($DataScale * 2);
echo "<TD><a href=\"http://50.57.99.73/devch2.php?Serial=$DeviceSN&Days=$DaysBack&Scale=$ScaleDouble\">Out</a></TD>";
$ScaleUp = $DataScale + 1;
echo "<TD><a href=\"http://50.57.99.73/devch2.php?Serial=$DeviceSN&Days=$DaysBack&Scale=$ScaleUp\">Wider</a></TD>";
if($DataScale > 1)
{
	//Only add Scale Down if Scale > 1
	$ScaleDown = $DataScale - 1;
	echo "<TD><a href=\"http://50.57.99.73/devch2.php?Serial=$DeviceSN&Days=$DaysBack&Scale=$ScaleDown\">Tighter</a></TD>";
	$ScaleHalf = ceil($DataScale / 2);
	echo "<TD><a href=\"http://50.57.99.73/devch2.php?Serial=$DeviceSN&Days=$DaysBack&Scale=$ScaleHalf\">In</a></TD>";
}
else 
{
	echo "<TD style=\"color:dimgray\">Tighter</TD>";
	echo "<TD style=\"color:dimgray\">In</TD>";
}

echo "<TD><a href=\"http://50.57.99.73/devch2.php?Serial=$DeviceSN&Days=$DaysBack&Scale=4\">1 Hour</a></TD>";
echo "<TD><a href=\"http://50.57.99.73/devch2.php?Serial=$DeviceSN&Days=$DaysBack&Scale=36\">10 Hour</a></TD>";
//echo "</TD><TD><a href=\"http://50.57.99.73/devch2.php?Serial=$DeviceSN&Days=$DaysBack&Scale=44\">12 Hour</a></TD>";
echo "<TD><a href=\"http://50.57.99.73/devch2.php?Serial=$DeviceSN&Days=$DaysBack&Scale=87\">24 Hour</a></TD>";
echo "<TD><a href=\"http://50.57.99.73/devch2.php?Serial=$DeviceSN&Days=$DaysBack&Scale=605\">Week</a></TD>";
//echo "<TD><a href=\"http://50.57.99.73/devch2.php?Serial=$DeviceSN&Days=$DaysBack&Scale=2679\">Month</a></TD>";
echo "<TD></TD>";
echo "<TD></TD>";
echo "<TD></TD>";
echo "<TD></TD>";

//echo "<style align=right>";
// *** echo "  <a class=\"linkinfo\" href=\"http://50.57.99.73/custsel.php\">Show Efficiency Chart</a>";
//echo "</style>";
echo "</TD></TR></TABLE>";
//Copyright Robert Dobrose 2011
//Start new table row for graph
echo "<TABLE><TR>";	//Start a table cell
echo "<TD><img WIDTH=$x_scale HEIGHT=$y_max src=$scale_file type=image/png></TD>";
echo "<TD><img WIDTH=$x_max HEIGHT=$y_max src=$graph_file type=image/png></TD>";
echo "</TR></TABLE>";	//Finish table

mysql_close($db);

require("template_bottom.inc"); 


?>
