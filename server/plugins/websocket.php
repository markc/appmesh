<?php
/**
 * AppMesh WebSocket Plugin - Bidirectional Gateway
 *
 * Provides WebSocket connectivity for real-time communication between:
 * - Browser-based UIs
 * - External tools and services
 * - Streaming data from desktop events
 *
 * This plugin provides tools to:
 * - Start/stop a WebSocket server
 * - Send messages to connected clients
 * - Manage the gateway lifecycle
 *
 * The actual WebSocket server runs as a separate process.
 */

// ============================================================================
// WebSocket Gateway Management
// ============================================================================

const WS_PID_FILE = '/tmp/appmesh-websocket.pid';
const WS_PORT_DEFAULT = 8765;

/**
 * Get the WebSocket server script path
 */
function ws_server_script(): string
{
    return __DIR__ . '/../scripts/websocket-server.php';
}

/**
 * Check if WebSocket server is running
 */
function ws_is_running(): array
{
    if (!file_exists(WS_PID_FILE)) {
        return ['running' => false];
    }

    $data = file_get_contents(WS_PID_FILE);
    [$pid, $port] = explode("\n", $data) + [null, WS_PORT_DEFAULT];
    $pid = (int) $pid;
    $port = (int) $port;

    // Check if process is actually running
    if ($pid > 0 && file_exists("/proc/{$pid}")) {
        return [
            'running' => true,
            'pid' => $pid,
            'port' => $port,
        ];
    }

    // Stale PID file
    @unlink(WS_PID_FILE);
    return ['running' => false];
}

/**
 * Send a message to the WebSocket server's control socket
 */
function ws_send_control(string $message, int $port): string
{
    // The WebSocket server listens on a Unix socket for control messages
    $controlSocket = "/tmp/appmesh-ws-control.sock";

    if (!file_exists($controlSocket)) {
        return "Error: Control socket not found. Is the WebSocket server running?";
    }

    $socket = @stream_socket_client("unix://{$controlSocket}", $errno, $errstr, 5);

    if (!$socket) {
        return "Error: Cannot connect to control socket: {$errstr}";
    }

    fwrite($socket, $message . "\n");
    $response = fgets($socket);
    fclose($socket);

    return trim($response ?: 'No response');
}

// ============================================================================
// Tool Definitions
// ============================================================================

return [
    // -------------------------------------------------------------------------
    // Server Management
    // -------------------------------------------------------------------------
    'appmesh_ws_status' => new Tool(
        description: 'Check WebSocket gateway server status',
        inputSchema: schema(),
        handler: function (array $args): string {
            $status = ws_is_running();

            if ($status['running']) {
                $port = $status['port'];
                return <<<EOT
WebSocket Gateway: Running
  PID: {$status['pid']}
  Port: {$port}
  URL: ws://localhost:{$port}

Clients can connect to receive real-time events.
Use appmesh_ws_broadcast to send messages to all clients.
EOT;
            }

            return <<<EOT
WebSocket Gateway: Not running

Start with: appmesh_ws_start
Or manually: php server/scripts/websocket-server.php

The gateway enables:
- Real-time event streaming to browsers
- Bidirectional communication with external tools
- D-Bus signal forwarding to web clients
EOT;
        }
    ),

    'appmesh_ws_start' => new Tool(
        description: 'Start the WebSocket gateway server',
        inputSchema: schema(
            [
                'port' => prop('integer', 'Port to listen on (default: 8765)'),
            ]
        ),
        handler: function (array $args): string {
            $status = ws_is_running();

            if ($status['running']) {
                return "WebSocket server already running on port {$status['port']} (PID: {$status['pid']})";
            }

            $port = (int) ($args['port'] ?? WS_PORT_DEFAULT);
            $script = ws_server_script();

            // Check if script exists
            if (!file_exists($script)) {
                // Create the WebSocket server script
                $scriptDir = dirname($script);

                if (!is_dir($scriptDir)) {
                    mkdir($scriptDir, 0755, true);
                }

                // Write a basic WebSocket server using PHP sockets
                file_put_contents($script, <<<'PHP'
#!/usr/bin/env php
<?php
/**
 * AppMesh WebSocket Gateway Server
 *
 * A simple WebSocket server for real-time communication.
 * Uses PHP's socket extension (no external dependencies).
 *
 * Usage: php websocket-server.php [port]
 */

declare(strict_types=1);

$port = (int) ($argv[1] ?? 8765);
$pidFile = '/tmp/appmesh-websocket.pid';
$controlSocket = '/tmp/appmesh-ws-control.sock';

// Write PID file
file_put_contents($pidFile, getmypid() . "\n" . $port);

// Cleanup on exit
register_shutdown_function(function() use ($pidFile, $controlSocket) {
    @unlink($pidFile);
    @unlink($controlSocket);
});

// Handle signals
pcntl_async_signals(true);
pcntl_signal(SIGTERM, function() { exit(0); });
pcntl_signal(SIGINT, function() { exit(0); });

// Create main socket - bind to localhost only for security
$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);

if (!@socket_bind($server, '127.0.0.1', $port)) {
    fwrite(STDERR, "Cannot bind to port {$port}\n");
    exit(1);
}

socket_listen($server);
socket_set_nonblock($server);

// Create control socket (Unix domain) - owner-only permissions
@unlink($controlSocket);
$control = socket_create(AF_UNIX, SOCK_STREAM, 0);
socket_bind($control, $controlSocket);
socket_listen($control);
socket_set_nonblock($control);
chmod($controlSocket, 0600);

echo "WebSocket server started on port {$port}\n";
echo "Control socket: {$controlSocket}\n";

$clients = [];         // Fully handshaked WebSocket clients
$pendingClients = [];  // Clients awaiting handshake

// WebSocket handshake
function ws_handshake($socket, $request): bool {
    if (!preg_match('/Sec-WebSocket-Key: (.*)\\r\\n/', $request, $match)) {
        return false;
    }

    $key = trim($match[1]);
    $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

    $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept: {$accept}\r\n\r\n";

    socket_write($socket, $response);
    return true;
}

// Encode WebSocket frame
function ws_encode($message): string {
    $length = strlen($message);

    if ($length <= 125) {
        return chr(0x81) . chr($length) . $message;
    } elseif ($length <= 65535) {
        return chr(0x81) . chr(126) . pack('n', $length) . $message;
    } else {
        return chr(0x81) . chr(127) . pack('J', $length) . $message;
    }
}

// Decode WebSocket frame
function ws_decode($data): ?string {
    if (strlen($data) < 2) return null;

    $length = ord($data[1]) & 127;
    $maskStart = 2;

    if ($length === 126) {
        $length = unpack('n', substr($data, 2, 2))[1];
        $maskStart = 4;
    } elseif ($length === 127) {
        $length = unpack('J', substr($data, 2, 8))[1];
        $maskStart = 10;
    }

    $mask = substr($data, $maskStart, 4);
    $payload = substr($data, $maskStart + 4, $length);

    $decoded = '';
    for ($i = 0; $i < strlen($payload); $i++) {
        $decoded .= $payload[$i] ^ $mask[$i % 4];
    }

    return $decoded;
}

// Broadcast to all clients
function broadcast($clients, $message): void {
    $frame = ws_encode($message);
    foreach ($clients as $client) {
        @socket_write($client, $frame);
    }
}

// Main loop
while (true) {
    // Accept new WebSocket connections
    $newClient = @socket_accept($server);
    if ($newClient) {
        socket_set_nonblock($newClient);
        $id = (int)$newClient;
        $pendingClients[$id] = ['socket' => $newClient, 'buffer' => '', 'time' => time()];
        echo "New connection (id: {$id})\n";
    }

    // Accept control connections
    $newControl = @socket_accept($control);
    if ($newControl) {
        $msg = trim(socket_read($newControl, 4096) ?: '');

        if (str_starts_with($msg, 'broadcast:')) {
            $payload = substr($msg, 10);
            broadcast($clients, $payload);
            socket_write($newControl, "Sent to " . count($clients) . " clients\n");
        } elseif ($msg === 'status') {
            socket_write($newControl, "Clients: " . count($clients) . "\n");
        } elseif ($msg === 'shutdown') {
            socket_write($newControl, "Shutting down\n");
            socket_close($newControl);
            break;
        } else {
            socket_write($newControl, "Unknown command\n");
        }

        socket_close($newControl);
    }

    // Handle pending handshakes
    foreach ($pendingClients as $id => $pending) {
        $socket = $pending['socket'];
        $data = @socket_read($socket, 4096);

        if ($data === false) {
            $err = socket_last_error($socket);
            if ($err !== SOCKET_EWOULDBLOCK && $err !== 11) {
                // Connection error
                socket_close($socket);
                unset($pendingClients[$id]);
                echo "Pending client {$id} disconnected\n";
            }
            continue;
        }

        if ($data === '') {
            // Timeout old pending connections (30 seconds)
            if (time() - $pending['time'] > 30) {
                socket_close($socket);
                unset($pendingClients[$id]);
                echo "Pending client {$id} timed out\n";
            }
            continue;
        }

        // Accumulate handshake data
        $pendingClients[$id]['buffer'] .= $data;

        // Check if we have complete HTTP headers
        if (str_contains($pendingClients[$id]['buffer'], "\r\n\r\n")) {
            if (ws_handshake($socket, $pendingClients[$id]['buffer'])) {
                // Handshake successful - promote to full client
                $clients[$id] = $socket;
                unset($pendingClients[$id]);
                echo "Client {$id} handshake complete\n";
            } else {
                // Invalid handshake
                socket_close($socket);
                unset($pendingClients[$id]);
                echo "Client {$id} handshake failed\n";
            }
        }
    }

    // Process established client data
    foreach ($clients as $id => $client) {
        $data = @socket_read($client, 4096);

        if ($data === false) {
            $err = socket_last_error($client);
            if ($err !== SOCKET_EWOULDBLOCK && $err !== 11) {
                socket_close($client);
                unset($clients[$id]);
                echo "Client {$id} disconnected\n";
            }
            continue;
        }

        if ($data === '') {
            continue;
        }

        // Check for close frame
        if (strlen($data) >= 1 && ord($data[0]) === 0x88) {
            socket_close($client);
            unset($clients[$id]);
            echo "Client {$id} closed connection\n";
            continue;
        }

        $message = ws_decode($data);
        if ($message !== null) {
            echo "Received from {$id}: {$message}\n";
            // Echo back (or process)
            @socket_write($client, ws_encode("Echo: {$message}"));
        }
    }

    usleep(10000); // 10ms sleep to reduce CPU
}

socket_close($server);
socket_close($control);
echo "Server stopped\n";
PHP
                );
                chmod($script, 0755);
            }

            // Start server in background
            $uid = posix_getuid();
            $runtimeDir = "/run/user/{$uid}";

            $cmd = sprintf(
                'php %s %d </dev/null >%s 2>&1 &',
                escapeshellarg($script),
                $port,
                escapeshellarg("/tmp/appmesh-ws-{$port}.log")
            );

            shell_exec($cmd);
            usleep(500000); // Wait for startup

            $status = ws_is_running();

            if ($status['running']) {
                return <<<EOT
WebSocket Gateway started!
  PID: {$status['pid']}
  Port: {$port}
  URL: ws://localhost:{$port}
  Log: /tmp/appmesh-ws-{$port}.log

Connect from browser:
  const ws = new WebSocket('ws://localhost:{$port}');
  ws.onmessage = (e) => console.log(e.data);
EOT;
            }

            return "Failed to start WebSocket server. Check /tmp/appmesh-ws-{$port}.log";
        }
    ),

    'appmesh_ws_stop' => new Tool(
        description: 'Stop the WebSocket gateway server',
        inputSchema: schema(),
        handler: function (array $args): string {
            $status = ws_is_running();

            if (!$status['running']) {
                return "WebSocket server is not running";
            }

            $pid = $status['pid'];

            // Try graceful shutdown via control socket
            $controlSocket = '/tmp/appmesh-ws-control.sock';
            if (file_exists($controlSocket)) {
                $socket = @stream_socket_client("unix://{$controlSocket}", $errno, $errstr, 2);
                if ($socket) {
                    fwrite($socket, "shutdown\n");
                    fclose($socket);
                    usleep(500000);
                }
            }

            // Force kill if still running
            if (file_exists("/proc/{$pid}")) {
                posix_kill($pid, SIGTERM);
                usleep(200000);

                if (file_exists("/proc/{$pid}")) {
                    posix_kill($pid, SIGKILL);
                }
            }

            @unlink(WS_PID_FILE);

            return "WebSocket server stopped (was PID: {$pid})";
        }
    ),

    // -------------------------------------------------------------------------
    // Messaging
    // -------------------------------------------------------------------------
    'appmesh_ws_broadcast' => new Tool(
        description: 'Broadcast a message to all connected WebSocket clients',
        inputSchema: schema(
            [
                'message' => prop('string', 'Message to broadcast (will be JSON encoded if object)'),
                'type' => prop('string', 'Message type for structured messages (optional)'),
            ],
            ['message']
        ),
        handler: function (array $args): string {
            $status = ws_is_running();

            if (!$status['running']) {
                return "Error: WebSocket server is not running. Start with appmesh_ws_start";
            }

            $message = $args['message'];
            $type = $args['type'] ?? null;

            // Wrap in structure if type provided
            if ($type) {
                $message = json_encode([
                    'type' => $type,
                    'data' => $message,
                    'timestamp' => time(),
                ]);
            }

            return ws_send_control("broadcast:{$message}", $status['port']);
        }
    ),

    'appmesh_ws_clients' => new Tool(
        description: 'Get count of connected WebSocket clients',
        inputSchema: schema(),
        handler: function (array $args): string {
            $status = ws_is_running();

            if (!$status['running']) {
                return "WebSocket server is not running";
            }

            return ws_send_control('status', $status['port']);
        }
    ),

    // -------------------------------------------------------------------------
    // Integration Info
    // -------------------------------------------------------------------------
    'appmesh_ws_info' => new Tool(
        description: 'Get WebSocket gateway integration information and examples',
        inputSchema: schema(),
        handler: function (array $args): string {
            $status = ws_is_running();
            $running = $status['running'] ? "Running on port {$status['port']}" : "Not running";

            return <<<EOT
# AppMesh WebSocket Gateway

**Status**: {$running}

## Browser Client Example

```javascript
const ws = new WebSocket('ws://localhost:8765');

ws.onopen = () => {
    console.log('Connected to AppMesh');
    ws.send(JSON.stringify({ action: 'subscribe', events: ['clipboard', 'notify'] }));
};

ws.onmessage = (event) => {
    const msg = JSON.parse(event.data);
    console.log('Event:', msg.type, msg.data);
};

ws.onerror = (err) => console.error('WebSocket error:', err);
ws.onclose = () => console.log('Disconnected');
```

## Use Cases

1. **Real-time D-Bus Events**
   Stream D-Bus signals to browser (clipboard changes, notifications, etc.)

2. **Remote Control**
   Control desktop from mobile/tablet web app

3. **Dashboard**
   Live system status updates in web UI

4. **Tool Integration**
   Connect external tools to AppMesh ecosystem

## D-Bus Signal Forwarding

Combine with sse-signals.php for D-Bus event streaming,
or use the WebSocket gateway directly for bidirectional comms.

## Related Tools

- `appmesh_ws_start` - Start the gateway
- `appmesh_ws_stop` - Stop the gateway
- `appmesh_ws_broadcast` - Send to all clients
- `appmesh_ws_clients` - Count connected clients
EOT;
        }
    ),
];
