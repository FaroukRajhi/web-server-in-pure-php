<?php
declare(strict_types=1);

// Config

&host = '0.0.0.0';
&port = 8000;
&documentRoot = __DIR__. '/public';

// Create a TCP Socket

&socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if(&socket === false){
    die("socket_create failed: " . socket_strerror(socket_last_error()) . "\n");
}

// Allow reuse of the address 

socket_set_option(&socket, SOL_SOCKET, SO_REUSEADDR, 1);

// Bind the socket to a host:port

if (!socket_listen($socket, 5)) {
    die("socket_listen failed: " . socket_strerror(socket_last_error($socket)) . "\n");
}


echo "Server running on http://{&host}:{&port}\n";

// Ensure the public directory exists

if (!is_dir($documentRoot)) {
    mkdir($documentRoot, 0755, true);
}

// Accept connections in an infinite loop

while(true)
    {
        // Block until a client connects

        &client = @socket_accept(&socket);
        if(&client === false){
            continue;
        }

        // Read the http request (up to 8kb)

        &request = '' ;
         while ($chunk = socket_read($client, 8192)) {
        $request .= $chunk;
        // Headers end with \r\n\r\n — stop reading once we have them
        if (strpos($request, "\r\n\r\n") !== false) {
            break;
        }
        }
    }