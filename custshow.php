<?php
//Copyright Robert Dobrose 2011

#----------------------#
# User Variables       #
#----------------------#

$host = "localhost:3306";  //This is the server where your database resides
//$host = "50.57.99.73:3306";  //This is the server where your database resides
$user = "root";           //This should be your database login
$pw = 'HXtmmPyuWI1l6pDE3l6V'; //This should be your database password
$database = "Home_db";        //This should be your database name

$CustomerID = $_GET['Customer'];
//$CustomerID = 2;

$db = mysql_connect($host,$user,$pw)
        or die("Cannot connect to MySQL.");
mysql_select_db($database,$db)
        or die("Cannot connect to database.");

$form_name = "Zeus Grid Customer Information";
require("template_top.inc");

//print_r($CustomerID);
session_start(); 
$SID = session_id();
$_SESSION['sid'] = $SID;
echo "Session Info: ";
print_r($_SESSION);
echo "<br>";

//$table_name = 'Customer_Information';
$command = "select DISTINCT * from Customer_Information where ZeusCustomer_ID = ".$CustomerID;
$result = mysql_query($command);
$cdata = mysql_fetch_object($result);

echo "<TABLE><TR><TD class='hilite1'>$cdata->Customer_Name</TD>";
//echo "<TD>$cdata->Customer_Address</TD></TR>";

$command = "select * from Customer_House where Customer_Information_ZeusCustomer_ID = ".$CustomerID;
$lresult = mysql_query($command);
while ($ldata = mysql_fetch_object($lresult)) {
	//Format each location connected to the specified customer
	print "<TR><TD></TD><TD class='hilite2'>".$ldata->House_Or_Unit_No." ".$ldata->Street_Name."</TD><TD>".$ldata->CityName."</TD></TR>\n";
	//print "<TR><TD></TD><TD>".$ldata->House_Or_Unit_No." ".$ldata->Street_Name."</TD><TD>".$ldata->CityName."</TD></TR>\n";
	$command = "select * from Customer_Zeus_Product where Customer_House_Customer_Location_Id = ".$ldata->Customer_Location_Id;
	$presult = mysql_query($command);
	while ($pdata = mysql_fetch_object($presult)) {
		//Format a line for each device at a location
		print "<TR><TD></TD><TD></TD>";
		print "<TD class='hilite3'>".$pdata->Product_Type."</TD>";
		$DeviceSN = $pdata->Product_SN;
		if($pdata->Product_Type == "Thermostat")
		{
			echo "<TD><a href=\"http://50.57.99.73/devchart.php?Serial=$DeviceSN&Days=0\">$DeviceSN</a></TD>";
		}
		else 
		{
			print "<TD>$DeviceSN</TD>";
		}
		print "<TD class='hilite4'>".$pdata->Product_SW_Ver."</TD>";
		print "</TR>\n";
		$command = "SELECT Object_Name, Object_Value, Object_Type, Object_Precision "
    			."FROM Product_Value_Objects "
				."WHERE Value_Owner_Product_SN=".$pdata->Product_SN;
		$vresult = mysql_query($command);
		while ($vdata = mysql_fetch_object($vresult)) {
			//format data for device
			print "<TR><TD></TD><TD></TD><TD></TD>";
			print "<TD class='hilite5'>".$vdata->Object_Name."</TD>";
			if($vdata->Object_Type == 'Int')
			{
				$outval = sprintf("%u", $vdata->Object_Value);
			}
			elseif ($vdata->Object_Type == 'Float')
			{
				$valformat = sprintf("%%01.%uf", $vdata->Object_Precision);
				$outval = sprintf($valformat, $vdata->Object_Value);
			}
			print "<TD class='hilite6'>$outval</TD>";
		}
		
	}  
	 
}

//Copyright Robert Dobrose 2011
echo "</TABLE>";

mysql_close($db);	
require("template_bottom.inc"); 

?>
