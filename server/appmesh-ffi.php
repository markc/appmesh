<?php
declare(strict_types=1);
/**
 * AppMesh FFI — PHP FFI bridge to libappmesh_core.so
 *
 * Lazy singleton that loads the Rust shared library once per process.
 * Returns null if the library is missing or initialization fails,
 * allowing callers to fall back to subprocess execution.
 */

final class AppMeshFFI
{
    private static bool $attempted = false;
    private static ?self $instance = null;

    private \FFI $ffi;
    private \FFI\CData $handle;

    private function __construct(\FFI $ffi, \FFI\CData $handle)
    {
        $this->ffi = $ffi;
        $this->handle = $handle;
    }

    /**
     * Get the singleton instance. Returns null if FFI is unavailable.
     * Only attempts loading once per process.
     */
    public static function instance(): ?self
    {
        if (self::$attempted) {
            return self::$instance;
        }
        self::$attempted = true;

        if (!extension_loaded('ffi')) {
            AppMeshLogger::info('FFI extension not loaded, using subprocess fallback');
            return null;
        }

        $soPath = self::findLibrary();
        if ($soPath === null) {
            AppMeshLogger::info('libappmesh_core.so not found, using subprocess fallback');
            return null;
        }

        $headerPath = self::findHeader();
        if ($headerPath === null) {
            AppMeshLogger::warning('appmesh.h not found, using subprocess fallback');
            return null;
        }

        try {
            $header = file_get_contents($headerPath);
            $ffi = \FFI::cdef($header, $soPath);
            $handle = $ffi->appmesh_init();

            if (\FFI::isNull($handle)) {
                AppMeshLogger::warning('appmesh_init() returned null (KWin EIS unavailable?)');
                return null;
            }

            self::$instance = new self($ffi, $handle);
            AppMeshLogger::info('FFI loaded successfully', ['library' => $soPath]);
            return self::$instance;
        } catch (\Throwable $e) {
            AppMeshLogger::warning('FFI initialization failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Type text into the focused window.
     * Returns true on success, false on error.
     */
    public function typeText(string $text, int $delayUs = 5000): bool
    {
        $result = $this->ffi->appmesh_type_text($this->handle, $text, $delayUs);
        if ($result === -2) {
            // Handle went stale — destroy and mark for recreation
            $this->destroy();
            return false;
        }
        return $result === 0;
    }

    /**
     * Send a key combo to the focused window.
     * Returns true on success, false on error.
     */
    public function sendKey(string $combo, int $delayUs = 5000): bool
    {
        $result = $this->ffi->appmesh_send_key($this->handle, $combo, $delayUs);
        if ($result === -2) {
            $this->destroy();
            return false;
        }
        return $result === 0;
    }

    /**
     * Open a named port and execute a command with JSON args.
     * Returns decoded result array on success, null on failure.
     */
    public function portExecute(string $port, string $command, array $args = []): ?array
    {
        $portHandle = $this->ffi->appmesh_port_open($port);
        if ($portHandle === null) {
            return null;
        }

        $argsJson = empty($args) ? null : json_encode($args);
        $resultPtr = $this->ffi->appmesh_port_execute($portHandle, $command, $argsJson);
        $this->ffi->appmesh_port_free($portHandle);

        if ($resultPtr === null) {
            return null;
        }

        $json = \FFI::string($resultPtr);
        $this->ffi->appmesh_string_free($resultPtr);

        return json_decode($json, true);
    }

    /**
     * Destroy the handle and mark instance as unavailable.
     */
    private function destroy(): void
    {
        $this->ffi->appmesh_free($this->handle);
        self::$instance = null;
        self::$attempted = false; // Allow retry on next call
        AppMeshLogger::info('FFI handle destroyed, will retry on next call');
    }

    public function __destruct()
    {
        if (isset($this->ffi, $this->handle) && !FFI::isNull($this->handle)) {
            $this->ffi->appmesh_free($this->handle);
        }
    }

    /**
     * Search for the shared library in known locations.
     */
    private static function findLibrary(): ?string
    {
        $projectRoot = dirname(__DIR__);
        $searchPaths = [
            $projectRoot . '/target/release/libappmesh_core.so',
            $projectRoot . '/target/release/libappmesh.so',
            $_SERVER['HOME'] . '/.local/lib/libappmesh_core.so',
            '/usr/local/lib/libappmesh_core.so',
        ];

        // Check env override first
        $envPath = getenv('APPMESH_LIB_PATH');
        if ($envPath !== false && $envPath !== '') {
            array_unshift($searchPaths, $envPath);
        }

        foreach ($searchPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Search for the C header file.
     */
    private static function findHeader(): ?string
    {
        $projectRoot = dirname(__DIR__);
        $searchPaths = [
            $projectRoot . '/crates/appmesh-core/appmesh.h',
            $_SERVER['HOME'] . '/.local/include/appmesh.h',
            '/usr/local/include/appmesh.h',
        ];

        foreach ($searchPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
