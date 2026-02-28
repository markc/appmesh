# AMP — AppMesh Protocol Specification

**Version:** 0.2.0
**Date:** 2026-02-28
**Authors:** Mark Constable / Claude
**Domain:** appmesh.nexus

> Every desktop app becomes a scriptable service. Your computer becomes a personal internet of cooperating programs.

---

## 1. What AMP Is

On the Amiga, **ARexx** was built into the OS. Every serious application exposed an ARexx port — a named endpoint that accepted commands and returned results. A three-line script could tell a paint program to render an image, a word processor to insert the filename, and a file manager to move it to a folder. No APIs to learn, no SDKs to install — just send commands to named ports in a common language.

**AMP** (AppMesh Protocol) recreates this for modern Linux desktops, extended to span multiple machines over WireGuard. Every application — desktop, web, or remote service — exposes ports. PHP is the scripting language. DNS provides addressing.

| Amiga / ARexx | AMP |
|---|---|
| `ADDRESS 'APP' 'COMMAND'` | `appmesh port clipboard get` (CLI) |
| ARexx port name | DNS address: `clipboard.appmesh.cachyos.amp` |
| `rx script.rexx` | `amp run script.amp.php` |
| REXX (universal glue) | PHP 8.5+ (universal glue) |
| Single machine IPC | Multi-node mesh (WireGuard + DNS) |

### Why PHP

- **Already the web language** — every project in the ecosystem (markweb, NetServa, LaRaMail, LaRaCRM) is PHP/Laravel. AMP scripts use the same language the applications are written in.
- **FFI gives native access** — PHP 8.5's FFI calls directly into `libappmesh_core.so` at ~0.05ms per call. No serialisation boundary, no subprocess overhead.
- **Zero learning curve** — developers writing AMP scripts already know the language, the patterns, and the tooling.
- **Runs on every node** — PHP is present on every AMP node by definition (workstation, web server, headless daemon).

---

## 2. What Exists Today

Every claim in this section is backed by working code in the repository.

### 2.1 Rust Core (`crates/appmesh-core/`)

A `cdylib` + `rlib` crate producing `libappmesh_core.so`. Exports 8 C ABI symbols:

| Symbol | Purpose |
|---|---|
| `appmesh_init()` → handle | Create input handle (EIS/libei connection to KWin) |
| `appmesh_type_text(handle, text, delay)` → status | Type text into focused window |
| `appmesh_send_key(handle, combo, delay)` → status | Send key combo (e.g. `ctrl+v`) |
| `appmesh_free(handle)` | Destroy input handle |
| `appmesh_port_open(name)` → port | Open a named port |
| `appmesh_port_execute(port, cmd, args_json)` → json | Execute command, return JSON result |
| `appmesh_port_free(port)` | Close port |
| `appmesh_string_free(ptr)` | Free string returned by port_execute |

Return conventions: `0` = success, `-1` = error, `-2` = null/stale handle. Port execute returns `{"ok": value}` or `{"error": {"code": N, "message": "..."}}`.

**The `AppMeshPort` trait** — every scriptable subsystem implements this:

```rust
pub trait AppMeshPort: Send {
    fn name(&self) -> &str;
    fn commands(&self) -> Vec<CommandDef>;
    fn execute(&self, cmd: &str, args: &HashMap<String, String>) -> PortResult;
}
```

**5 ports implemented:**

| Port | Commands | Transport |
|---|---|---|
| `input` | `type_text`, `send_key` | KWin EIS via libei (FFI) |
| `clipboard` | `get`, `set` | Klipper D-Bus via zbus (FFI) |
| `notify` | `send` | freedesktop Notifications D-Bus (FFI) |
| `screenshot` | `take` | Spectacle subprocess |
| `windows` | `list`, `activate` | KWin script + qdbus6 subprocess |

Each D-Bus port creates its own tokio runtime + zbus connection to avoid nested-runtime deadlock. The `input` port holds a `Mutex<InputHandle>` wrapping the EIS session — D-Bus connection must stay alive or KWin invalidates EIS.

### 2.2 PHP MCP Server (`server/`)

10 plugins, 56 tools, served as an MCP JSON-RPC server (`appmesh-mcp.php`):

| Plugin | Protocol | Tools |
|---|---|---|
| `dbus.php` | D-Bus | 8 — clipboard, notify, screenshot, dbus_call/list, kwin windows |
| `osc.php` | OSC/UDP | 3 — generic send, Ardour, Carla |
| `tts.php` | Gemini API | 6 — speech, voices, tutorial scripts, recording, video |
| `cdp.php` | Chrome DevTools | 6 — list, version, eval, screenshot, navigate, click |
| `midi.php` | PipeWire MIDI | 8 — list, links, devices, connect, disconnect, monitor, virtual, bridge |
| `websocket.php` | WebSocket | 6 — status, start, stop, broadcast, clients, info |
| `config.php` | KDE/KConfig | 11 — list, read, get, set, themes, backup, restore, plasma info |
| `nextcloud.php` | Nextcloud API | 5 |
| `keyboard.php` | Rust FFI | 2 — type_text, send_key (delegates to AppMeshFFI) |
| `ports.php` | Rust FFI | 1 — generic port_execute |

**`AppMeshFFI`** (`server/appmesh-ffi.php`) — lazy singleton bridging PHP to the Rust library:

```php
$ffi = AppMeshFFI::instance();           // init once, reuse
$ffi->typeText('Hello from PHP');        // direct FFI (~0.05ms)
$ffi->sendKey('ctrl+v');                 // key injection
$ffi->portExecute('clipboard', 'get');   // generic port API
```

Library search order: `$APPMESH_LIB_PATH` → `target/release/libappmesh_core.so` → `~/.local/lib/` → `/usr/local/lib/`. Stale handle recovery: return code `-2` triggers destroy + retry on next call.

### 2.3 QML Plugin + Plasmoids (`qml/`)

A C++ Qt6 QML module wrapping `libappmesh_core.so` via `dlopen`:

```cpp
class AppMeshBridge : public QObject {
    QML_ELEMENT
    QML_SINGLETON

    Q_PROPERTY(bool available READ available CONSTANT)
    Q_PROPERTY(QStringList ports READ ports CONSTANT)

    Q_INVOKABLE QVariantMap portExecute(const QString &port, const QString &cmd,
                                        const QVariantMap &args = {});
    Q_INVOKABLE void sendMessage(const QString &channel, const QString &data);

signals:
    void meshMessage(const QString &channel, const QString &data);
};
```

- **`available`** — `false` if library not found (graceful degradation, no crash)
- **`portExecute`** — calls through to Rust port API, returns QVariantMap
- **`meshMessage`** signal — inter-plasmoid pub/sub within the same Plasma session

**Two plasmoids** installed to `/usr/lib/qt6/qml/AppMesh/`:

| Plasmoid | ID | Purpose |
|---|---|---|
| Mesh Send | `com.appmesh.send` | Text field + Broadcast/Notify buttons |
| Mesh Log | `com.appmesh.log` | Scrolling message list with badge count |

Mesh Send calls `AppMeshBridge.sendMessage()` and `portExecute("notify", "send", ...)`. Mesh Log listens to `onMeshMessage` and displays a timestamped log. Both use the shared `AppMeshBridge` singleton.

### 2.4 CLI (`crates/appmesh-cli/`)

```
appmesh type              # read stdin, type into focused window
appmesh key ctrl+v        # send key combo
appmesh port clipboard get                        # execute port command
appmesh port notify send title=Hello body=World   # key=value args
appmesh ports             # list all ports and commands
```

### 2.5 Web UI (`web/`)

HTMX + SSE interface at `localhost:8420`. Real-time D-Bus signal streaming via `sse-signals.php`.

---

## 3. The AMP Port Address

AMP uses DNS-native addressing. Addresses read left-to-right from most-specific to least-specific, mapping directly to DNS hierarchy:

```
[port].[app].[node].amp
```

Examples:

```
clipboard.appmesh.cachyos.amp   → clipboard port, appmesh app, cachyos node
dns.netserva.mko.amp            → DNS service on netserva on mko
compose.laramail.mmc.amp        → compose action on laramail on mmc
inbox.stalwart.mko.amp          → inbox on Stalwart mail on mko
```

- **`.amp`** is the mesh TLD (managed by PowerDNS on each node)
- **Node names** (`cachyos`, `mko`, `mmc`) are subdomains under `.amp`
- **App names** (`appmesh`, `netserva`, `laramail`) are subdomains under the node
- **Port names** (`clipboard`, `dns`, `compose`) are the leaf — the actual service endpoint

**Today's reality:** `appmesh port clipboard get` uses flat names, local only. The address string is a design target — it becomes resolvable DNS once PowerDNS zones are configured and WireGuard provides routing between nodes.

**Local shorthand:** `clipboard` alone implies `.appmesh.cachyos.amp` when running locally. No DNS lookup needed for local ports.

---

## 4. The AMP Message

AMP messages use **markdown frontmatter** as the wire format — `---` delimited headers with an optional freeform body. The encoding is **8-bit UTF-8**. Every AMP message is a valid `.amp.md` file, readable by humans, `cat`-able, `grep`-able, and renderable by any markdown tool.

### 4.1 Grammar

```
message = "---\n" headers "---\n" body
headers = *(key ": " value "\n")
body    = *UTF-8  (may be empty)
```

Every message begins with `---\n` and the header block ends with `---\n`. The trailing `\n` after the closing `---` is mandatory — it makes framing unambiguous on a byte stream (the reader knows the message is complete).

**The empty message** — `---\n---\n` (8 bytes) — is the smallest valid AMP message. No headers, no body, but valid and useful:

- **Heartbeat/keepalive** — "I'm still here" on a persistent connection
- **ACK** — "message received" (context implied by the channel)
- **Stream separator** — boundary marker between logical groups of data messages
- **NOP** — do nothing, but keep the connection alive and prove the parser is working

### 4.2 The Three Shapes

One format, one parser, three message shapes:

| Shape | Description | Use case |
|---|---|---|
| **Full message** | Headers + markdown/text body | Events, rich responses, notifications |
| **Command** | Headers only (including `args:`), no body | Requests, acks, simple responses |
| **Data** | Minimal `json:` header, no body needed | High-throughput streams, structured payloads |
| **Empty** | No headers, no body (`---\n---\n`) | Heartbeat, ACK, NOP, stream separator |

All three are delimited by `---` and parsed identically. The presence of `json:`, `args:`, or a body determines the shape — not a mode flag.

### 4.3 Format

Headers are flat `key: value` lines between `---` delimiters. Two special keys carry inline JSON: `args` (command arguments) and `json` (self-contained data payload). Everything after the closing `---` is the body — freeform content (markdown, JSON, plain text, or empty).

**Shape 1 — Full message** (headers + body):

```
---
amp: 1
type: event
id: 0192b3a4-7c8d-0123-4567-890abcdef012
from: screenshot.appmesh.cachyos.amp
command: taken
---
# Screenshot saved
Path: `/tmp/screenshot-2026-02-28.png`
Size: **2.4 MB**
```

**Shape 2 — Command** (headers only):

```
---
amp: 1
type: request
id: 0192b3a4-5e6f-7890-abcd-ef1234567890
from: script.appmesh.cachyos.amp
to: notify.appmesh.cachyos.amp
command: send
args: {"title": "Hello", "body": "World"}
ttl: 30
---
```

**Shape 3 — Data** (minimal envelope, structured payload):

```
---
json: {"count": 12, "unread": 3}
---
```

The `json:` shape can carry additional headers when routing context is needed:

```
---
amp: 1
reply-to: 0192b3a4-5e6f-7890-abcd-ef1234567890
json: {"count": 12, "unread": 3}
---
```

### 4.4 Header Fields

| Field | Required | Description |
|---|---|---|
| `amp` | yes* | Protocol version (always `1`) |
| `type` | yes* | `request`, `response`, `event`, `stream` |
| `id` | yes* | UUID v7 (time-ordered) |
| `from` | yes* | Source AMP port address |
| `to` | no | Target AMP port address (omitted for events/broadcasts) |
| `command` | yes* | The action to perform |
| `args` | no | Command arguments as inline JSON: `{"key": "value"}` |
| `json` | no | Self-contained data payload as inline JSON |
| `reply-to` | no | Message ID this responds to (responses only) |
| `ttl` | no | Time-to-live in seconds (default 30) |
| `error` | no | Error string (error responses only) |
| `timestamp` | no | Unix timestamp with microseconds |

\* Required for full messages and commands. Data-only messages (`json:` shape) may omit routing headers when the transport already provides context (e.g., an established WebSocket channel or Unix socket session).

### 4.5 More Examples

**Request with no args:**

```
---
amp: 1
type: request
id: 0192b3a4-5e6f-7890-abcd-ef1234567890
from: clipboard.appmesh.cachyos.amp
to: inbox.stalwart.mko.amp
command: get
ttl: 30
---
```

**Response with structured body:**

```
---
amp: 1
type: response
id: 0192b3a4-6a7b-8901-cdef-234567890abc
reply-to: 0192b3a4-5e6f-7890-abcd-ef1234567890
from: inbox.stalwart.mko.amp
to: clipboard.appmesh.cachyos.amp
command: search
---
{"count": 12, "unread": 3, "messages": [{"id": 1, "subject": "Hello"}]}
```

**Stream of data messages** (on an established channel):

```
---
json: {"level": 0.72, "peak": 0.91, "channel": "left"}
---
---
json: {"level": 0.68, "peak": 0.85, "channel": "right"}
---
```

### 4.6 Parsing

Headers are flat `key: value` strings — no YAML parser needed. Two keys (`args` and `json`) carry inline JSON, decoded with `json_decode` / `serde_json`. The parser is identical for all three shapes.

**PHP:**

```php
function amp_parse(string $raw): array {
    [, $fm, $body] = preg_split('/^---$/m', $raw, 3);
    $headers = [];
    foreach (explode("\n", trim($fm)) as $line) {
        [$k, $v] = explode(': ', $line, 2);
        $headers[trim($k)] = trim($v);
    }
    if (isset($headers['json'])) {
        $headers['json'] = json_decode($headers['json'], true);
    }
    if (isset($headers['args'])) {
        $headers['args'] = json_decode($headers['args'], true);
    }
    return ['headers' => $headers, 'body' => trim($body ?? '')];
}
```

**Rust:**

```rust
fn amp_parse(raw: &str) -> (HashMap<&str, &str>, &str) {
    let content = raw.trim_start_matches("---\n");
    let (fm, body) = content.split_once("\n---\n").unwrap_or((content, ""));
    let mut headers = HashMap::new();
    for line in fm.lines() {
        if let Some((k, v)) = line.split_once(": ") {
            headers.insert(k.trim(), v.trim());
        }
    }
    // json and args values are JSON strings — decode downstream
    (headers, body.trim_start_matches('\n'))
}
```

### 4.7 Design Rationale

The `---` frontmatter format is borrowed from the markdown ecosystem (Hugo, Jekyll, Statamic) where it is universally understood. AMP frontmatter *looks like* YAML but is intentionally restricted to flat `key: value` lines — no indentation, no nesting, no type coercion surprises. The `args` and `json` fields use inline JSON because both PHP and Rust already have JSON parsers (`json_decode`, `serde_json`) with zero additional dependencies.

The three shapes serve different needs with zero format negotiation:

- **Full messages** carry rich, human-readable content — event logs are browsable markdown files
- **Commands** are self-contained in headers — compact, no body parsing needed
- **Data messages** are near-pure JSON with `---` framing — ideal for high-throughput streams where routing context is already established by the transport

This makes AMP messages:

- **Human-readable** — `cat message.amp.md` and read it
- **Tool-friendly** — `grep "command: send" *.amp.md` works
- **Debuggable** — message logs are browsable text files
- **Markdown-native** — event bodies render in any markdown viewer, plasmoid, or browser
- **Stream-friendly** — `---` delimiters naturally frame messages on a byte stream

**Status:** Not yet implemented as a wire format. The MCP server uses JSON-RPC. The QML plugin uses signal strings. `AmpMessage` is the target envelope for mesh-level communication.

---

## 5. Transport Layers

Transport is an implementation detail — callers address ports, not transports.

| Channel | Path | Latency | Status |
|---|---|---|---|
| **PHP FFI** | PHP → `libappmesh_core.so` → KWin/D-Bus | ~0.05ms | Working |
| **D-Bus** | PHP subprocess → dbus-daemon → KDE apps | ~1ms | Working (discovery + fallback) |
| **Unix socket** | PHP ↔ Rust daemon (streaming) | ~0.1ms est. | Not yet built |
| **WebSocket/WG** | Browser → Reverb → PHP → Rust | ~2ms est. | Infrastructure ready |

**FFI path** (hot path for local calls):
```
PHP → FFI → Rust C ABI → port execute → tokio runtime → zbus/libei → KDE
```

**QML path** (same library, different caller):
```
QML → dlopen → C function pointers → Rust C ABI → port execute → KDE
```

**Remote path** (target architecture):
```
Browser/Phone → WebSocket → Laravel Reverb → AmpMessage → WireGuard → remote node → port execute
```

The MCP server adds a **JSON-RPC layer** for Claude Code integration. MCP tools map directly to port commands or plugin-specific functionality.

---

## 6. PHP as the Scripting Language

### 6.1 What Works Now

Three calling conventions, same Rust ports underneath:

```php
// 1. Direct FFI (fastest, ~0.05ms)
$ffi = AppMeshFFI::instance();
$ffi->portExecute('clipboard', 'get');

// 2. MCP tool call (via Claude Code)
// Claude calls appmesh_clipboard_get → dbus.php → Klipper D-Bus

// 3. CLI
// $ appmesh port clipboard get
```

### 6.2 The `.amp.php` Vision

AMP scripts are plain PHP files that orchestrate ports:

```php
#!/usr/bin/env amp run
<?php
// screenshot-and-notify.amp.php
// Take a screenshot, copy the path to clipboard, notify the user

$mesh = AppMeshFFI::instance();

$shot = $mesh->portExecute('screenshot', 'take', ['mode' => 'fullscreen']);
$path = $shot['ok']['path'] ?? 'unknown';

$mesh->portExecute('clipboard', 'set', ['text' => $path]);
$mesh->portExecute('notify', 'send', [
    'title' => 'Screenshot saved',
    'body'  => $path,
]);
```

**Future extensions:**
- `amp run script.amp.php` — execute with AppMesh runtime pre-loaded
- `amp shell` — interactive REPL with port tab-completion
- Remote port calls transparent: `$mesh->portExecute('inbox.stalwart.mko.amp', 'search', [...])` routes over WireGuard automatically

---

## 7. QML / Plasmoid Integration

### The Bridge

QML apps and Plasma widgets use the same Rust library through `AppMeshBridge`:

```qml
import AppMesh

Item {
    Component.onCompleted: {
        if (AppMeshBridge.available) {
            var result = AppMeshBridge.portExecute("clipboard", "get", {})
            console.log("Clipboard:", JSON.stringify(result))
        }
    }

    Connections {
        target: AppMeshBridge
        function onMeshMessage(channel, data) {
            console.log("Received on", channel, ":", data)
        }
    }
}
```

Library search order: `$APPMESH_LIB_PATH` → `../target/release/` → `~/.local/lib/` → `/usr/local/lib/`.

### End Goal: Inertia for the Desktop

Plasmoids become thin QML shells backed by Laravel logic:

```qml
import AppMesh

Item {
    AppMeshBridge {
        id: bridge
        // Future: WebSocket connection to Laravel Reverb
        // endpoint: "ws://localhost:8080/reverb"
    }
    Text { text: bridge.data.transcription }
}
```

The brain lives in Laravel. The plasmoid is just a view. This is Inertia's server-driven philosophy applied to native desktop widgets.

---

## 8. The Mesh

### 8.1 Nodes

Three WireGuard-connected nodes running markweb (Laravel):

| Node | URL | Role | Key Services |
|---|---|---|---|
| `cachyos` | `web.goldcoast.org` | Local workstation | FrankenPHP, PostgreSQL 18, Ollama, Reverb |
| `mko` | `web.kanary.org` | Staging/dev | FrankenPHP, Stalwart, PostgreSQL, Ollama, Reverb |
| `mmc` | `web.motd.com` | Production | FrankenPHP, Stalwart, PostgreSQL, Ollama, Reverb |

All nodes run: FrankenPHP (Caddy), PostgreSQL + pgvector, Ollama, Laravel Reverb, PowerDNS, systemd scheduler.

### 8.2 Mesh Heartbeat

markweb sends a heartbeat POST every 30 seconds from each node, config-driven via `MESH_NODE_NAME` and `MESH_NODE_WG_IP` environment variables. This provides fleet discovery — every node knows what other nodes are alive and reachable.

### 8.3 DNS Discovery

PowerDNS on each node manages the `.amp` zone. The evolution path:

1. **Today:** flat port names, local only (`appmesh port clipboard get`)
2. **Next:** SRV records registered when ports come online (`_clipboard._amp.appmesh.cachyos.amp`)
3. **Target:** full DNS resolution — `clipboard.appmesh.mko.amp` resolves to WireGuard IP, request routes automatically

Local calls never touch DNS. Remote calls resolve lazily on first use, cache the route.

---

## 9. Iteration Path

### Done

- Rust `AppMeshPort` trait and 5 port implementations
- 8 C ABI symbols in `libappmesh_core.so`
- PHP FFI bridge with stale-handle recovery
- 10 MCP plugins / 56 tools for Claude Code
- QML plugin with `dlopen`, singleton bridge, `meshMessage` signal
- 2 Plasma 6 plasmoids (send + log) installed system-wide
- CLI with `type`, `key`, `port`, `ports` subcommands
- Web UI with HTMX + SSE signal streaming
- 3-node WireGuard mesh with heartbeat discovery
- KWin EIS keyboard injection via libei

### Next

- **Audio port** — PipeWire capture/playback via Rust, for dictation and TTS streaming
- **`amp` CLI wrapper** — PHP script that loads AppMeshFFI, runs `.amp.php` files, provides REPL
- **meshMessage over WebSocket** — bridge `AppMeshBridge.sendMessage()` to Laravel Reverb for cross-process and cross-node messaging
- **DNS SRV registration** — ports register themselves in PowerDNS on startup
- **D-Bus signals for QML** — real cross-process pub/sub (currently in-process only)

### Vision

Any application on any node, scriptable from PHP. Plasmoids as thin QML shells with brains in Laravel. A `.amp.php` script that says `$mesh->portExecute('compose.laramail.mmc.amp', 'send', [...])` just works — DNS resolves the node, WireGuard routes the packet, the port executes the command, the response comes back as an `AmpMessage`. The desktop becomes programmable the way the Amiga was, but networked.

---

## 10. Technical Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Desktop integration | QML plasmoids via cxx-qt bridge path | Native Plasma 6 widgets, no Electron/Tauri overhead |
| D-Bus library | zbus 5 (pure Rust, async) | No C deps, tokio-native, clean API |
| Input injection | reis (libei bindings) | Only Wayland-native option for KWin EIS |
| reis fork | `markc/reis` (`fix-empty-scm-rights`) | Upstream PR #18 pending |
| QML bridge | C++ dlopen wrapper | No cxx-qt build complexity for v1, runtime library search |
| PHP integration | FFI (not subprocess) | 20x faster than shelling out, same process |
| Message format | Markdown frontmatter + JSON args | Human-readable, `cat`/`grep`-able, `.amp.md` files, zero YAML dep |
| Async runtime | tokio (single-threaded, per-port) | Avoids nested-runtime deadlock with zbus |
| Remote transport | WebSocket over WireGuard | Laravel Reverb already deployed on all nodes |
| DNS | PowerDNS | Already manages zones on all nodes, API for dynamic records |
| No JS SDK | — | PHP is the scripting language. Browser access goes through Laravel. |
