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

// ============================================================================
// Secure Temp File Helper
// ============================================================================

/**
 * Create a secure temporary file path using XDG_RUNTIME_DIR
 * Falls back to /tmp with restricted permissions if runtime dir unavailable
 *
 * @param string $prefix Prefix for the temp file (e.g., 'appmesh-screenshot')
 * @param string $extension File extension without dot (e.g., 'png')
 * @return string Full path to the temp file
 */
function appmesh_tempfile(string $prefix = 'appmesh', string $extension = ''): string
{
    $uid = posix_getuid();
    $runtimeDir = getenv('XDG_RUNTIME_DIR') ?: "/run/user/{$uid}";

    // Use XDG_RUNTIME_DIR/appmesh if available (mode 0700, user-only)
    if (is_dir($runtimeDir) && is_writable($runtimeDir)) {
        $tempDir = "{$runtimeDir}/appmesh";
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0700, true);
        }
    } else {
        // Fallback to /tmp with unique subdir
        $tempDir = "/tmp/appmesh-{$uid}";
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0700, true);
        }
    }

    $filename = $prefix . '_' . uniqid() . '_' . getmypid();
    if ($extension) {
        $filename .= '.' . ltrim($extension, '.');
    }

    return "{$tempDir}/{$filename}";
}

// ============================================================================
// Shell Execution Helper
// ============================================================================

/**
 * Execute a shell command with proper error handling
 *
 * @param string $command Command to execute
 * @param bool $captureStderr Whether to capture stderr (default: true)
 * @return array{output: string, exitCode: int, success: bool}
 */
function appmesh_exec(string $command, bool $captureStderr = true): array
{
    $output = [];
    $exitCode = 0;

    if ($captureStderr) {
        $command .= ' 2>&1';
    }

    exec($command, $output, $exitCode);

    $outputStr = implode("\n", $output);

    // Log command execution for debugging
    AppMeshLogger::debug("exec", [
        'command' => substr($command, 0, 200),
        'exitCode' => $exitCode,
        'outputLen' => strlen($outputStr),
    ]);

    return [
        'output' => $outputStr,
        'exitCode' => $exitCode,
        'success' => $exitCode === 0,
    ];
}

/**
 * Execute a shell command and return output string (convenience wrapper)
 * Logs errors but returns output even on failure
 */
function appmesh_shell(string $command): string
{
    $result = appmesh_exec($command);
    if (!$result['success']) {
        AppMeshLogger::warning("shell command failed", [
            'command' => substr($command, 0, 100),
            'exitCode' => $result['exitCode'],
        ]);
    }
    return trim($result['output']);
}

// ============================================================================
// Argument Validation Helper
// ============================================================================

/**
 * Validate required arguments are present and non-empty
 *
 * @param array $args Arguments to validate
 * @param array $required List of required argument names
 * @return string|null Error message if validation fails, null if valid
 */
function appmesh_validate(array $args, array $required): ?string
{
    $missing = [];
    foreach ($required as $key) {
        if (!isset($args[$key]) || (is_string($args[$key]) && trim($args[$key]) === '')) {
            $missing[] = $key;
        }
    }

    if ($missing) {
        return "Missing required arguments: " . implode(', ', $missing);
    }

    return null;
}

/**
 * Get argument with type coercion and default
 */
function appmesh_arg(array $args, string $key, mixed $default = null, string $type = 'string'): mixed
{
    $value = $args[$key] ?? $default;

    if ($value === null) {
        return $default;
    }

    return match ($type) {
        'int', 'integer' => (int) $value,
        'float', 'double' => (float) $value,
        'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
        'array' => is_array($value) ? $value : [$value],
        default => (string) $value,
    };
}

// ============================================================================
// Logging Infrastructure
// ============================================================================

class AppMeshLogger
{
    private static ?string $logFile = null;
    private static bool $enabled = true;

    /**
     * Initialize logger with log file path
     */
    public static function init(?string $logFile = null): void
    {
        if ($logFile === null) {
            $uid = posix_getuid();
            $runtimeDir = getenv('XDG_RUNTIME_DIR') ?: "/run/user/{$uid}";
            if (is_dir($runtimeDir) && is_writable($runtimeDir)) {
                $logFile = "{$runtimeDir}/appmesh.log";
            } else {
                $logFile = "/tmp/appmesh-{$uid}.log";
            }
        }
        self::$logFile = $logFile;
    }

    /**
     * Disable logging (for testing or high-performance scenarios)
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * Enable logging
     */
    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * Log a message at specified level
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        if (!self::$enabled) {
            return;
        }

        if (self::$logFile === null) {
            self::init();
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        $line = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";

        // Append to log file (non-blocking, ignore errors)
        @file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('DEBUG', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }
}
