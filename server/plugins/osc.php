<?php
/**
 * AppMesh OSC Plugin
 *
 * Provides Open Sound Control (OSC) support for pro audio applications.
 * Tools: generic OSC sender, Ardour control, Carla control.
 *
 * Supported applications:
 * - Ardour (port 3819) - Full DAW control via OSC
 * - Carla (port 22752) - Plugin host control
 * - Any OSC-capable application
 */

// ============================================================================
// OSC Protocol Implementation
// ============================================================================

/**
 * Pad data to 4-byte boundary (OSC requirement)
 */
function osc_pad(string $data): string
{
    $padLen = 4 - (strlen($data) % 4);
    return $data . str_repeat("\0", $padLen);
}

/**
 * Encode an OSC message
 */
function osc_encode(string $address, array $args = []): string
{
    // Address pattern (null-terminated, padded)
    $packet = osc_pad($address . "\0");

    // Type tag string
    $typeTag = ',';
    $argData = '';

    foreach ($args as $arg) {
        if (is_int($arg)) {
            $typeTag .= 'i';
            $argData .= pack('N', $arg); // Big-endian 32-bit int
        } elseif (is_float($arg)) {
            $typeTag .= 'f';
            $argData .= pack('G', $arg); // Big-endian 32-bit float
        } elseif (is_string($arg)) {
            $typeTag .= 's';
            $argData .= osc_pad($arg . "\0");
        }
    }

    $packet .= osc_pad($typeTag . "\0");
    $packet .= $argData;

    return $packet;
}

/**
 * Send an OSC message via UDP
 */
function osc_send_udp(string $host, int $port, string $address, array $args = []): string
{
    $packet = osc_encode($address, $args);

    $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if ($socket === false) {
        return "Error: Could not create socket - " . socket_strerror(socket_last_error());
    }

    $result = socket_sendto($socket, $packet, strlen($packet), 0, $host, $port);
    socket_close($socket);

    if ($result === false) {
        return "Error: Failed to send - " . socket_strerror(socket_last_error());
    }

    return "Sent OSC: $address to $host:$port (" . count($args) . " args, $result bytes)";
}

/**
 * Parse typed argument strings like "i:42", "f:3.14", "s:hello"
 */
function osc_parse_arg(string $arg): int|float|string
{
    if (preg_match('/^i:(-?\d+)$/', $arg, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/^f:(-?\d+\.?\d*)$/', $arg, $m)) {
        return (float) $m[1];
    }
    if (preg_match('/^s:(.*)$/', $arg, $m)) {
        return $m[1];
    }
    // Auto-detect type
    if (is_numeric($arg)) {
        return str_contains($arg, '.') ? (float) $arg : (int) $arg;
    }
    return $arg;
}

// ============================================================================
// Tool Definitions
// ============================================================================

return [
    // -------------------------------------------------------------------------
    // Generic OSC
    // -------------------------------------------------------------------------
    'appmesh_osc_send' => new Tool(
        description: 'Send an OSC message to any OSC-capable application',
        inputSchema: schema(
            [
                'host' => prop('string', 'Target host (default: localhost)'),
                'port' => prop('integer', 'Target UDP port'),
                'address' => prop('string', 'OSC address pattern (e.g., /transport/play)'),
                'args' => prop('array', 'Arguments: use "i:N" for int, "f:N" for float, "s:text" for string, or auto-detect', [
                    'items' => ['type' => 'string']
                ]),
            ],
            ['port', 'address']
        ),
        handler: function (array $args): string {
            $host = $args['host'] ?? 'localhost';
            $port = (int) ($args['port'] ?? 0);
            $address = $args['address'] ?? '';
            $oscArgs = array_map('osc_parse_arg', $args['args'] ?? []);

            if ($port <= 0 || $port > 65535) {
                return "Error: Invalid port number";
            }
            if (!str_starts_with($address, '/')) {
                return "Error: OSC address must start with /";
            }

            return osc_send_udp($host, $port, $address, $oscArgs);
        }
    ),

    // -------------------------------------------------------------------------
    // Ardour DAW Control (port 3819)
    // -------------------------------------------------------------------------
    'appmesh_osc_ardour' => new Tool(
        description: 'Control Ardour DAW via OSC. Enable OSC in Ardour: Edit > Preferences > Control Surfaces > OSC',
        inputSchema: schema(
            [
                'command' => prop('string', 'OSC command (e.g., /transport_play, /strip/gain, /goto_start)'),
                'args' => prop('array', 'Arguments for the command', ['items' => ['type' => 'string']]),
                'host' => prop('string', 'Ardour host (default: localhost)'),
                'port' => prop('integer', 'Ardour OSC port (default: 3819)'),
            ],
            ['command']
        ),
        handler: function (array $args): string {
            $host = $args['host'] ?? 'localhost';
            $port = (int) ($args['port'] ?? 3819);
            $command = $args['command'] ?? '';
            $oscArgs = array_map('osc_parse_arg', $args['args'] ?? []);

            if (!str_starts_with($command, '/')) {
                $command = '/' . $command;
            }

            $result = osc_send_udp($host, $port, $command, $oscArgs);

            // Add helpful context for common commands
            $help = match (true) {
                str_contains($command, 'transport_play') => ' (starts playback)',
                str_contains($command, 'transport_stop') => ' (stops playback)',
                str_contains($command, 'rec_enable') => ' (toggles record enable)',
                str_contains($command, 'goto_start') => ' (moves to session start)',
                str_contains($command, 'strip/gain') => ' (sets track gain in dB)',
                str_contains($command, 'strip/mute') => ' (mutes/unmutes track)',
                str_contains($command, 'strip/solo') => ' (solos/unsolos track)',
                str_contains($command, 'save_state') => ' (saves session)',
                default => '',
            };

            return $result . $help;
        }
    ),

    // -------------------------------------------------------------------------
    // Carla Plugin Host Control (port 22752)
    // -------------------------------------------------------------------------
    'appmesh_osc_carla' => new Tool(
        description: 'Control Carla plugin host via OSC. Start Carla with: carla --osc-gui=22752',
        inputSchema: schema(
            [
                'command' => prop('string', 'OSC command for Carla'),
                'args' => prop('array', 'Arguments for the command', ['items' => ['type' => 'string']]),
                'host' => prop('string', 'Carla host (default: localhost)'),
                'port' => prop('integer', 'Carla OSC port (default: 22752)'),
            ],
            ['command']
        ),
        handler: function (array $args): string {
            $host = $args['host'] ?? 'localhost';
            $port = (int) ($args['port'] ?? 22752);
            $command = $args['command'] ?? '';
            $oscArgs = array_map('osc_parse_arg', $args['args'] ?? []);

            if (!str_starts_with($command, '/')) {
                $command = '/' . $command;
            }

            return osc_send_udp($host, $port, $command, $oscArgs);
        }
    ),
];
