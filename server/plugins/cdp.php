<?php
/**
 * AppMesh CDP Plugin - Chrome DevTools Protocol
 *
 * Control Electron apps (VS Code, Discord, Slack, Obsidian, etc.) via CDP.
 * Works with any Chromium-based app launched with --remote-debugging-port.
 *
 * Usage:
 * 1. Launch app with: code --remote-debugging-port=9222
 * 2. Use these tools to discover and control the app
 *
 * Requirements: curl (for WebSocket via external tool), or php-websocket extension
 */

// ============================================================================
// CDP Protocol Implementation
// ============================================================================

/**
 * Get available debugging targets from a CDP endpoint
 */
function cdp_get_targets(string $host, int $port): array
{
    $url = "http://{$host}:{$port}/json";

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return ['error' => "Cannot connect to {$host}:{$port}. Is the app running with --remote-debugging-port={$port}?"];
    }

    $targets = json_decode($response, true);

    if (!is_array($targets)) {
        return ['error' => 'Invalid response from CDP endpoint'];
    }

    return $targets;
}

/**
 * Get CDP version info
 */
function cdp_get_version(string $host, int $port): array
{
    $url = "http://{$host}:{$port}/json/version";

    $context = stream_context_create([
        'http' => ['timeout' => 5],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return ['error' => "Cannot connect to {$host}:{$port}"];
    }

    return json_decode($response, true) ?? ['error' => 'Invalid response'];
}

/**
 * Send a CDP command via websocat (command-line WebSocket client)
 * Falls back to curl if websocat not available
 */
function cdp_send_command(string $wsUrl, string $method, array $params = []): array
{
    $id = time();
    $message = json_encode([
        'id' => $id,
        'method' => $method,
        'params' => (object) $params,
    ]);

    // Try websocat first (most reliable for single commands)
    $websocat = trim(shell_exec('which websocat 2>/dev/null') ?? '');

    if ($websocat) {
        $cmd = sprintf(
            'echo %s | timeout 10 websocat -n1 %s 2>&1',
            escapeshellarg($message),
            escapeshellarg($wsUrl)
        );

        $output = shell_exec($cmd);

        if ($output) {
            $response = json_decode(trim($output), true);
            if (is_array($response)) {
                return $response;
            }
        }

        return ['error' => 'No response from WebSocket', 'raw' => $output];
    }

    // Try wscat as alternative
    $wscat = trim(shell_exec('which wscat 2>/dev/null') ?? '');

    if ($wscat) {
        $cmd = sprintf(
            'echo %s | timeout 10 wscat -c %s -w 5 2>&1',
            escapeshellarg($message),
            escapeshellarg($wsUrl)
        );

        $output = shell_exec($cmd);

        if ($output) {
            // wscat output may have multiple lines
            foreach (explode("\n", $output) as $line) {
                $response = json_decode(trim($line), true);
                if (is_array($response) && isset($response['id'])) {
                    return $response;
                }
            }
        }

        return ['error' => 'No valid response', 'raw' => $output];
    }

    return [
        'error' => 'WebSocket client not found. Install websocat: cargo install websocat',
        'install_hint' => 'Or: paru -S websocat  (on Arch)',
    ];
}

/**
 * Take a screenshot via CDP
 */
function cdp_screenshot(string $wsUrl, string $format = 'png', int $quality = 80): array
{
    $result = cdp_send_command($wsUrl, 'Page.captureScreenshot', [
        'format' => $format,
        'quality' => $format === 'jpeg' ? $quality : null,
    ]);

    if (isset($result['error'])) {
        return $result;
    }

    if (isset($result['result']['data'])) {
        $data = base64_decode($result['result']['data']);
        $path = appmesh_tempfile('cdp_screenshot', $format);
        file_put_contents($path, $data);
        return ['path' => $path, 'size' => strlen($data)];
    }

    return ['error' => 'No screenshot data', 'response' => $result];
}

/**
 * Get WebSocket URL for a target (DRY helper)
 *
 * @param string $host CDP host
 * @param int $port CDP port
 * @param int $targetIdx Target index
 * @return array{wsUrl: string}|array{error: string} WebSocket URL or error
 */
function cdp_get_target_ws(string $host, int $port, int $targetIdx): array
{
    $targets = cdp_get_targets($host, $port);

    if (isset($targets['error'])) {
        return ['error' => $targets['error']];
    }

    if (!isset($targets[$targetIdx])) {
        return ['error' => "Target index {$targetIdx} not found. Use cdp_list to see available targets."];
    }

    $wsUrl = $targets[$targetIdx]['webSocketDebuggerUrl'] ?? null;

    if (!$wsUrl) {
        return ['error' => "No WebSocket URL for target {$targetIdx}"];
    }

    return ['wsUrl' => $wsUrl];
}

// ============================================================================
// Tool Definitions
// ============================================================================

return [
    // -------------------------------------------------------------------------
    // Discovery Tools
    // -------------------------------------------------------------------------
    'appmesh_cdp_list' => new Tool(
        description: 'List available CDP debugging targets (tabs, pages, workers) from an Electron app',
        inputSchema: schema(
            [
                'host' => prop('string', 'Target host (default: localhost)'),
                'port' => prop('integer', 'CDP port (default: 9222)'),
            ]
        ),
        handler: function (array $args): string {
            $host = $args['host'] ?? 'localhost';
            $port = (int) ($args['port'] ?? 9222);

            $targets = cdp_get_targets($host, $port);

            if (isset($targets['error'])) {
                return "Error: {$targets['error']}\n\nTo enable CDP, launch app with:\n  code --remote-debugging-port=9222\n  discord --remote-debugging-port=9223";
            }

            $output = "CDP Targets at {$host}:{$port}:\n\n";

            foreach ($targets as $i => $target) {
                $output .= sprintf(
                    "[%d] %s\n    Type: %s\n    URL: %s\n    WS: %s\n\n",
                    $i,
                    $target['title'] ?? '(untitled)',
                    $target['type'] ?? 'unknown',
                    $target['url'] ?? '-',
                    $target['webSocketDebuggerUrl'] ?? '-'
                );
            }

            return $output;
        }
    ),

    'appmesh_cdp_version' => new Tool(
        description: 'Get CDP version and browser info from an Electron app',
        inputSchema: schema(
            [
                'host' => prop('string', 'Target host (default: localhost)'),
                'port' => prop('integer', 'CDP port (default: 9222)'),
            ]
        ),
        handler: function (array $args): string {
            $host = $args['host'] ?? 'localhost';
            $port = (int) ($args['port'] ?? 9222);

            $version = cdp_get_version($host, $port);

            if (isset($version['error'])) {
                return "Error: {$version['error']}";
            }

            return json_encode($version, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    ),

    // -------------------------------------------------------------------------
    // Execution Tools
    // -------------------------------------------------------------------------
    'appmesh_cdp_eval' => new Tool(
        description: 'Execute JavaScript in an Electron app via CDP. Returns the result.',
        inputSchema: schema(
            [
                'expression' => prop('string', 'JavaScript expression to evaluate'),
                'host' => prop('string', 'Target host (default: localhost)'),
                'port' => prop('integer', 'CDP port (default: 9222)'),
                'target' => prop('integer', 'Target index from cdp_list (default: 0)'),
            ],
            ['expression']
        ),
        handler: function (array $args): string {
            $host = appmesh_arg($args, 'host', 'localhost');
            $port = appmesh_arg($args, 'port', 9222, 'int');
            $targetIdx = appmesh_arg($args, 'target', 0, 'int');
            $expression = $args['expression'];

            $target = cdp_get_target_ws($host, $port, $targetIdx);
            if (isset($target['error'])) {
                return "Error: {$target['error']}";
            }

            $result = cdp_send_command($target['wsUrl'], 'Runtime.evaluate', [
                'expression' => $expression,
                'returnByValue' => true,
            ]);

            if (isset($result['error'])) {
                return "Error: " . json_encode($result['error']);
            }

            if (isset($result['result']['result'])) {
                $value = $result['result']['result'];

                if (isset($value['value'])) {
                    return is_string($value['value'])
                        ? $value['value']
                        : json_encode($value['value'], JSON_PRETTY_PRINT);
                }

                return json_encode($value, JSON_PRETTY_PRINT);
            }

            return json_encode($result, JSON_PRETTY_PRINT);
        }
    ),

    'appmesh_cdp_screenshot' => new Tool(
        description: 'Take a screenshot of an Electron app via CDP',
        inputSchema: schema(
            [
                'host' => prop('string', 'Target host (default: localhost)'),
                'port' => prop('integer', 'CDP port (default: 9222)'),
                'target' => prop('integer', 'Target index from cdp_list (default: 0)'),
                'format' => prop('string', 'Image format: png or jpeg (default: png)'),
            ]
        ),
        handler: function (array $args): string {
            $host = appmesh_arg($args, 'host', 'localhost');
            $port = appmesh_arg($args, 'port', 9222, 'int');
            $targetIdx = appmesh_arg($args, 'target', 0, 'int');
            $format = appmesh_arg($args, 'format', 'png');

            $target = cdp_get_target_ws($host, $port, $targetIdx);
            if (isset($target['error'])) {
                return "Error: {$target['error']}";
            }

            $result = cdp_screenshot($target['wsUrl'], $format);

            if (isset($result['error'])) {
                return "Error: {$result['error']}";
            }

            return "Screenshot saved: {$result['path']} ({$result['size']} bytes)";
        }
    ),

    // -------------------------------------------------------------------------
    // Navigation & DOM Tools
    // -------------------------------------------------------------------------
    'appmesh_cdp_navigate' => new Tool(
        description: 'Navigate an Electron app to a URL',
        inputSchema: schema(
            [
                'url' => prop('string', 'URL to navigate to'),
                'host' => prop('string', 'Target host (default: localhost)'),
                'port' => prop('integer', 'CDP port (default: 9222)'),
                'target' => prop('integer', 'Target index (default: 0)'),
            ],
            ['url']
        ),
        handler: function (array $args): string {
            $host = appmesh_arg($args, 'host', 'localhost');
            $port = appmesh_arg($args, 'port', 9222, 'int');
            $targetIdx = appmesh_arg($args, 'target', 0, 'int');
            $url = $args['url'];

            $target = cdp_get_target_ws($host, $port, $targetIdx);
            if (isset($target['error'])) {
                return "Error: {$target['error']}";
            }

            $result = cdp_send_command($target['wsUrl'], 'Page.navigate', ['url' => $url]);

            if (isset($result['error'])) {
                return "Error: " . json_encode($result['error']);
            }

            return "Navigated to: {$url}";
        }
    ),

    'appmesh_cdp_click' => new Tool(
        description: 'Click an element in an Electron app using CSS selector',
        inputSchema: schema(
            [
                'selector' => prop('string', 'CSS selector of element to click'),
                'host' => prop('string', 'Target host (default: localhost)'),
                'port' => prop('integer', 'CDP port (default: 9222)'),
                'target' => prop('integer', 'Target index (default: 0)'),
            ],
            ['selector']
        ),
        handler: function (array $args): string {
            $host = appmesh_arg($args, 'host', 'localhost');
            $port = appmesh_arg($args, 'port', 9222, 'int');
            $targetIdx = appmesh_arg($args, 'target', 0, 'int');
            $selector = $args['selector'];

            $target = cdp_get_target_ws($host, $port, $targetIdx);
            if (isset($target['error'])) {
                return "Error: {$target['error']}";
            }

            // Use JavaScript to find and click the element
            $js = sprintf(
                'document.querySelector(%s)?.click(); "clicked"',
                json_encode($selector)
            );

            $result = cdp_send_command($target['wsUrl'], 'Runtime.evaluate', [
                'expression' => $js,
                'returnByValue' => true,
            ]);

            if (isset($result['error'])) {
                return "Error: " . json_encode($result['error']);
            }

            return "Clicked: {$selector}";
        }
    ),
];
