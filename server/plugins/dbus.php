<?php
/**
 * AppMesh D-Bus Plugin
 *
 * Provides D-Bus integration for KDE desktop automation.
 * Tools: clipboard, notifications, screenshots, D-Bus calls, KWin window management.
 */

// ============================================================================
// D-Bus Helper Functions
// ============================================================================

function qdbus_call(string $service, string $path, string $method, array $args = []): string
{
    $escaped = array_map(fn($a) => escapeshellarg((string)$a), $args);
    $cmd = "qdbus6 " . escapeshellarg($service) . " " . escapeshellarg($path) . " " . escapeshellarg($method);
    if ($escaped) {
        $cmd .= " " . implode(" ", $escaped);
    }
    $output = shell_exec($cmd . " 2>&1") ?? '';
    return trim($output);
}

// ============================================================================
// Tool Definitions
// ============================================================================

return [
    // -------------------------------------------------------------------------
    // Clipboard Tools
    // -------------------------------------------------------------------------
    'appmesh_clipboard_get' => new Tool(
        description: 'Get the current clipboard contents from KDE Klipper',
        inputSchema: schema(),
        handler: fn(array $args): string =>
            qdbus_call('org.kde.klipper', '/klipper', 'org.kde.klipper.klipper.getClipboardContents')
    ),

    'appmesh_clipboard_set' => new Tool(
        description: 'Set the clipboard contents in KDE Klipper',
        inputSchema: schema(
            ['content' => prop('string', 'The text to copy to the clipboard')],
            ['content']
        ),
        handler: function (array $args): string {
            $content = $args['content'] ?? '';
            qdbus_call('org.kde.klipper', '/klipper', 'org.kde.klipper.klipper.setClipboardContents', [$content]);
            return "Clipboard set to: " . substr($content, 0, 100) . (strlen($content) > 100 ? '...' : '');
        }
    ),

    // -------------------------------------------------------------------------
    // Notification Tools
    // -------------------------------------------------------------------------
    'appmesh_notify' => new Tool(
        description: 'Send a desktop notification on KDE',
        inputSchema: schema(
            [
                'title' => prop('string', 'Notification title'),
                'body' => prop('string', 'Notification body text'),
            ],
            ['title']
        ),
        handler: function (array $args): string {
            $title = escapeshellarg($args['title'] ?? 'AppMesh');
            $body = escapeshellarg($args['body'] ?? '');
            shell_exec("/usr/bin/notify-send $title $body 2>&1");
            return "Notification sent: {$args['title']}";
        }
    ),

    // -------------------------------------------------------------------------
    // Screenshot Tools
    // -------------------------------------------------------------------------
    'appmesh_screenshot' => new Tool(
        description: 'Take a screenshot using KDE Spectacle. Returns the path to the saved image.',
        inputSchema: schema(
            ['mode' => prop('string', 'Screenshot mode', ['enum' => ['fullscreen', 'activewindow', 'region']])]
        ),
        handler: function (array $args): string {
            $mode = appmesh_arg($args, 'mode', 'fullscreen');
            $filename = appmesh_tempfile('screenshot', 'png');

            $modeFlag = match ($mode) {
                'activewindow' => '-a',
                'region' => '-r',
                default => '-f',
            };

            $result = appmesh_exec("spectacle {$modeFlag} -b -n -o " . escapeshellarg($filename));

            usleep(500000);

            if (file_exists($filename)) {
                return "Screenshot saved: {$filename}";
            }

            AppMeshLogger::warning("Screenshot may have failed", [
                'mode' => $mode,
                'exitCode' => $result['exitCode'],
            ]);

            return "Screenshot may have been saved to: {$filename} (verify manually)";
        }
    ),

    // -------------------------------------------------------------------------
    // D-Bus Generic Tools
    // -------------------------------------------------------------------------
    'appmesh_dbus_call' => new Tool(
        description: 'Call any D-Bus method on the session bus. Use appmesh_dbus_list to discover available services.',
        inputSchema: schema(
            [
                'service' => prop('string', 'D-Bus service name (e.g., org.kde.KWin)'),
                'path' => prop('string', 'Object path (e.g., /KWin)'),
                'method' => prop('string', 'Method to call with interface (e.g., org.kde.KWin.showDesktop)'),
                'args' => prop('array', 'Arguments to pass to the method', ['items' => ['type' => 'string']]),
            ],
            ['service', 'path', 'method']
        ),
        handler: function (array $args): string {
            $result = qdbus_call(
                $args['service'],
                $args['path'],
                $args['method'],
                $args['args'] ?? []
            );
            return $result ?: '(no output)';
        }
    ),

    'appmesh_dbus_list' => new Tool(
        description: 'List D-Bus services or introspect a specific service to discover its methods',
        inputSchema: schema(
            [
                'service' => prop('string', 'Service to introspect. If omitted, lists all available services.'),
                'path' => prop('string', 'Object path to introspect (default: /)'),
            ]
        ),
        handler: function (array $args): string {
            if (!isset($args['service'])) {
                $output = shell_exec('qdbus6 2>&1') ?? '';
                $services = array_filter(explode("\n", trim($output)));
                $kde = array_filter($services, fn($s) => str_contains($s, 'kde') || str_contains($s, 'KDE'));
                return "KDE Services:\n" . implode("\n", $kde) . "\n\nAll services: " . count($services);
            }

            $path = $args['path'] ?? '/';
            $output = shell_exec("qdbus6 " . escapeshellarg($args['service']) . " " . escapeshellarg($path) . " 2>&1");
            return trim($output ?? '(no output)');
        }
    ),

    // -------------------------------------------------------------------------
    // KWin Window Management
    // -------------------------------------------------------------------------
    'appmesh_kwin_list_windows' => new Tool(
        description: 'List all open windows with their IDs, titles, and applications',
        inputSchema: schema(),
        handler: function (array $args): string {
            $script = <<<'JS'
            const clients = workspace.windowList();
            let result = [];
            for (const c of clients) {
                if (c.caption && c.caption.length > 0) {
                    result.push({
                        id: c.internalId.toString(),
                        title: c.caption,
                        app: c.resourceClass,
                        active: c.active
                    });
                }
            }
            JSON.stringify(result);
            JS;

            $scriptFile = appmesh_tempfile('kwin_list_windows', 'js');
            file_put_contents($scriptFile, $script);

            $loadResult = qdbus_call(
                'org.kde.KWin',
                '/Scripting',
                'org.kde.kwin.Scripting.loadScript',
                [$scriptFile]
            );

            @unlink($scriptFile);

            if (!is_numeric($loadResult)) {
                return "Failed to load script: {$loadResult}";
            }

            $scriptPath = "/Scripting/Script{$loadResult}";
            qdbus_call('org.kde.KWin', $scriptPath, 'org.kde.kwin.Script.run');
            usleep(100000);
            qdbus_call('org.kde.KWin', $scriptPath, 'org.kde.kwin.Script.stop');

            $wmctrl = appmesh_exec('wmctrl -l -p', false);
            if ($wmctrl['success'] && $wmctrl['output']) {
                return "Windows (via wmctrl):\n{$wmctrl['output']}";
            }

            $kdotool = appmesh_exec('kdotool search --name "."', false);
            if ($kdotool['success'] && $kdotool['output']) {
                return "Window IDs:\n{$kdotool['output']}";
            }

            return "Script loaded (id: {$loadResult}). Window listing requires wmctrl or kdotool for reliable output.";
        }
    ),

    'appmesh_kwin_activate_window' => new Tool(
        description: 'Activate (focus) a window by its ID',
        inputSchema: schema(
            ['window_id' => prop('string', 'The window ID (UUID) to activate')],
            ['window_id']
        ),
        handler: function (array $args): string {
            if ($error = appmesh_validate($args, ['window_id'])) {
                return "Error: {$error}";
            }

            $windowId = $args['window_id'];

            if (is_numeric($windowId)) {
                $result = appmesh_exec("kdotool windowactivate " . escapeshellarg($windowId));
                if ($result['success']) {
                    return "Activated window: {$windowId}";
                }

                $result2 = appmesh_exec("wmctrl -i -a " . escapeshellarg($windowId));
                return "Activation attempted. kdotool: {$result['output']}, wmctrl: {$result2['output']}";
            }

            // Escape the windowId for JavaScript (it's a UUID string)
            $escapedId = json_encode($windowId);
            $script = "workspace.windowList().find(w => w.internalId.toString() === {$escapedId})?.activate();";
            $scriptFile = appmesh_tempfile('kwin_activate', 'js');
            file_put_contents($scriptFile, $script);

            $loadResult = qdbus_call('org.kde.KWin', '/Scripting', 'org.kde.kwin.Scripting.loadScript', [$scriptFile]);
            @unlink($scriptFile);

            if (is_numeric($loadResult)) {
                $scriptPath = "/Scripting/Script{$loadResult}";
                qdbus_call('org.kde.KWin', $scriptPath, 'org.kde.kwin.Script.run');
                usleep(50000);
                qdbus_call('org.kde.KWin', $scriptPath, 'org.kde.kwin.Script.stop');
                return "Activated window: {$windowId}";
            }

            return "Failed to activate window: {$windowId}";
        }
    ),
];
