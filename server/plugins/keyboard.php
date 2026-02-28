<?php
/**
 * AppMesh Keyboard Plugin
 *
 * Injects keyboard input into the focused window via KWin EIS.
 * Uses FFI (libappmesh_core.so) for fast injection (~0.05ms),
 * falls back to ei-type subprocess if FFI is unavailable (~50-100ms).
 */

// Load FFI bridge
require_once __DIR__ . '/../appmesh-ffi.php';

return [
    'appmesh_keyboard_type' => new Tool(
        description: 'Type text into the focused window using KWin EIS keyboard injection',
        inputSchema: schema(
            ['text' => prop('string', 'The text to type into the focused window')],
            ['text']
        ),
        handler: function (array $args): string {
            if ($error = appmesh_validate($args, ['text'])) {
                return "Error: {$error}";
            }

            $text = $args['text'];
            $len = strlen($text);

            // Try FFI first
            $ffi = AppMeshFFI::instance();
            if ($ffi !== null) {
                if ($ffi->typeText($text)) {
                    return "Typed {$len} characters into focused window (FFI)";
                }
                AppMeshLogger::warning('FFI type_text failed, falling back to subprocess');
            }

            // Subprocess fallback
            $result = appmesh_exec('printf %s ' . escapeshellarg($text) . ' | ei-type');

            if (!$result['success']) {
                AppMeshLogger::warning('keyboard_type failed', [
                    'exitCode' => $result['exitCode'],
                    'output' => $result['output'],
                ]);
                return "Error: ei-type failed (exit {$result['exitCode']}): {$result['output']}";
            }

            return "Typed {$len} characters into focused window (subprocess)";
        }
    ),

    'appmesh_keyboard_key' => new Tool(
        description: 'Send a key combo to the focused window (e.g. ctrl+v, enter, alt+tab)',
        inputSchema: schema(
            ['key' => prop('string', 'Key combo to send (e.g. "ctrl+v", "enter", "ctrl+shift+s")')],
            ['key']
        ),
        handler: function (array $args): string {
            if ($error = appmesh_validate($args, ['key'])) {
                return "Error: {$error}";
            }

            $key = $args['key'];

            // Try FFI first
            $ffi = AppMeshFFI::instance();
            if ($ffi !== null) {
                if ($ffi->sendKey($key)) {
                    return "Sent key combo: {$key} (FFI)";
                }
                AppMeshLogger::warning('FFI send_key failed, falling back to subprocess');
            }

            // Subprocess fallback
            $result = appmesh_exec('ei-type --key ' . escapeshellarg($key));

            if (!$result['success']) {
                AppMeshLogger::warning('keyboard_key failed', [
                    'exitCode' => $result['exitCode'],
                    'output' => $result['output'],
                ]);
                return "Error: ei-type --key failed (exit {$result['exitCode']}): {$result['output']}";
            }

            return "Sent key combo: {$key} (subprocess)";
        }
    ),
];
