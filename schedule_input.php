<?php
echo "Hello World!!";
?>

<html>
<body>

<table width="500" border="0">
<tr>
<td colspan="2" style="background-color:#FFA500;">
<h1>User Profile On The Web</h1>
</td>
</tr>

<tr valign="top">
<td style="background-color:#FFD700;width:100px;text-align:top;">
<b>Menu</b><br />
HTML<br />
CSS<br />
JavaScript
</td>
<td style="background-color:#eeeeee;height:200px;width:400px;text-align:top;">
<table width="400" border="1" td colspan="2">
<FORM  METHOD ="post" ACTION = "InsertUserProfileData.php">
<tr> 
<td>thermostat SN </td>
<td><INPUT TYPE = "text" SIZE = 25 NAME = "thermSN"> </td>
</tr>
<tr>
<td>schedule</td>
<td>
<table>
<tr><td><INPUT TYPE = "text" SIZE = 25 NAME = "rtc_time" VALUE ="rtc_time" ></td></tr> 
<tr><td><INPUT TYPE = "text" SIZE = 5 NAME = "week_day" VALUE ="week_day" ></td></tr> 
<tr><td><INPUT TYPE = "text" SIZE = 5 NAME = "therm_action" VALUE ="therm_action"></td></tr> 
<tr><td><INPUT TYPE = "text" SIZE = 5 NAME = "flag_value1" VALUE ="flag_value1"></td></tr> 
<tr><td><INPUT TYPE = "text" SIZE = 5 NAME = "flag_value2" VALUE ="flag_value2"></td></tr> 
</table>


</td>
<tr>
<td colspan="2" style="background-color:#FFA500;text-align:center;">
Copyright © 2012 FORTITUD INC</td>
</tr>
</table>

</body>
</html>
