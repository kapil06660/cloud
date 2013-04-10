<?php
//Copyright Robert Dobrose 2011

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
//	$command = "SELECT Customer_Name FROM Customer_Information "
//				."WHERE (ZeusCustomer_ID=(SELECT Customer_Information_ZeusCustomer_ID FROM Customer_House "
//				."WHERE (Customer_Location_Id=(SELECT Customer_House_Customer_Location_Id "
//				."FROM Customer_Zeus_Product WHERE Product_SN='$DeviceSN'))))";
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
if($DaysBack == 0)
{
	$Page_Refresh = 1;
}
require("template_top.inc");

//$qt=array(65,68,72,78,83,80,77,74,75,77,79,81);
//$qt=array(50,93,65,82,75,80,77,74,75,77,79,81);
//$rt=array(65,68,72,70,67,65,69,70,73,72,74,77);
//$qt=array(50,93,65,82,75,80,77,74,75,77,79,81);
//$rt=array(65,68,72,70,67,65,69,70,73,72,74,77);
//header ("Content-type: image/png");
$rec_found = false;
//print_r($qt);
//print_r($rt);
$mylimit = 500;
$rowstart = 0;
$qt = array(); 
$rt = array();
$st = array();

//header('Content-Disposition: Attachment;filename=image.png'); 

if($DeviceSN != 0)
{
	do 
	{
		//Query for a limited set of data records to avoid memory overflows
		//Do multiple queries while there are still records in the dataset
		$table_name = 'Product_Raw_Data_Sample';
		//    $sql = "SELECT Sample_Timestamp, Object_Value, Object_Type, Object_Precision "
		//    	."FROM $table_name "
		//		."WHERE (Customer_Zeus_Product_Product_SN='$DeviceSN' and External_Update=1)";
		$command = "SELECT Sample_Timestamp,Sample_Length,Sample_Data_Record,Data_Record_Count,Product_Data_Sample_Id "
		    	."FROM $table_name "
				."WHERE (Customer_Zeus_Product_Product_SN='$DeviceSN' "
				."and Sample_Timestamp >= CAST(DATE_SUB(CURDATE(), INTERVAL '$DaysBack' DAY) as DATETIME) "
				."and Sample_Timestamp < DATE_ADD(CAST(DATE_SUB(CURDATE(), INTERVAL '$DaysBack' DAY) as DATETIME), INTERVAL 24 HOUR)) "
				."LIMIT $rowstart,$mylimit";
		//		."ORDER BY Sample_Timestamp";
		if($Debug >= 2)
		{
			echo "SQL: ";
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
				echo "<br><br>";
			}
			//Get each record and create data arrays from raw data
			if((strlen($rdata->Sample_Data_Record) > 10) && ($rdata->Sample_Length == ($rdata->Data_Record_Count * 10)))
			{
				//Copyright Robert Dobrose 2011
				$line_array = explode("\n", $rdata->Sample_Data_Record);
				foreach($line_array as $myline)
				{
					//Sample data exists here.  Parse as CSV and load into array.
					$tmp_array = str_getcsv($myline);
					//print_r($tmp_array);
					if($tmp_array[0] == 1)
					{
						$st[] = $tmp_array[1];
						$qt[] = $tmp_array[2]/100;
						$rt[] = $tmp_array[3]/100;
					}
				} 
			}
		}
		mysql_free_result($result);
		if($Debug)
		{
			echo "Records Processed: ";
			$row_count = mysql_num_rows($result);	//Keep track of the number of records selected
			print_r($row_count);
			echo "<br>";
		}
		$rec_found = true;
		$rowstart += $mylimit;
	} while($row_count >= $mylimit);
}

if(!$rec_found)
{
	if($Debug)
	{
		echo "No data records found.<br>";
	}
	for($idx=0;$idx < $x_max; $idx++)
	{
		$qt[] = 10*sin(deg2rad($idx)) + 75;
		$rt[] = 25*sin(deg2rad($idx*2)) + 70;
		if($qt[$idx] > 80)
		{
			$st[] = 6;
		}
		elseif($qt[$idx] > 75)
		{
			$st[] = 4;
		}
		elseif($qt[$idx] < 70)
		{
			$st[] = 1;
		}
		else 
		{
			$st[] = 0;
		}
	}
	//$qt=array(50,93,65,82,75,80,77,74,75,77,79,81);
	//$rt=array(65,68,72,70,67,65,69,70,73,72,74,77);
	//$st=array(0,0,2,2,0,1,1,0,0,4,4,0);	
}

if($Debug >= 4)
{
	echo "Temp Array:  ";
	print_r($qt);
	echo "<br>";
}
if($Debug >= 5)
{
	echo "Humid Array:  ";
	print_r($rt);
	echo "<br>";
}

$decim = sizeof($qt)/$x_max;
if($decim < 1)
{
	$decim = 1;
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

$graph_color_heat = ImageColorAllocateAlpha ($im,255,100,100,60);
$graph_color_cool = ImageColorAllocateAlpha ($im,100,150,255,80);
$graph_color_fan = ImageColorAllocateAlpha ($im,250,255,150,50);

$q_accum = 0;
$r_accum = 0;
$q_count = 0;
$decim_count = 0; 
$x1=0;
$y1=0;
$rx1=0;
$ry1=0;
$first_one="yes";
while(list($key, $val) = each($qt)){
	$rval = $rt[$key];
	$sflags = $st[$key];
	$q_accum += $val;
	$r_accum += $rval;
	$q_count++;
	$decim_count++;
	if($decim_count >= $decim)
	{
		$decim_count = 0;
	}
	if($decim_count < 1)
	{
		//Crossed decimation boundary.  Calculate new average value and reset accums.
		$q_avg = $q_accum / $q_count;
		$q_accum = 0;
		$r_avg = $r_accum / $q_count;
		$r_accum = 0;
		$q_count = 0;
		if($q_avg > $y_maxval)
		{
			$q_avg = $y_maxval;
		}
		if($q_avg < $y_minval)
		{
			$q_avg = $y_minval;
		}
		if($r_avg > $y_maxval)
		{
			$r_avg = $y_maxval;
		}
		if($r_avg < $y_minval)
		{
			$r_avg = $y_minval;
		}
		//echo "$nt[month], $nt[sales]";
		$y2 = $y_max - (($q_avg - $y_minval) * $y_scale); // Coordinate of Y axis
		$ry2 = $y_max - (($r_avg - $y_minval) * $y_scale); // Coordinate of Y axis
		//ImageString($im,2,$x2,$y2,$key,$graph_color); 
		//Line above is to print month names on the graph
		if($first_one=="no"){ // this is to prevent from starting $x1=0 and $y1=0
			$x2=$x1+$x_gap; // Shifting in X axis
			$rx2=$rx1+$x_gap; // Shifting in X axis
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
		else 
		{
			$x2 = $x_start;
			$rx2 = $x_start; 
		}
		$x1=$x2; // Storing the value for next draw
		$y1=$y2;
		$rx1=$rx2; // Storing the value for next draw
		$ry1=$ry2;
		$first_one="no"; // Now flag is set to allow the drawing
	}
}



//Copyright Robert Dobrose 2011
$graph_file = "imagetmp/mygraph$SID.png";
ImagePng($im, $graph_file); # store the image to file

imagedestroy($im);
// ImagePng ($im);

echo "<TABLE><TR>";	//Start a table cell
//Add a link bar for Days traversing.  Always add Prev Day.
$PrevDay = $DaysBack + 1;
echo "<TD><TD><a href=\"http://50.57.99.73/devchart.php?Serial=$DeviceSN&Days=$PrevDay\">Prev Day</a>";
if($DaysBack > 0)
{
	//Only add Next Day if Days > 0
	$NextDay = $DaysBack - 1;
	echo "  <a href=\"http://50.57.99.73/devchart.php?Serial=$DeviceSN&Days=$NextDay\">Next Day</a>";
}
//echo "<style align=right>";
// *** echo "  <a class=\"linkinfo\" href=\"http://50.57.99.73/custsel.php\">Show Efficiency Chart</a>";
//echo "</style>";
echo "</TR><TR>";
//Copyright Robert Dobrose 2011
//Start new table row for graph
echo "<TD><img WIDTH=$x_scale HEIGHT=$y_max src=$scale_file type=image/png></TD>";
echo "<TD><img WIDTH=$x_max HEIGHT=$y_max src=$graph_file type=image/png></TD>";
echo "</TR></TABLE>";	//Finish table

mysql_close($db);

require("template_bottom.inc"); 


?>
