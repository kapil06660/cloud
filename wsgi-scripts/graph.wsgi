
def avg_value(data_list, index):
	sum = 0.0
	count = 0
	for row in data_list:
		rs = row.split(",")
		if len(rs) > index:
			sum += float(rs[index])/100
			count += 1
	if count > 0:
		return sum/count
	return None

def set_timeticks(time_arr):
	if not time_arr: return
	from datetime import datetime
	import matplotlib.pyplot as plt
	min_time = min(time_arr)
	max_time = max(time_arr)
	min_dt = datetime.fromtimestamp(min_time)
	if max_time - min_time > 3600*12: # Greater than 12 hours, tick every 3 hours
		if min_dt.hour % 3 == 0 and min_dt.minute == 0 and min_dt.second == 0:
			hour_next = min_dt.hour
		else:
			hour_next = (int(min_dt.hour/3)+1) * 3
		sec_diff = (hour_next - min_dt.hour)*3600 - min_dt.minute*60 - min_dt.second
		first_tick = min_time + sec_diff
		tick_diff = 3600*3
	elif max_time - min_time > 3600*3: # Greater than 3 hours, tick every hour
		if min_dt.minute == 0 and min_dt.second == 0:
			hour_next = min_dt.hour
		else:
			hour_next = min_dt.hour+1
		sec_diff = (hour_next - min_dt.hour)*3600 - min_dt.minute*60 - min_dt.second
		first_tick = min_time + sec_diff
		tick_diff = 3600
	else: # Tick every 15 minutes
		if min_dt.minute % 15 == 0 and min_dt.second == 0:
			minute_next = min_dt.minute
		else:
			minute_next = (int(min_dt.hour/15)+1) * 15
		sec_diff = (minute_next - min_dt.minute)*60 - min_dt.second
		first_tick = min_time + sec_diff
		tick_diff = 60*15
	tick_ts = []
	tick_label = []
	tick = first_tick
	while tick <= max_time:
		tick_ts.append(tick)
		dt = datetime.fromtimestamp(tick)
		tick_label.append(dt.strftime("%H:%M"))
		tick += tick_diff
	ax = plt.gca()
	ax.set_xticks(tick_ts)
	ax.set_xticklabels(tick_label)
	max_dt = datetime.fromtimestamp(max_time)
	xlabel_str = "Time: " + min_dt.strftime("%h %d, %H:%M") + " to " + max_dt.strftime("%h %d, %H:%M")
	plt.xlabel(xlabel_str)


def application(environ, start_response):
	import MySQLdb
	import time
	from datetime import datetime

	import matplotlib
	matplotlib.use('Agg')
	import matplotlib.pyplot as plt

	import random
	from cgi import parse_qs, escape

	d = parse_qs(environ['QUERY_STRING'])

	serial = d.get('SERIAL', ['1231231'])[0] # TODO handle no serial present
	from_dt = d.get('FROM_DT', [''])[0]
	duration = int(d.get('DURATION', [24])[0])

	con = MySQLdb.connect(host="50.57.99.73", user="root", passwd="HXtmmPyuWI1l6pDE3l6V", db="Home_db")

	cur = con.cursor()

	sql_query = "SELECT Sample_Timestamp,Sample_Length,Sample_Data_Record,Data_Record_Count,Product_Data_Sample_Id " \
		    "FROM Product_Raw_Data_Sample WHERE '%s'<Sample_Timestamp " \
		    "AND Sample_Timestamp<ADDTIME('%s','%d:00:00') " \
		    "AND Customer_Zeus_Product_Product_SN=%s " % (from_dt, from_dt, duration, serial)

	cur.execute(sql_query)
	time_arr = []
	itemp_arr = []
	otemp_arr = []
	while True:
		row = cur.fetchone()
		if not row: break
		ts_datetime = row[0]
		ts = time.mktime(ts_datetime.timetuple())
		sample_data = row[2].split("\n")
		itemp_avg = avg_value(sample_data, 2)
		if itemp_avg is None: continue
		otemp_avg = avg_value(sample_data, 4)
		if otemp_avg is None: continue
		#print ts, itemp_avg, otemp_avg
		#otemp_avg = otemp_avg + (random.random()*2 - 1)  # TODO remove
		time_arr.append(ts)
		itemp_arr.append(itemp_avg)
		otemp_arr.append(otemp_avg)

	cur.close()
	con.close()
 
	set_timeticks(time_arr)
	#plt.ylim(0, 100)
	plt.plot(time_arr, itemp_arr)
	plt.plot(time_arr, otemp_arr)
	plt.ylabel("Temperature")

	import cStringIO
	fig_str_file = cStringIO.StringIO()
	plt.savefig(fig_str_file)
	plt.close('all')

	status = '200 OK'
	output = fig_str_file.getvalue()

	response_headers = [('Content-type', 'image/png'),
			    ('Content-Length', str(len(output)))]
	start_response(status, response_headers)

	return [output]

