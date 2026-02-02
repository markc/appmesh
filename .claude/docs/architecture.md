# Architecture

## Overview

AppMesh is a desktop automation system that enables Claude Code to control Linux desktop applications through various protocols. It implements the Model Context Protocol (MCP) to expose tools that bridge AI assistants with desktop software.

## Design Principles

1. **Protocols are plugins** - Each communication protocol (D-Bus, OSC, etc.) is a separate plugin
2. **Documentation over tools** - Prefer documenting capabilities over creating specialized tools
3. **Generic then specific** - Use generic tools (dbus_call, osc_send) with good docs
4. **No frameworks** - Pure PHP, minimal dependencies

## Component Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Claude Code                              │
│                  (MCP Client)                                │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼ stdio (JSON-RPC)
┌─────────────────────────────────────────────────────────────┐
│                    appmesh-mcp.php                           │
│                   (MCP Server Entry)                         │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    appmesh-core.php                          │
│              (Tool class, schema helpers)                    │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                      Plugin Loader                           │
│               (loads server/plugins/*.php)                   │
├─────────────┬─────────────┬─────────────┬──────────────────┤
│   dbus.php  │   osc.php   │   tts.php   │    future...     │
│  (8 tools)  │  (3 tools)  │  (6 tools)  │                  │
└─────────────┴─────────────┴─────────────┴──────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                   Desktop Applications                       │
├─────────────┬─────────────┬─────────────┬──────────────────┤
│   KDE/Qt    │   Ardour    │   Gemini    │   Electron       │
│   (D-Bus)   │   (OSC)     │   (API)     │   (CDP)          │
└─────────────┴─────────────┴─────────────┴──────────────────┘
```

## Plugin System

### Loading

Plugins are PHP files in `server/plugins/` that return an array of Tool objects:

```php
// server/plugins/example.php
return [
    'appmesh_example_action' => new Tool(
        description: 'Does something',
        inputSchema: schema([...], [...]),
        handler: function (array $args): string { ... }
    ),
];
```

### Registration

The MCP server dynamically loads all `*.php` files from the plugins directory and merges their tools into the registry.

### Naming Convention

Tools follow the pattern: `appmesh_<plugin>_<action>`

## Protocol Mapping

| Protocol | Transport | Use Case |
|----------|-----------|----------|
| D-Bus | Session bus | KDE apps, system services |
| OSC | UDP | Audio apps (Ardour, Carla) |
| Gemini API | HTTPS | TTS, content generation |
| CDP | WebSocket | Electron apps (future) |
| MIDI | PipeWire | Hardware controllers (future) |

## Data Flow

### Tool Invocation

```
Claude Code
    │
    ▼ {"method": "tools/call", "params": {"name": "appmesh_clipboard_get"}}
appmesh-mcp.php
    │
    ▼ dispatch to plugin handler
dbus.php handler
    │
    ▼ shell_exec('qdbus6 org.kde.klipper ...')
Klipper (D-Bus)
    │
    ▼ clipboard contents
Return to Claude Code
```

### Event Streaming (Web UI)

```
D-Bus signals
    │
    ▼ sse-signals.php (SSE)
Browser
    │
    ▼ display/react
```

## Configuration

### MCP Server

Configured in `.mcp.json`:

```json
{
  "mcpServers": {
    "appmesh": {
      "command": "/usr/sbin/php",
      "args": ["/home/markc/Dev/appmesh/server/appmesh-mcp.php"]
    }
  }
}
```

### Plugin-Specific

Plugins read configuration from environment or hardcoded defaults:
- OSC ports: Defined in plugin
- API keys: Environment variables
- Paths: XDG conventions

## Future Expansion

### Chrome DevTools Protocol

Connect to Electron apps via CDP for:
- VS Code automation
- Browser control
- JavaScript execution in running apps

### PipeWire MIDI

Route MIDI from hardware controllers for:
- DAW control surfaces
- MIDI → OSC bridging
- Programmatic MIDI

### WebSocket Gateway

Bidirectional communication for:
- Browser-based UIs
- External tool integration
- Real-time event handling
