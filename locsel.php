<?php
//Copyright Robert Dobrose 2011

#----------------------#
# User Variables       #
#----------------------#

$host = "localhost:3306";  //This is the server where your database resides
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

//print_r($CustomerID);

//$table_name = 'Customer_Information';
$command = "select DISTINCT * from Customer_Information where ZeusCustomer_ID = ".$CustomerID;
$result = mysql_query($command)
		or die("Cannot find customer record.");
$cdata = mysql_fetch_object($result);

$command = "select * from Customer_House where Customer_Information_ZeusCustomer_ID = ".$CustomerID;
$lresult = mysql_query($command)
		or die("No Locations Available for Customer.");
if(mysql_num_rows($lresult) > 1)
{
	//More than one location available for customer.
	require("template_top.inc");
	echo "<TABLE><TR><TD>$cdata->Customer_Name</TD>";
	//echo "<TD>$cdata->Customer_Address</TD></TR>";
echo "<form method='GET' action='custshow.php'>";
echo "<TABLE><TR><TD>Select a Customer</TD>";
echo "<TD><select name='Customer'>";
while ($data = mysql_fetch_object($result)) {
   echo "<option value=$data->ZeusCustomer_ID>".$data->Customer_Name.": ".$data->Customer_Address."</option>";
}
echo "</select></TD></TR>";
	
	
	
	while ($ldata = mysql_fetch_object($lresult)) {
		//Load a drop-down select field with the available location selections
		
		//Format each location connected to the specified customer
		print "<TR><TD></TD><TD>".$ldata->House_Or_Unit_No." ".$ldata->Street_Name."</TD><TD>".$ldata->CityName."</TD></TR>\n";
		$command = "select * from Customer_Zeus_Product where Customer_House_Customer_Location_Id = ".$ldata->Customer_Location_Id;
		$presult = mysql_query($command);
		while ($pdata = mysql_fetch_object($presult)) {
			//Format a line for each device at a location
			print "<TR><TD></TD><TD></TD>";
			print "<TD>".$pdata->Product_Type."</TD>";
			print "<TD>".$pdata->Product_SN."</TD>";
			print "<TD>".$pdata->Product_SW_Ver."</TD>";
			print "</TR>\n";
			$command = "SELECT Object_Name, Object_Value, Object_Type, Object_Precision "
	    			."FROM Product_Value_Objects "
					."WHERE Value_Owner_Product_SN=".$pdata->Product_SN;
			$vresult = mysql_query($command);
			while ($vdata = mysql_fetch_object($vresult)) {
				//format data for device
				print "<TR><TD></TD><TD></TD><TD></TD>";
				print "<TD>".$vdata->Object_Name."</TD>";
				if($vdata->Object_Type == 'Int')
				{
					$outval = sprintf("%u", $vdata->Object_Value);
				}
				elseif ($vdata->Object_Type == 'Float')
				{
					$valformat = sprintf("%%01.%uf", $vdata->Object_Precision);
					$outval = sprintf($valformat, $vdata->Object_Value);
				}
				print "<TD>$outval</TD>";
			}
	    }    
	}

	
//Copyright Robert Dobrose 2011
	echo "</TABLE>";
	mysql_close($db);
	require("template_bottom.inc"); 
}
else 
{
	//Only one location for customer, so choose it and skip to device select
	mysql_close($db);
	//Redirect to next PHP script with single location info
}

?>
