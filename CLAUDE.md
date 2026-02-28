# AppMesh Project

Desktop automation through application mesh networking - the modern successor to ARexx.

**Website:** appmesh.nexus
**Repository:** github.com/markc/appmesh

## Current Status (2026-02-01)

**AppMesh** is functional with 48 tools across 7 plugins.

### Architecture

```
~/Dev/appmesh/
├── _journal/               # Development log (dated entries)
├── .claude/                # Claude Code integration
│   ├── skills/             # Invocable skills (dbus, osc, tts, cdp, midi, ws)
│   ├── runbooks/           # Operational procedures
│   ├── docs/               # Architecture documentation
│   └── guidelines/         # Development standards
├── .mcp.json               # MCP server configuration
├── Cargo.toml              # Rust workspace root
├── crates/                 # Rust crates
│   ├── appmesh-core/       # Library (cdylib+rlib) → libappmesh_core.so
│   └── appmesh-cli/        # CLI binary: appmesh type/key
├── docs/                   # Application D-Bus documentation
│   ├── kmail.md            # KMail/Akonadi interface
│   ├── okular.md           # Okular PDF viewer
│   └── ...                 # Other app-specific docs
├── server/                 # MCP server code
│   ├── appmesh-mcp.php     # Entry point
│   ├── appmesh-core.php    # Shared core (Tool class, registry)
│   ├── appmesh-ffi.php     # PHP FFI bridge to libappmesh_core.so
│   └── plugins/            # Protocol plugins
│       ├── dbus.php        # D-Bus tools (8 tools)
│       ├── osc.php         # OSC tools (3 tools)
│       ├── tts.php         # TTS/tutorial tools (6 tools)
│       ├── cdp.php         # Chrome DevTools Protocol (6 tools)
│       ├── midi.php        # PipeWire MIDI routing (8 tools)
│       ├── websocket.php   # WebSocket gateway (6 tools)
│       └── config.php      # KDE config & themes (11 tools)
└── web/                    # Web interface
    ├── index.php           # HTMX + SSE web UI
    └── sse-signals.php     # Real-time D-Bus signal streaming
```

## Skills Activation

This project has domain-specific skills available. Activate the relevant skill when working in that domain.

| Skill | Invoke | Purpose |
|-------|--------|---------|
| `/dbus` | `/dbus <action>` | D-Bus desktop automation |
| `/osc` | `/osc <target> <command>` | OSC audio control |
| `/tts` | `/tts <action>` | Text-to-speech and tutorials |
| `/cdp` | `/cdp <action> [port]` | Control Electron apps via CDP |
| `/midi` | `/midi <action>` | PipeWire MIDI routing |
| `/ws` | `/ws <action>` | WebSocket gateway |
| `/config` | `/config <action>` | KDE config & themes |
| `/email` | `/email [to] [subject]` | Compose email via KMail D-Bus |
| `/tb` | `tb email [to] [subject]` | Compose email via Thunderbird CLI |

Skills are documented in `.claude/skills/`.

## Working Features

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

**TTS Plugin (6 tools):**
- `appmesh_tts` - Convert text to speech (Gemini)
- `appmesh_tts_voices` - List available voices
- `appmesh_tutorial_script` - Generate tutorial script
- `appmesh_tutorial_full` - Generate full tutorial (script + audio)
- `appmesh_screen_record` - Control screen recording
- `appmesh_video_combine` - Merge video and audio

**CDP Plugin (6 tools):** *NEW*
- `appmesh_cdp_list` - List debugging targets from Electron app
- `appmesh_cdp_version` - Get browser/app version info
- `appmesh_cdp_eval` - Execute JavaScript in app
- `appmesh_cdp_screenshot` - Take screenshot via CDP
- `appmesh_cdp_navigate` - Navigate to URL
- `appmesh_cdp_click` - Click element by CSS selector

**MIDI Plugin (8 tools):** *NEW*
- `appmesh_midi_list` - List PipeWire MIDI ports
- `appmesh_midi_links` - Show current MIDI connections
- `appmesh_midi_devices` - List ALSA hardware devices
- `appmesh_midi_connect` - Connect output to input port
- `appmesh_midi_disconnect` - Remove connection
- `appmesh_midi_monitor` - Watch MIDI events
- `appmesh_midi_virtual` - Manage virtual MIDI ports
- `appmesh_midi_to_osc` - MIDI to OSC bridging info

**WebSocket Plugin (6 tools):** *NEW*
- `appmesh_ws_status` - Check gateway status
- `appmesh_ws_start` - Start WebSocket server
- `appmesh_ws_stop` - Stop WebSocket server
- `appmesh_ws_broadcast` - Send to all clients
- `appmesh_ws_clients` - Count connected clients
- `appmesh_ws_info` - Integration documentation

**Config Plugin (11 tools):** *NEW - Plasma 6.6 Ready*
- `appmesh_config_list` - List KDE config files
- `appmesh_config_read` - Read config file sections
- `appmesh_config_get` / `appmesh_config_set` - Get/set individual values
- `appmesh_theme_list` - List global themes
- `appmesh_theme_apply` - Apply global theme
- `appmesh_theme_current` - Get current theme
- `appmesh_theme_export` - Export current settings as theme
- `appmesh_config_backup` - Backup KDE configuration
- `appmesh_config_restore` - Restore from backup
- `appmesh_plasma_info` - Plasma version and 6.6 features

## To Run

**MCP Server (for Claude Code):**
```bash
# Automatically loaded when working in ~/Dev/appmesh/
# Or manually: php ~/Dev/appmesh/server/appmesh-mcp.php
```

**Web UI:**
```bash
cd ~/Dev/appmesh/web
php -S localhost:8420
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

Each desktop app becomes a service. Your computer becomes a personal internet of cooperating programs.

## Plugin Architecture

### Adding New Protocols

Create a new file in `server/plugins/` that returns an array of Tool objects. See `.claude/runbooks/add-plugin.md` for the full procedure.

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
            return "Result";
        }
    ),
];
```

### Current Plugins

| Plugin | Protocol | Tools |
|--------|----------|-------|
| `dbus.php` | D-Bus | 8 |
| `osc.php` | OSC/UDP | 3 |
| `tts.php` | Gemini API | 6 |
| `cdp.php` | Chrome DevTools Protocol | 6 |
| `midi.php` | PipeWire MIDI | 8 |
| `websocket.php` | WebSocket | 6 |
| `config.php` | KDE/KConfig | 11 |

### Plasma 6.6 Support

The config plugin is ready for **Plasma 6.6** (February 17, 2026) which adds:
- Save current visual configuration as new global theme
- One-click desktop appearance snapshots
- Full theme export from System Settings

### Future Projects

**kate-dbus-text-plugin** - KTextEditor plugin exposing full text editing via D-Bus
- Enables: getText, setText, insertText, removeText, find/replace
- Works with: Kate, KWrite, KDevelop, and all KTextEditor apps
- Feasibility: HIGH (KTextEditor has all APIs, needs D-Bus wrapper)
- See: `docs/kate-dbus-plugin-proposal.md`

## Guidelines

Development standards are in `.claude/guidelines/`:
- `coding.md` - PHP style and plugin patterns
- `protocols.md` - Protocol-specific guidance
- `discovery.md` - How to explore new applications

## Working Style

When exploring a new application:
1. Discover its D-Bus interfaces (`qdbus6 org.kde.appname`)
2. Experiment with methods and observe behavior
3. Document quirks, especially around authentication/secrets
4. Note the gap between documentation and reality
5. Create reproducible examples in `docs/`

Skills are documentation, not code proliferation. Use the generic `appmesh_dbus_call` tool with good documentation rather than creating dozens of specialized tools.

## Key Discoveries

### D-Bus vs KConfig

KDE uses two complementary systems:
- **D-Bus**: Runtime communication (call methods, get/set runtime properties, signals)
- **KConfig (files)**: Persistent configuration storage

Some settings (like display scaling) require direct config file editing, not D-Bus calls.

### Display Scaling Note

Fractional scaling (2.25x, 2.5x) can cause font rendering issues in Qt WebEngine apps due to QTBUG-113574. Integer scaling (1x, 2x) is more reliable.

## Development Journal

Work history is tracked in `_journal/` (dated entries). Check for context from previous sessions.
