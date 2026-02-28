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
    'mail' => [
        'connect' => ['Connect to JMAP mail server', [
            'url' => prop('string', 'JMAP server URL'),
            'user' => prop('string', 'Email/username'),
            'pass' => prop('string', 'Password'),
        ], []],
        'status' => ['Check mail connection status', [], []],
        'mailboxes' => ['List all mailboxes', [], []],
        'query' => ['Query emails in a mailbox', [
            'mailbox' => prop('string', 'Mailbox name or ID (default: Inbox)'),
            'limit' => prop('string', 'Max results (default: 20)'),
        ], []],
        'read' => ['Read an email by ID', [
            'id' => prop('string', 'Email ID'),
        ], ['id']],
        'mark_read' => ['Mark email as read', [
            'id' => prop('string', 'Email ID'),
        ], ['id']],
        // Phase 2: Send & Compose
        'identities' => ['List sender identities', [], []],
        'send' => ['Send an email', [
            'to' => prop('string', 'Recipient email address'),
            'subject' => prop('string', 'Email subject'),
            'body' => prop('string', 'Email body text'),
            'from' => prop('string', 'From address (default: first identity)'),
        ], ['to', 'subject', 'body']],
        'reply' => ['Reply to an email', [
            'id' => prop('string', 'Email ID to reply to'),
            'body' => prop('string', 'Reply body text'),
        ], ['id', 'body']],
        // Phase 3: Mail Management
        'move' => ['Move email to another mailbox', [
            'id' => prop('string', 'Email ID'),
            'mailbox' => prop('string', 'Target mailbox name or ID'),
        ], ['id', 'mailbox']],
        'delete' => ['Delete email (move to Trash, or permanent)', [
            'id' => prop('string', 'Email ID'),
            'permanent' => prop('string', 'Permanently delete (true/false, default: false)'),
        ], ['id']],
        'flag' => ['Flag an email', [
            'id' => prop('string', 'Email ID'),
        ], ['id']],
        'unflag' => ['Unflag an email', [
            'id' => prop('string', 'Email ID'),
        ], ['id']],
        'mark_unread' => ['Mark email as unread', [
            'id' => prop('string', 'Email ID'),
        ], ['id']],
        'search' => ['Full-text search across all mailboxes', [
            'text' => prop('string', 'Search text'),
            'limit' => prop('string', 'Max results (default: 20)'),
        ], ['text']],
        'attachment_list' => ['List attachments on an email', [
            'id' => prop('string', 'Email ID'),
        ], ['id']],
        'attachment_download' => ['Download attachment by blob ID', [
            'id' => prop('string', 'Blob ID'),
            'name' => prop('string', 'Filename to save as'),
        ], ['id']],
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
