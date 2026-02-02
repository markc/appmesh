# Coding Guidelines

## General Principles

1. **PHP 8.4+ features** - Use constructor promotion, named arguments, match expressions
2. **Plugins are self-contained** - Each plugin returns an array of Tool objects
3. **Keep it simple** - No frameworks, minimal dependencies
4. **Documentation over code** - Skills are documentation, not tool proliferation

## PHP Style

### Formatting

- PSR-12 coding style
- 4 spaces indentation (no tabs)
- One class/function per responsibility

### Type Declarations

```php
// Always use explicit types
function handleClipboard(array $args): string
{
    return shell_exec('qdbus6 org.kde.klipper ...');
}

// Use nullable types when appropriate
function getWindow(?string $id = null): ?array
{
    // ...
}
```

### Tool Definition Pattern

```php
return [
    'appmesh_toolname' => new Tool(
        description: 'What this tool does',
        inputSchema: schema(
            ['param' => prop('string', 'Parameter description')],
            ['param']  // required parameters
        ),
        handler: function (array $args): string {
            // Implementation
            return json_encode($result);
        }
    ),
];
```

## Plugin Structure

### File Organization

```
server/plugins/
├── dbus.php      # D-Bus tools
├── osc.php       # OSC tools
├── tts.php       # TTS/tutorial tools
├── cdp.php       # Chrome DevTools Protocol (future)
└── midi.php      # PipeWire MIDI (future)
```

### Plugin Template

```php
<?php

declare(strict_types=1);

// Plugin: <name>
// Tools for <description>

return [
    'appmesh_<plugin>_<action>' => new Tool(
        description: '...',
        inputSchema: schema([...], [...]),
        handler: function (array $args): string {
            // ...
        }
    ),
];
```

## Error Handling

```php
// Return errors as JSON
handler: function (array $args): string {
    $result = shell_exec($command);

    if ($result === null) {
        return json_encode(['error' => 'Command failed']);
    }

    return $result;
}
```

## Shell Commands

```php
// Prefer qdbus6 for D-Bus
shell_exec('qdbus6 org.kde.klipper /klipper getClipboardContents');

// Escape arguments properly
$safe = escapeshellarg($userInput);
shell_exec("command {$safe}");

// Use full paths for reliability
shell_exec('/usr/bin/spectacle -b -f -o /tmp/screenshot.png');
```

## Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Tool name | snake_case | `appmesh_clipboard_get` |
| Plugin file | lowercase | `dbus.php` |
| Function | camelCase | `handleRequest()` |
| Variable | camelCase | `$toolRegistry` |
| Constant | UPPER_SNAKE | `MCP_VERSION` |

## Comments

- Prefer self-documenting code over comments
- Document plugin purpose at top of file
- No inline comments unless logic is truly complex

## Testing

Tools are tested manually via Claude Code invocation. For complex logic, extract into testable functions.
