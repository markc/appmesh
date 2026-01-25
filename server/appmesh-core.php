<?php
declare(strict_types=1);
/**
 * AppMesh Core - Shared Plugin Infrastructure
 *
 * This file contains the core classes and functions shared between:
 * - appmesh-mcp.php (Claude Code MCP interface)
 * - web/appmesh-sse.php (Browser web UI)
 */

// ============================================================================
// Environment Configuration (.env loader)
// ============================================================================

(function() {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) return;

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Don't override existing env vars
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
        }
    }
})();

/** Get env var with optional default */
function appmesh_env(string $key, string $default = ''): string {
    return getenv($key) ?: $default;
}

// ============================================================================
// Tool Definition
// ============================================================================

final readonly class Tool
{
    public function __construct(
        public string $description,
        public array $inputSchema,
        /** @var callable(array): string */
        private mixed $handler,
    ) {}

    public function execute(array $args): string
    {
        return ($this->handler)($args);
    }
}

// ============================================================================
// Schema Builder Helpers
// ============================================================================

function schema(array $properties = [], array $required = []): array
{
    return [
        'type' => 'object',
        'properties' => empty($properties) ? (object)[] : $properties,
        'required' => $required,
    ];
}

function prop(string $type, string $description, array $extra = []): array
{
    return ['type' => $type, 'description' => $description, ...$extra];
}

// ============================================================================
// Plugin Loader
// ============================================================================

function appmesh_load_plugins(string $pluginDir): array
{
    $tools = [];
    $pluginFiles = glob($pluginDir . '/*.php');

    if ($pluginFiles === false) {
        return $tools;
    }

    foreach ($pluginFiles as $pluginFile) {
        $pluginName = basename($pluginFile, '.php');
        $pluginTools = require $pluginFile;

        if (is_array($pluginTools)) {
            $tools = array_merge($tools, $pluginTools);
        }
    }

    return $tools;
}

// ============================================================================
// Tool Registry (singleton for web UI convenience)
// ============================================================================

class AppMesh
{
    private static ?array $tools = null;

    public static function tools(): array
    {
        if (self::$tools === null) {
            self::$tools = appmesh_load_plugins(__DIR__ . '/plugins');
        }
        return self::$tools;
    }

    public static function call(string $name, array $args = []): string
    {
        $tools = self::tools();
        if (!isset($tools[$name])) {
            throw new RuntimeException("Unknown tool: $name");
        }
        return $tools[$name]->execute($args);
    }

    public static function has(string $name): bool
    {
        return isset(self::tools()[$name]);
    }
}
