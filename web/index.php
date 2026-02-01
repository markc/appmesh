<?php
declare(strict_types=1);
/**
 * AppMesh Web UI v2.1 - HTMX + SSE Interface
 *
 * Browser-based interface to AppMesh using the shared plugin system.
 * Real-time D-Bus signal streaming using Server-Sent Events.
 *
 * Usage: php -S localhost:8420 index.php
 *   or:  frankenphp php-server --listen :8420
 */

// Load shared core (Tool class, AppMesh registry, plugins)
require_once __DIR__ . '/../server/appmesh-core.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Route SSE endpoint to separate handler
if ($path === '/sse/signals') {
    require __DIR__ . '/sse-signals.php';
    exit;
}

// Check if HTMX request
$isHtmx = isset($_SERVER['HTTP_HX_REQUEST']);

// CORS - Restrict to localhost by default, configurable via environment
$allowedOrigins = appmesh_env('APPMESH_CORS_ORIGINS', 'http://localhost:8420,http://127.0.0.1:8420');
$allowedList = array_map('trim', explode(',', $allowedOrigins));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Only allow explicitly configured origins (localhost by default)
if ($origin && in_array($origin, $allowedList, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
} elseif (!$origin) {
    // Same-origin requests (no Origin header) - allow for direct browser access
    header('Access-Control-Allow-Origin: http://localhost:8420');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, HX-Request');
    http_response_code(204);
    exit;
}

// Simple router
try {
    $response = match(true) {
        $path === '/' => serveUI(),
        $path === '/api/tools' => listTools($isHtmx),
        $path === '/api/services' => listServices($isHtmx),
        $path === '/api/clipboard' => getClipboard($isHtmx),
        $path === '/api/clipboard/set' => setClipboard($isHtmx),
        $path === '/api/notify' => sendNotify($isHtmx),
        $path === '/api/screenshot' => takeScreenshot($isHtmx),
        str_starts_with($path, '/api/introspect/') => introspect($path, $isHtmx),
        str_starts_with($path, '/api/call/') => callTool($path, $isHtmx),
        default => notFound($isHtmx),
    };
    echo $response;
} catch (Throwable $e) {
    http_response_code(500);
    echo $isHtmx
        ? "<div class='error'>{$e->getMessage()}</div>"
        : json_encode(['error' => $e->getMessage()]);
}
exit;

// ============================================
// API Handlers - Using Plugin System
// ============================================

function listTools(bool $isHtmx): string
{
    $tools = AppMesh::tools();

    if (!$isHtmx) {
        header('Content-Type: application/json');
        $list = array_map(fn($name, $tool) => [
            'name' => $name,
            'description' => $tool->description,
        ], array_keys($tools), array_values($tools));
        return json_encode(['tools' => $list]);
    }

    header('Content-Type: text/html');
    $html = '<h3>Available Tools (' . count($tools) . ')</h3><div class="tools">';
    foreach ($tools as $name => $tool) {
        $html .= "<div class='tool'><strong>{$name}</strong><br><small>{$tool->description}</small></div>";
    }
    $html .= '</div>';
    return $html;
}

function getClipboard(bool $isHtmx): string
{
    $content = AppMesh::call('appmesh_clipboard_get');

    if (!$isHtmx) {
        header('Content-Type: application/json');
        return json_encode(['clipboard' => $content]);
    }

    header('Content-Type: text/html');
    $escaped = htmlspecialchars($content);
    return "<div class='result'><strong>Clipboard:</strong><pre>{$escaped}</pre></div>";
}

function setClipboard(bool $isHtmx): string
{
    $content = $_POST['content'] ?? '';
    AppMesh::call('appmesh_clipboard_set', ['content' => $content]);

    header('Content-Type: text/html');
    return "<div class='result success'>Clipboard updated</div>";
}

function sendNotify(bool $isHtmx): string
{
    $title = $_POST['title'] ?? 'AppMesh';
    $body = $_POST['body'] ?? 'Hello from AppMesh!';

    AppMesh::call('appmesh_notify', ['title' => $title, 'body' => $body]);

    header('Content-Type: text/html');
    return "<div class='result success'>Notification sent: {$title}</div>";
}

function takeScreenshot(bool $isHtmx): string
{
    $mode = $_POST['mode'] ?? 'fullscreen';
    $result = AppMesh::call('appmesh_screenshot', ['mode' => $mode]);

    header('Content-Type: text/html');
    return "<div class='result success'>{$result}</div>";
}

function listServices(bool $isHtmx): string
{
    $output = AppMesh::call('appmesh_dbus_list');
    $lines = explode("\n", $output);

    // Extract KDE services list
    $kdeStart = false;
    $services = [];
    foreach ($lines as $line) {
        if (str_starts_with($line, 'KDE Services:')) {
            $kdeStart = true;
            continue;
        }
        if ($kdeStart && $line && !str_starts_with($line, 'All services:')) {
            $services[] = trim($line);
        }
        if (str_starts_with($line, 'All services:')) {
            break;
        }
    }

    if (!$isHtmx) {
        header('Content-Type: application/json');
        return json_encode(['services' => $services]);
    }

    header('Content-Type: text/html');
    $html = '';
    foreach ($services as $s) {
        $short = str_replace(['org.kde.', 'org.freedesktop.'], '', $s);
        $html .= "<div class='service' hx-get='/api/introspect/{$s}' hx-target='#main'>{$short}</div>";
    }
    return $html ?: '<div class="muted">No KDE services found</div>';
}

function introspect(string $path, bool $isHtmx): string
{
    $parts = explode('/', trim(substr($path, 16), '/'));
    $service = array_shift($parts);
    $object = '/' . implode('/', $parts);

    $output = AppMesh::call('appmesh_dbus_list', [
        'service' => $service,
        'path' => $object ?: '/'
    ]);

    if (!$isHtmx) {
        header('Content-Type: application/json');
        return json_encode(['service' => $service, 'object' => $object, 'raw' => $output]);
    }

    header('Content-Type: text/html');

    $lines = explode("\n", $output);
    $methods = $properties = $signals = $children = [];

    foreach ($lines as $line) {
        if (str_starts_with($line, 'method ')) $methods[] = substr($line, 7);
        elseif (str_starts_with($line, 'property ')) $properties[] = substr($line, 9);
        elseif (str_starts_with($line, 'signal ')) $signals[] = substr($line, 7);
        elseif ($line && !str_contains($line, ' ')) $children[] = $line;
    }

    $html = "<h3>" . htmlspecialchars($service) . "</h3>";
    $html .= "<p class='path'>" . htmlspecialchars($object ?: '/') . "</p>";

    if ($children) {
        $html .= "<details open><summary>Children (" . count($children) . ")</summary><div class='items'>";
        foreach ($children as $c) {
            $childPath = ltrim($object, '/') . '/' . $c;
            $html .= "<span class='item child' hx-get='/api/introspect/{$service}/{$childPath}' hx-target='#main'>{$c}</span>";
        }
        $html .= "</div></details>";
    }

    if ($methods) {
        $html .= "<details open><summary>Methods (" . count($methods) . ")</summary><div class='items'>";
        foreach ($methods as $m) {
            $html .= "<code class='item method'>" . htmlspecialchars($m) . "</code>";
        }
        $html .= "</div></details>";
    }

    if ($signals) {
        $html .= "<details><summary>Signals (" . count($signals) . ")</summary><div class='items'>";
        foreach ($signals as $s) {
            $html .= "<code class='item signal'>" . htmlspecialchars($s) . "</code>";
        }
        $html .= "</div></details>";
    }

    return $html;
}

function callTool(string $path, bool $isHtmx): string
{
    // /api/call/tool_name
    $toolName = substr($path, 10);
    $args = $_POST;

    if (!AppMesh::has($toolName)) {
        http_response_code(404);
        return $isHtmx
            ? "<div class='error'>Unknown tool: {$toolName}</div>"
            : json_encode(['error' => "Unknown tool: $toolName"]);
    }

    try {
        $result = AppMesh::call($toolName, $args);
    } catch (Throwable $e) {
        http_response_code(500);
        return $isHtmx
            ? "<div class='error'>{$e->getMessage()}</div>"
            : json_encode(['error' => $e->getMessage()]);
    }

    if (!$isHtmx) {
        header('Content-Type: application/json');
        return json_encode(['result' => $result]);
    }

    header('Content-Type: text/html');
    $escaped = htmlspecialchars($result ?: '(empty)');
    return "<div class='result'><strong>Result:</strong><pre>{$escaped}</pre></div>";
}

function notFound(bool $isHtmx): string
{
    http_response_code(404);
    return $isHtmx ? "<div class='error'>Not found</div>" : '{"error":"Not found"}';
}

function serveUI(): never
{
    $toolCount = count(AppMesh::tools());
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AppMesh</title>
    <script src="https://cdn.jsdelivr.net/npm/htmx.org@2.0.8/dist/htmx.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/htmx-ext-sse@2.2.2/dist/sse.min.js"></script>
    <style>
        :root {
            --bg: #0f0f1a;
            --surface: #1a1a2e;
            --primary: #3daee9;
            --success: #27ae60;
            --text: #eee;
            --muted: #666;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        .layout {
            display: grid;
            grid-template-columns: 250px 1fr 300px;
            grid-template-rows: auto 1fr;
            min-height: 100vh;
            gap: 1px;
            background: #333;
        }
        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; }
            .signals { display: none; }
        }
        header {
            grid-column: 1 / -1;
            background: var(--surface);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        header h1 { font-size: 1.25rem; color: var(--primary); }
        .badge {
            background: var(--success);
            color: white;
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
        }
        .sidebar, .main, .signals {
            background: var(--surface);
            padding: 1rem;
            overflow-y: auto;
        }
        .sidebar h2, .signals h2 {
            font-size: 0.85rem;
            color: var(--muted);
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.4rem 0.75rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .btn:hover { opacity: 0.85; }
        .btn.success { background: var(--success); }
        .service, .tool {
            padding: 0.5rem;
            margin: 0.25rem 0;
            background: rgba(255,255,255,0.05);
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .service:hover, .tool:hover { background: var(--primary); }
        .main h3 { color: var(--primary); margin-bottom: 0.5rem; }
        .path { color: var(--muted); font-family: monospace; margin-bottom: 1rem; }
        details { margin: 0.5rem 0; }
        summary {
            cursor: pointer;
            padding: 0.5rem;
            background: rgba(255,255,255,0.05);
            border-radius: 4px;
        }
        .items { padding: 0.5rem; display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .item {
            background: rgba(255,255,255,0.05);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .item.child { cursor: pointer; }
        .item.child:hover { background: var(--primary); }
        .item.method { color: #f39c12; }
        .item.signal { color: #9b59b6; }
        .result {
            background: rgba(255,255,255,0.05);
            padding: 1rem;
            border-radius: 4px;
            margin: 0.5rem 0;
        }
        .result pre {
            background: rgba(0,0,0,0.3);
            padding: 0.75rem;
            border-radius: 4px;
            overflow-x: auto;
            margin-top: 0.5rem;
            white-space: pre-wrap;
        }
        .result.success { border-left: 3px solid var(--success); }
        .error { color: #e74c3c; padding: 1rem; }
        .muted { color: var(--muted); }
        .signals { border-left: 1px solid #333; }
        .signal-stream { display: flex; flex-direction: column; gap: 0.5rem; }
        .signal {
            padding: 0.5rem;
            background: rgba(255,255,255,0.05);
            border-radius: 4px;
            font-size: 0.8rem;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .signal .time { color: var(--muted); font-family: monospace; margin-right: 0.5rem; }
        .signal.clipboard { border-left: 3px solid #3498db; }
        .signal.notification { border-left: 3px solid #f39c12; }
        .signal.activity { border-left: 3px solid #9b59b6; }
        .sse-status { padding: 0.5rem; border-radius: 4px; font-size: 0.8rem; margin-bottom: 0.5rem; }
        .sse-status.connected { background: rgba(39, 174, 96, 0.2); }
        .sse-status.disconnected { background: rgba(231, 76, 60, 0.2); }
        .tools { display: flex; flex-direction: column; gap: 0.5rem; }
        .tools small { color: var(--muted); }
    </style>
</head>
<body>
    <div class="layout">
        <header>
            <h1>AppMesh</h1>
            <span class="badge">v2.1</span>
            <span class="badge">{$toolCount} tools</span>
            <span class="badge">HTMX</span>
            <span class="badge">SSE</span>
        </header>

        <div class="sidebar">
            <h2>Quick Actions</h2>
            <div class="actions">
                <button class="btn" hx-get="/api/clipboard" hx-target="#main">Clipboard</button>
                <button class="btn success"
                        hx-post="/api/notify"
                        hx-vals='{"title":"Hello!","body":"AppMesh is working"}'
                        hx-target="#main">Notify</button>
                <button class="btn" hx-post="/api/screenshot" hx-target="#main">Screenshot</button>
                <button class="btn" hx-get="/api/tools" hx-target="#main">Tools</button>
            </div>

            <h2>D-Bus Services</h2>
            <div hx-get="/api/services" hx-trigger="load" hx-target="this">
                Loading...
            </div>
        </div>

        <div class="main">
            <div id="main">
                <p class="muted">Select a service to explore its D-Bus interface, or use quick actions above.</p>
            </div>
        </div>

        <div class="signals" hx-ext="sse" sse-connect="/sse/signals">
            <h2>Live D-Bus Signals</h2>
            <div class="signal-stream"
                 sse-swap="clipboard,notification,activity,connected"
                 hx-swap="afterbegin">
                <div class="sse-status disconnected">Connecting to signal stream...</div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    exit;
}
