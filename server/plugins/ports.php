<?php
/**
 * AppMesh Ports Plugin
 *
 * Exposes all Rust FFI ports as MCP tools via the generic port API.
 * Each port gets its own tool: appmesh_port_<name>
 */

require_once __DIR__ . '/../appmesh-ffi.php';

$portTools = [];

// Port definitions: name => [commands => [cmd => [description, params, required]]]
$ports = [
    'clipboard' => [
        'get' => ['Get clipboard contents', [], []],
        'set' => ['Set clipboard contents', ['text' => prop('string', 'Text to copy')], ['text']],
    ],
    'notify' => [
        'send' => [
            'Send desktop notification',
            [
                'title' => prop('string', 'Notification title'),
                'body' => prop('string', 'Notification body text'),
                'icon' => prop('string', 'Icon name (default: dialog-information)'),
            ],
            ['title'],
        ],
    ],
    'screenshot' => [
        'take' => [
            'Take a screenshot',
            ['mode' => prop('string', 'fullscreen, activewindow, or region', ['enum' => ['fullscreen', 'activewindow', 'region']])],
            [],
        ],
    ],
    'windows' => [
        'list' => ['List all open windows', [], []],
        'activate' => ['Activate a window by ID', ['id' => prop('string', 'Window ID (UUID)')], ['id']],
    ],
];

foreach ($ports as $portName => $commands) {
    foreach ($commands as $cmdName => $cmdDef) {
        [$description, $properties, $required] = $cmdDef;
        $toolName = "appmesh_port_{$portName}_{$cmdName}";

        $portTools[$toolName] = new Tool(
            description: "{$description} (via Rust FFI port: {$portName})",
            inputSchema: schema($properties, $required),
            handler: function (array $args) use ($portName, $cmdName): string {
                $ffi = AppMeshFFI::instance();
                if ($ffi === null) {
                    return "Error: FFI unavailable";
                }

                $result = $ffi->portExecute($portName, $cmdName, $args);
                if ($result === null) {
                    return "Error: port '{$portName}' unavailable";
                }

                if (isset($result['ok'])) {
                    return is_string($result['ok']) ? $result['ok'] : json_encode($result['ok']);
                }

                if (isset($result['error'])) {
                    return "Error: {$result['error']['message']}";
                }

                return json_encode($result);
            }
        );
    }
}

return $portTools;
