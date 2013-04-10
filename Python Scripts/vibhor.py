#!/usr/bin/env python


#wake up libraries - its show time
import sys,cgi,cgitb,MySQLdb
cgitb.enable()



#get the POST variables
form = cgi.FieldStorage()
Record_ID = form.getvalue('Record_ID')
Record_Type_Of_Group = form.getvalue('Record_Type_Of_Group')
Record_TimeStamp = form.getvalue('Record_TimeStamp')
Record_Length_Or_Period = form.getvalue('Record_Length_Or_Period')
Customer_Zeus_Product_Product_SN = form.getvalue('Customer_Zeus_Product_Product_SN')


#database connection
try:
	conn = MySQLdb.connect(host = "localhost",user = "root",passwd = "zg0001chicago",db = "mydb")

except MySQLdb.Error, e:
     print "Error %d: %s" % (e.args[0], e.args[1])
     sys.exit (1)


#insert in to database
try:
	cursor = conn.cursor ()
#qt ="SELECT VERSION()"
	query = "INSERT INTO `mydb`.`Product_Record` (`Record_ID` ,`Record_Type_Of_Group` ,`Record_TimeStamp` ,`Record_Length_Or_Period` ,`Customer_Zeus_Product_Product_SN`) VALUES ('%s','%s','%s','%s','%s')" % (Record_ID, Record_Type_Of_Group,Record_TimeStamp,Record_Length_Or_Period,Customer_Zeus_Product_Product_SN)
	cursor.execute(query)	

#row = cursor.fetchone ()
	print "Number of rows updated: %d" % cursor.rowcount
	cursor.close ()

except MySQLdb.Error, e:
     print "Error %d: %s" % (e.args[0], e.args[1])
     sys.exit (1)

#finalize data (for innodb)
conn.commit ()
conn.close ()

#now you can show whatever you want

#print "Content-Type: text/html"
#print
#print """\
#<html>
#<head><title>ZeusDude</title></head>
#<body>
#<h2>Hello Dude ! </h2>
#"""
#print "<h2>Record_ID: %s</h2>" % (Record_ID)
#print "<h2>Record_Type_Of_Group: %s</h2>" % (Record_Type_Of_Group)
#print "<h2>Record_TimeStamp: %s</h2>" % (Record_TimeStamp)
#print "<h2>Record_Length_Or_Period: %s</h2>" % (Record_Length_Or_Period)
#print "<h2>Customer_Zeus_Product_Product_SN: %s</h2>" % (Customer_Zeus_Product_Product_SN)
#print "server version:", row[0]
#print """\
#</body>
#</html>
#"""

