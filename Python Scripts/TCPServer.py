#!/usr/bin/python


# simple illustration client/server pair; client program sends a string
# to server, which echoes it back to the client (in multiple copies),
# and the latter prints to the screen

# this is the server

import cgi,cgitb,socket,sys
cgitb.enable()

# TCP server example
server_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
server_socket.bind(("", 999))
server_socket.listen(5)

print "TCPServer Waiting for client on port 999"

while 1:
	client_socket, address = server_socket.accept()
	print "I got a connection from ", address
	
	data = client_socket.recv(512)
		
	#now send back
	print "RECEIVED:" , data
	client_socket.send(data)
	client_socket.close()
		