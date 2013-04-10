#!/usr/bin/env python

import sys

zip_code = sys.argv[1]

url = "http://forecast.weather.gov/MapClick.php?lat=%s&lon=%s&FcstType=dwml"

lat = ""
lon = ""

import MySQLdb
con = MySQLdb.connect(host="localhost", user="root", passwd="HXtmmPyuWI1l6pDE3l6V", db="Home_db")
cur = con.cursor()

try:
	cur.execute("SELECT latitude, longitude FROM Zip_Timezone where zip='%s'" % zip_code)
	result = cur.fetchall()

	if result:
		lat = str(result[0][0])
		lon = str(result[0][1])
except:
	pass

if not (lat and lon):
	print ("Could not find zip code location.")
	sys.exit(1)

print "lat="+lat
print "lon="+lon

import urllib
url_handle = urllib.urlopen(url % (lat,lon))
weather_page = url_handle.read()

import libxml2
doc = libxml2.parseDoc(weather_page)
ctx = doc.xpathNewContext()
res = ctx.xpathEval('//data[@type="current observations"]/parameters/temperature[@type="apparent"]/value/text()')
if not res:
	print ("temp_f=unknown")
else:
	print ("temp_f="+res[0].getContent())

res = ctx.xpathEval('//data[@type="current observations"]/parameters/humidity[@type="relative"]/value/text()')
if not res:
	print ("humidity=unknown")
else:
	print ("humidity="+res[0].getContent())

res = ctx.xpathEval('//data[@type="current observations"]/parameters/conditions-icon/icon-link/text()')
if not res:
	print ("weather_icon=unknown")
else:
	url = res[0].getContent()
	filename = url.split("/")[-1]  # get the filename part from url
	name = filename.split(".")[0]  # remove the .jpg part from filename
	print ("weather_icon="+name)

