#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * AppMesh MCP Server v2.1 - Plugin Architecture
 *
 * Desktop Automation via Application Mesh - The InterAPP glue.
 * A modular MCP server that loads protocol-specific plugins.
 *
 * Supported protocols (via plugins):
 * - D-Bus: KDE desktop automation
 * - OSC: Pro audio (Ardour, Carla, Guitarix)
 * - Future: MIDI, WebSocket, REST APIs, config files, etc.
 *
 * Protocol: JSON-RPC 2.0 over newline-delimited stdio
 * Spec: https://modelcontextprotocol.io/specification/2024-11-05
 */

ob_implicit_flush(true);
while (ob_get_level()) ob_end_flush();

// Load shared core (Tool class, schema helpers, plugin loader)
require_once __DIR__ . '/appmesh-core.php';

// ============================================================================
// MCP Server Implementation
// ============================================================================

final readonly class McpServer
{
    private const PROTOCOL_VERSION = '2024-11-05';

    public function __construct(
        private string $name,
        private string $version,
        /** @var array<string, Tool> */
        private array $tools,
    ) {}

    public function run(): never
    {
        $this->log("Starting v{$this->version} with " . count($this->tools) . " tools");

        while (!feof(STDIN)) {
            $line = fgets(STDIN);
            if ($line === false || ($line = trim($line)) === '') {
                continue;
            }

            $request = json_decode($line, true);
            if (!is_array($request)) {
                continue;
            }

            $this->dispatch($request);
        }

        $this->log("Shutting down");
        exit(0);
    }

    private function dispatch(array $request): void
    {
        $method = $request['method'] ?? '';
        $id = $request['id'] ?? null;
        $params = $request['params'] ?? [];

        $this->log("Received: $method" . ($id !== null ? " (id: $id)" : " (notification)"));

        match ($method) {
            'initialize' => $this->handleInitialize($id),
            'notifications/initialized' => $this->log("Client initialized"),
            'tools/list' => $this->handleToolsList($id),
            'tools/call' => $this->handleToolsCall($id, $params),
            'ping' => $this->respond($id, (object)[]),
            default => $this->handleUnknown($method, $id),
        };
    }

    private function handleInitialize(mixed $id): void
    {
        $this->respond($id, [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => ['tools' => (object)[]],
            'serverInfo' => [
                'name' => $this->name,
                'version' => $this->version,
            ],
        ]);
    }

    private function handleToolsList(mixed $id): void
    {
        $tools = array_map(
            fn(string $name, Tool $tool): array => [
                'name' => $name,
                'description' => $tool->description,
                'inputSchema' => $tool->inputSchema,
            ],
            array_keys($this->tools),
            array_values($this->tools)
        );

        $this->respond($id, ['tools' => $tools]);
    }

    private function handleToolsCall(mixed $id, array $params): void
    {
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        $this->log("Calling tool: $name");

        if (!isset($this->tools[$name])) {
            $this->respondToolResult($id, "Unknown tool: $name", isError: true);
            return;
        }

        try {
            $result = $this->tools[$name]->execute($args);
            $this->respondToolResult($id, $result);
        } catch (\Throwable $e) {
            $this->log("Tool error: {$e->getMessage()}");
            $this->respondToolResult($id, "Error: {$e->getMessage()}", isError: true);
        }
    }

    private function handleUnknown(string $method, mixed $id): void
    {
        if ($id !== null) {
            $this->respondError($id, -32601, "Method not found: $method");
        }
    }

    private function respondToolResult(mixed $id, string $text, bool $isError = false): void
    {
        $result = ['content' => [['type' => 'text', 'text' => $text]]];
        if ($isError) {
            $result['isError'] = true;
        }
        $this->respond($id, $result);
    }

    private function respond(mixed $id, array|object $result): void
    {
        $this->send(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    private function respondError(mixed $id, int $code, string $message): void
    {
        $this->send(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]]);
    }

    private function send(array $message): void
    {
        $json = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        fwrite(STDOUT, $json . "\n");
        fflush(STDOUT);
    }

    private function log(string $msg): void
    {
        fwrite(STDERR, "[{$this->name}] $msg\n");
    }
}

// ============================================================================
// Run Server
// ============================================================================

$tools = AppMesh::tools();

if (empty($tools)) {
    fwrite(STDERR, "[appmesh] Warning: No plugins loaded\n");
}

(new McpServer('appmesh', '2.1.0', $tools))->run();
