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

#----------------------#
# Main Body            #
#----------------------#

$db = mysql_connect($host,$user,$pw)
        or die("Cannot connect to MySQL.");
mysql_select_db($database,$db)
        or die("Cannot connect to database.");

$form_name = "Zeus Grid Customer Select";

require("template_top.inc");
 
$table_name = 'Customer_Information';
$command = "select * from ".$table_name;
$result = mysql_query($command);
echo "<form method='GET' action='custshow.php'>";
echo "<TABLE><TR><TD>Select a Customer</TD>";
echo "<TD><select name='Customer'>";
while ($data = mysql_fetch_object($result)) {
   echo "<option value=$data->ZeusCustomer_ID>".$data->Customer_Name.": ".$data->Customer_Address."</option>";
}
echo "</select></TD></TR>";
?>

<tr>
<td colspan="2" align="center">
<input type="submit" value="SUBMIT" />
</td></tr>
</table><br>
</form>

<?php
//Copyright Robert Dobrose 2011

mysql_close($db);

require("template_bottom.inc"); 

?>

