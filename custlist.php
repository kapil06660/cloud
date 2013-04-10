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
$table_name = 'Customer_Information';

#----------------------#
# Main Body            #
#----------------------#

$db = mysql_connect($host,$user,$pw)
        or die("Cannot connect to MySQL.");
mysql_select_db($database,$db)
        or die("Cannot connect to database.");

$form_name = "Zeus Grid Customer List";

require("template_top.inc");
 
?>
  

<TABLE BORDER="1">
<TR><TD>Name</TD><TD>Address</TD></TR>

<?
$command = "select * from ".$table_name;
$result = mysql_query($command);
while ($data = mysql_fetch_object($result)) {
    print "<TR><TD>".$data->Customer_Name."</TD><TD>".$data->Customer_Address."</TD></TR>\n";
}

mysql_close($db);

?>

</TABLE>
<br>
<?
   require("template_bottom.inc"); 
   //require($_SERVER['DOCUMENT_ROOT']."/Lesson12/template_bottom.inc"); 
?>

