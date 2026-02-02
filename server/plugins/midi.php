<?php
/**
 * AppMesh MIDI Plugin - PipeWire MIDI Routing
 *
 * Control MIDI routing on Linux via PipeWire/JACK.
 * Enables connecting hardware controllers, virtual MIDI, and audio applications.
 *
 * Requirements:
 * - PipeWire with MIDI support (pipewire-jack)
 * - pw-link, pw-cli commands available
 *
 * Use cases:
 * - Route MIDI keyboard to DAW
 * - Connect virtual MIDI ports between apps
 * - Monitor MIDI device connections
 */

// ============================================================================
// PipeWire MIDI Helpers
// ============================================================================

/**
 * Get all MIDI ports (inputs and outputs)
 */
function midi_get_ports(): array
{
    // Get all ports and filter for MIDI
    $output = shell_exec('pw-link -o 2>/dev/null') ?? '';
    $inputs = shell_exec('pw-link -i 2>/dev/null') ?? '';

    $outputs = array_filter(
        array_map('trim', explode("\n", $output)),
        fn($line) => str_contains(strtolower($line), 'midi') && $line !== ''
    );

    $inputPorts = array_filter(
        array_map('trim', explode("\n", $inputs)),
        fn($line) => str_contains(strtolower($line), 'midi') && $line !== ''
    );

    return [
        'outputs' => array_values($outputs),
        'inputs' => array_values($inputPorts),
    ];
}

/**
 * Get existing MIDI connections
 */
function midi_get_links(): array
{
    $output = shell_exec('pw-link -l 2>/dev/null') ?? '';
    $lines = explode("\n", trim($output));
    $links = [];
    $currentOutput = null;

    foreach ($lines as $line) {
        $line = rtrim($line);

        if ($line === '' || !str_contains(strtolower($line), 'midi')) {
            continue;
        }

        if (!str_starts_with($line, ' ') && !str_starts_with($line, "\t")) {
            // This is an output port
            $currentOutput = trim($line);
        } elseif ($currentOutput && str_starts_with(trim($line), '|->')) {
            // This is a connection
            $input = trim(substr(trim($line), 3));
            $links[] = [
                'from' => $currentOutput,
                'to' => $input,
            ];
        }
    }

    return $links;
}

/**
 * List ALSA MIDI devices (raw hardware)
 */
function midi_get_alsa_devices(): string
{
    // Try aplaymidi for listing
    $output = shell_exec('aplaymidi -l 2>/dev/null') ?? '';

    if (!$output) {
        // Try amidi as fallback
        $output = shell_exec('amidi -l 2>/dev/null') ?? '';
    }

    if (!$output) {
        return 'No ALSA MIDI utilities found. Install: alsa-utils';
    }

    return $output;
}

/**
 * Monitor MIDI input (blocking, with timeout)
 */
function midi_monitor(string $port, int $timeout = 5): string
{
    // Use aseqdump for monitoring
    $cmd = sprintf(
        'timeout %d aseqdump -p %s 2>&1',
        $timeout,
        escapeshellarg($port)
    );

    $output = shell_exec($cmd);

    if (!$output) {
        return "No MIDI events received in {$timeout} seconds (or aseqdump not found)";
    }

    return $output;
}

// ============================================================================
// Tool Definitions
// ============================================================================

return [
    // -------------------------------------------------------------------------
    // Discovery Tools
    // -------------------------------------------------------------------------
    'appmesh_midi_list' => new Tool(
        description: 'List all MIDI ports available via PipeWire',
        inputSchema: schema(),
        handler: function (array $args): string {
            $ports = midi_get_ports();

            if (empty($ports['outputs']) && empty($ports['inputs'])) {
                return "No MIDI ports found.\n\nCheck that PipeWire MIDI is running:\n  systemctl --user status pipewire\n  systemctl --user status wireplumber\n\nEnsure MIDI devices are connected.";
            }

            $output = "MIDI Output Ports (sources):\n";
            foreach ($ports['outputs'] as $i => $port) {
                $output .= "  [{$i}] {$port}\n";
            }

            $output .= "\nMIDI Input Ports (sinks):\n";
            foreach ($ports['inputs'] as $i => $port) {
                $output .= "  [{$i}] {$port}\n";
            }

            return $output;
        }
    ),

    'appmesh_midi_links' => new Tool(
        description: 'List current MIDI connections between ports',
        inputSchema: schema(),
        handler: function (array $args): string {
            $links = midi_get_links();

            if (empty($links)) {
                return "No MIDI connections found.\n\nUse appmesh_midi_connect to create connections.";
            }

            $output = "Current MIDI Connections:\n\n";

            foreach ($links as $link) {
                $output .= "{$link['from']}\n  └─> {$link['to']}\n\n";
            }

            return $output;
        }
    ),

    'appmesh_midi_devices' => new Tool(
        description: 'List raw ALSA MIDI hardware devices',
        inputSchema: schema(),
        handler: function (array $args): string {
            return "ALSA MIDI Devices:\n\n" . midi_get_alsa_devices();
        }
    ),

    // -------------------------------------------------------------------------
    // Connection Management
    // -------------------------------------------------------------------------
    'appmesh_midi_connect' => new Tool(
        description: 'Connect a MIDI output port to an input port',
        inputSchema: schema(
            [
                'output' => prop('string', 'Output port name (from midi_list outputs)'),
                'input' => prop('string', 'Input port name (from midi_list inputs)'),
            ],
            ['output', 'input']
        ),
        handler: function (array $args): string {
            $output = $args['output'];
            $input = $args['input'];

            $cmd = sprintf(
                'pw-link %s %s 2>&1',
                escapeshellarg($output),
                escapeshellarg($input)
            );

            $result = shell_exec($cmd);

            if ($result === null || $result === '') {
                return "Connected: {$output} -> {$input}";
            }

            // Check if already connected
            if (str_contains($result, 'File exists')) {
                return "Already connected: {$output} -> {$input}";
            }

            return "Error: {$result}";
        }
    ),

    'appmesh_midi_disconnect' => new Tool(
        description: 'Disconnect a MIDI link between ports',
        inputSchema: schema(
            [
                'output' => prop('string', 'Output port name'),
                'input' => prop('string', 'Input port name'),
            ],
            ['output', 'input']
        ),
        handler: function (array $args): string {
            $output = $args['output'];
            $input = $args['input'];

            $cmd = sprintf(
                'pw-link -d %s %s 2>&1',
                escapeshellarg($output),
                escapeshellarg($input)
            );

            $result = shell_exec($cmd);

            if ($result === null || $result === '') {
                return "Disconnected: {$output} -> {$input}";
            }

            return "Error: {$result}";
        }
    ),

    // -------------------------------------------------------------------------
    // Monitoring Tools
    // -------------------------------------------------------------------------
    'appmesh_midi_monitor' => new Tool(
        description: 'Monitor MIDI events from a port (blocks for timeout seconds)',
        inputSchema: schema(
            [
                'port' => prop('string', 'ALSA port to monitor (e.g., "20:0" or device name)'),
                'timeout' => prop('integer', 'Seconds to monitor (default: 5, max: 30)'),
            ],
            ['port']
        ),
        handler: function (array $args): string {
            $port = $args['port'];
            $timeout = min((int) ($args['timeout'] ?? 5), 30);

            return "Monitoring MIDI on {$port} for {$timeout}s...\n\n" . midi_monitor($port, $timeout);
        }
    ),

    // -------------------------------------------------------------------------
    // Virtual MIDI
    // -------------------------------------------------------------------------
    'appmesh_midi_virtual' => new Tool(
        description: 'Create a virtual MIDI port using pw-midibridge or explain how to',
        inputSchema: schema(
            [
                'name' => prop('string', 'Name for the virtual port'),
                'action' => prop('string', 'Action: create, list, or info (default: info)'),
            ]
        ),
        handler: function (array $args): string {
            $action = $args['action'] ?? 'info';
            $name = $args['name'] ?? 'AppMesh-Virtual';

            return match ($action) {
                'info' => <<<EOT
Virtual MIDI Ports in PipeWire:

1. **Using a2jmidid** (ALSA to JACK MIDI bridge):
   a2jmidid -e &
   This exposes all ALSA MIDI devices as JACK/PipeWire ports.

2. **Using virmidi** (kernel module):
   sudo modprobe snd-virmidi
   Creates virtual MIDI devices at /dev/snd/midiC*D*

3. **Using aseq** (ALSA sequencer):
   # Many apps create virtual ports automatically
   # Check: aplaymidi -l

4. **Using pw-loopback** (audio, not MIDI):
   For audio loopback only, not MIDI.

Most audio apps (Ardour, Carla, REAPER) create their own
PipeWire MIDI ports automatically when running.
EOT,

                'list' => (function () {
                    $a2j = shell_exec('pgrep -a a2jmidid 2>/dev/null') ?? '';
                    $virmidi = shell_exec('lsmod | grep snd_virmidi 2>/dev/null') ?? '';

                    $output = "Virtual MIDI Status:\n\n";
                    $output .= "a2jmidid: " . ($a2j ? "Running\n{$a2j}" : "Not running") . "\n";
                    $output .= "snd-virmidi: " . ($virmidi ? "Loaded" : "Not loaded") . "\n";

                    return $output;
                })(),

                'create' => (function () use ($name) {
                    // Check if a2jmidid is available
                    $a2j = trim(shell_exec('which a2jmidid 2>/dev/null') ?? '');

                    if (!$a2j) {
                        return "a2jmidid not found. Install: paru -S a2jmidid";
                    }

                    // Check if already running
                    $running = shell_exec('pgrep a2jmidid 2>/dev/null') ?? '';

                    if ($running) {
                        return "a2jmidid already running (PID: " . trim($running) . ")";
                    }

                    // Start a2jmidid in background
                    shell_exec('a2jmidid -e >/dev/null 2>&1 &');
                    usleep(500000);

                    $pid = trim(shell_exec('pgrep a2jmidid 2>/dev/null') ?? '');

                    if ($pid) {
                        return "Started a2jmidid (PID: {$pid})\nALSA MIDI devices now available as PipeWire ports.\nUse appmesh_midi_list to see them.";
                    }

                    return "Failed to start a2jmidid";
                })(),

                default => "Unknown action. Use: info, list, or create",
            };
        }
    ),

    // -------------------------------------------------------------------------
    // MIDI to OSC Bridge
    // -------------------------------------------------------------------------
    'appmesh_midi_to_osc' => new Tool(
        description: 'Convert MIDI CC/Note to OSC message (one-shot conversion info)',
        inputSchema: schema(
            [
                'cc' => prop('integer', 'MIDI CC number (0-127)'),
                'value' => prop('integer', 'CC value (0-127)'),
                'osc_address' => prop('string', 'Target OSC address pattern'),
                'osc_port' => prop('integer', 'Target OSC port'),
            ],
            ['cc', 'osc_address', 'osc_port']
        ),
        handler: function (array $args): string {
            $cc = (int) $args['cc'];
            $value = (int) ($args['value'] ?? 64);
            $oscAddress = $args['osc_address'];
            $oscPort = (int) $args['osc_port'];

            // Normalize CC value to 0.0-1.0
            $normalized = $value / 127.0;

            // This is informational - actual bridging would need a daemon
            return <<<EOT
MIDI to OSC Mapping:

MIDI CC {$cc} (value: {$value})
  → OSC {$oscAddress} (normalized: {$normalized})
  → Port: {$oscPort}

For continuous bridging, use one of:
1. **midimonster** - Full MIDI/OSC/DMX bridge
   https://github.com/cbdevnet/midimonster

2. **osmid** - Simple MIDI↔OSC bridge (used by Sonic Pi)
   https://github.com/llloret/osmid

3. **OSC2MIDI** - Configurable bridge
   https://github.com/ssj71/OSC2MIDI

4. **Custom script** with aseqdump:
   aseqdump -p 20:0 | while read line; do
     # Parse and send OSC via appmesh_osc_send
   done
EOT;
        }
    ),
];
