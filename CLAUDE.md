# AppMesh Project

Desktop automation through application mesh networking - the modern successor to ARexx.

**Website:** appmesh.nexus
**Repository:** github.com/markc/appmesh

## Current Status (2026-01-25)

**AppMesh** is functional with 11 tools across 2 protocol plugins.

### Architecture

```
~/.appmesh/
├── CLAUDE.md           # This file - project context
├── .mcp.json           # MCP server configuration
├── docs/               # Application integration documentation
│   ├── kmail.md        # KMail/Akonadi D-Bus interface
│   ├── system-settings.md  # KDE system configuration
│   └── ...             # Other app-specific docs
├── server/             # MCP server code
│   ├── appmesh-mcp.php     # MCP server entry point
│   ├── appmesh-core.php    # Shared core (Tool class, registry)
│   └── plugins/            # Protocol plugins
│       ├── dbus.php        # D-Bus tools (8 tools)
│       └── osc.php         # OSC tools (3 tools)
└── web/                # Web interface
    ├── index.php           # HTMX + SSE web UI
    └── sse-signals.php     # Real-time D-Bus signal streaming
```

### Working Features

**D-Bus Plugin (8 tools):**
- `appmesh_clipboard_get` / `appmesh_clipboard_set` - Klipper clipboard
- `appmesh_notify` - Desktop notifications
- `appmesh_screenshot` - Spectacle screenshots
- `appmesh_dbus_call` - Generic D-Bus method calls
- `appmesh_dbus_list` - Service discovery and introspection
- `appmesh_kwin_list_windows` / `appmesh_kwin_activate_window` - Window management

**OSC Plugin (3 tools):**
- `appmesh_osc_send` - Generic OSC messages
- `appmesh_osc_ardour` - Ardour DAW control (port 3819)
- `appmesh_osc_carla` - Carla plugin host (port 22752)

### To Run

**MCP Server (for Claude Code):**
```bash
# Automatically loaded when working in ~/.appmesh/
# Or manually: php ~/.appmesh/server/appmesh-mcp.php
```

**Web UI:**
```bash
cd ~/.appmesh/web
php -S localhost:8420
# Or: frankenphp php-server --listen :8420
# Open http://localhost:8420
```

## Project Vision

### What Is AppMesh?

**ARexx** was a feature on Amiga computers (1980s) that let different programs talk to each other through simple scripts - true desktop automation.

**D-Bus** is how Linux programs send messages to each other today - an internal message bus for the desktop.

**AppMesh** brings ARexx-style cooperation to modern Linux, extended to include web browsers and AI assistants. It creates a mesh network of applications that can be orchestrated through natural language.

### The Core Insight

Modern desktop programs are isolated. They share a screen but rarely cooperate. D-Bus provides the plumbing for cooperation, but it's underutilized and poorly documented for practical scripting.

AppMesh addresses this by:
1. Documenting how real applications actually use D-Bus (quirks and all)
2. Building PHP-based coordination (bridging web and desktop)
3. Enabling Claude Code to orchestrate desktop applications

### The Vision

Imagine describing what you want: "Take a screenshot, shrink it, upload to cloud storage, give me the link." Instead of manual steps across programs, a script (or AI) coordinates them automatically.

Each desktop app becomes a service. Your computer becomes a personal internet of cooperating programs. PHP acts as coordinator - it already handles web requests, multiple connections, and browser communication. Teaching it D-Bus makes it a bridge between web and desktop worlds.

## Plugin Architecture

### Adding New Protocols

Create a new file in `server/plugins/` that returns an array of Tool objects:

```php
<?php
return [
    'appmesh_myprotocol_action' => new Tool(
        description: 'What this tool does',
        inputSchema: schema(
            ['param' => prop('string', 'Parameter description')],
            ['param']  // required parameters
        ),
        handler: function (array $args): string {
            // Implementation
            return "Result";
        }
    ),
];
```

### Future Plugins

- **config.php** - XDG/KConfig file editing for persistent settings
- **midi.php** - MIDI device control
- **websocket.php** - WebSocket connections
- **rest.php** - REST API integration

## Working Style

When exploring a new application:
1. Discover its D-Bus interfaces (`qdbus6 org.kde.appname`)
2. Experiment with methods and observe behavior
3. Document quirks, especially around authentication/secrets
4. Note the gap between documentation and reality
5. Create reproducible examples in docs/

Skills are documentation, not code proliferation. Use the generic `appmesh_dbus_call` tool with good documentation rather than creating dozens of specialized tools.

## Key Discoveries

### D-Bus vs KConfig

KDE uses two complementary systems:
- **D-Bus**: Runtime communication (call methods, get/set runtime properties, signals)
- **KConfig (files)**: Persistent configuration storage

Some settings (like display scaling) require direct config file editing, not D-Bus calls. This is why a config plugin is planned.

### Display Scaling Note

Fractional scaling (2.25x, 2.5x) can cause font rendering issues in Qt WebEngine apps due to QTBUG-113574. Integer scaling (1x, 2x) is more reliable.
