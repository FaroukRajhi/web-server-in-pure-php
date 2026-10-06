<?php
declare(strict_types=1);

// Configuration
$host = '0.0.0.0';
$port = 8080;
$documentRoot = __DIR__ . '/public';

// Step 1: Create a TCP socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    die("socket_create failed: " . socket_strerror(socket_last_error()) . "\n");
}

// Step 2: Allow reuse of the address (avoids "address already in use")
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

// Step 3: Bind the socket to a host:port
if (!socket_bind($socket, $host, $port)) {
    die("socket_bind failed: " . socket_strerror(socket_last_error($socket)) . "\n");
}

// Step 4: Start listening for connections
if (!socket_listen($socket, 5)) {
    die("socket_listen failed: " . socket_strerror(socket_last_error($socket)) . "\n");
}

echo "Server running on http://{$host}:{$port}\n";

// Ensure the public directory exists
if (!is_dir($documentRoot)) {
    mkdir($documentRoot, 0755, true);
}

// Step 5: Accept connections in an infinite loop
while (true) {
    // Block until a client connects
    $client = @socket_accept($socket);
    if ($client === false) {
        continue;
    }

    // Read the HTTP request (up to 8 KB)
    $request = '';
    while ($chunk = socket_read($client, 8192)) {
        $request .= $chunk;
        // Headers end with \r\n\r\n — stop reading once we have them
        if (strpos($request, "\r\n\r\n") !== false) {
            break;
        }
    }

    if ($request === '') {
        socket_close($client);
        continue;
    }

    // Log request line (first line)
    $firstLine = strtok($request, "\r\n");
    echo "[" . date('H:i:s') . "] {$firstLine}\n";

    // Parse the request line: "GET /path HTTP/1.1"
    $parts = explode(' ', $firstLine);
    $method = $parts[0] ?? 'GET';
    $uri    = $parts[1] ?? '/';

    // Strip query string and decode
    $path = parse_url($uri, PHP_URL_PATH);
    $path = urldecode($path ?? '/');

    // Prevent directory traversal (../)
    $safePath = str_replace(['..', "\0"], '', $path);
    $filePath = $documentRoot . $safePath;

    // If path is a directory, append index.html
    if (is_dir($filePath)) {
        $filePath = rtrim($filePath, '/') . '/index.html';
    }

    // Step 6: Build the response
    if ($method !== 'GET' && $method !== 'HEAD') {
        $body = "<h1>405 Method Not Allowed</h1>";
        $response = "HTTP/1.1 405 Method Not Allowed\r\n"
                  . "Content-Type: text/html\r\n"
                  . "Content-Length: " . strlen($body) . "\r\n"
                  . "Connection: close\r\n\r\n"
                  . $body;
    } elseif (is_file($filePath)) {
        $body = file_get_contents($filePath);
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';

        $response = "HTTP/1.1 200 OK\r\n"
                  . "Content-Type: {$mime}\r\n"
                  . "Content-Length: " . strlen($body) . "\r\n"
                  . "Connection: close\r\n\r\n"
                  . ($method === 'HEAD' ? '' : $body);
    } else {
        $body = "<h1>404 Not Found</h1><p>{$safePath} was not found.</p>";
        $response = "HTTP/1.1 404 Not Found\r\n"
                  . "Content-Type: text/html\r\n"
                  . "Content-Length: " . strlen($body) . "\r\n"
                  . "Connection: close\r\n\r\n"
                  . $body;
    }

    // Step 7: Send and close
    socket_write($client, $response, strlen($response));
    socket_close($client);
}

// Never reached, but good practice
socket_close($socket);