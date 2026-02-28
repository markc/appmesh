<?php
/**
 * AppMesh Keyboard Plugin
 *
 * Injects keyboard input into the focused window via ei-type (libei/KWin EIS).
 * Tools: type text, send key combos.
 */

return [
    'appmesh_keyboard_type' => new Tool(
        description: 'Type text into the focused window using ei-type (libei keyboard injection)',
        inputSchema: schema(
            ['text' => prop('string', 'The text to type into the focused window')],
            ['text']
        ),
        handler: function (array $args): string {
            if ($error = appmesh_validate($args, ['text'])) {
                return "Error: {$error}";
            }

            $text = $args['text'];
            $result = appmesh_exec('printf %s ' . escapeshellarg($text) . ' | ei-type');

            if (!$result['success']) {
                AppMeshLogger::warning('keyboard_type failed', [
                    'exitCode' => $result['exitCode'],
                    'output' => $result['output'],
                ]);
                return "Error: ei-type failed (exit {$result['exitCode']}): {$result['output']}";
            }

            return "Typed " . strlen($text) . " characters into focused window";
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
            $result = appmesh_exec('ei-type --key ' . escapeshellarg($key));

            if (!$result['success']) {
                AppMeshLogger::warning('keyboard_key failed', [
                    'exitCode' => $result['exitCode'],
                    'output' => $result['output'],
                ]);
                return "Error: ei-type --key failed (exit {$result['exitCode']}): {$result['output']}";
            }

            return "Sent key combo: {$key}";
        }
    ),
];
