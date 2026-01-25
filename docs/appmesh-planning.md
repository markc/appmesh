# AppMesh: Desktop Automation via Application Mesh

**Vision:** A web-accessible bridge to the Linux desktop, enabling browsers and AI assistants to orchestrate KDE applications through a unified HTTP/WebSocket interface.

---

## The Problem

Modern Linux desktops have powerful inter-process communication (D-Bus) but it's only accessible locally. Meanwhile:

- Browsers are sandboxed away from the desktop
- AI assistants (Claude, etc.) can't directly control desktop apps
- Web apps can't integrate with local applications
- Automation requires shell scripts or custom code for each app

The Amiga had ARexx in the 1980s - any app could talk to any other app through simple scripts. We've regressed.

---

## The Gap Analysis

### What Exists

| Component | Technology | Limitation |
|-----------|------------|------------|
| **KIO** | HTTP/FTP/WebDAV client | Apps can fetch from web, but web can't reach apps |
| **D-Bus** | Local IPC | Rich app control, but localhost only |
| **Qt HTTP Server** | Embeddable server | Building block, not assembled |
| **Qt WebChannel** | QObject - JS bridge | For embedded WebViews, not external browsers |
| **KDE Connect** | Device sync | Custom TLS protocol, device-centric, not web-native |

### The Missing Piece

```
[Browser/AI]  <->  HTTP/WebSocket  <->  [???]  <->  D-Bus  <->  [KDE Apps]
```

**No KDE component runs an HTTP server that bridges to D-Bus.**

---

## AppMesh Architecture

```
+------------------------------------------------------------------+
|                         BROWSERS / AI                             |
|  (Chrome, Firefox, Claude Code, web apps, remote scripts)         |
+------------------------------------------------------------------+
                              |
                    HTTP / WebSocket / SSE
                              |
                              v
+------------------------------------------------------------------+
|                      AppMesh SERVICE                              |
|  +-------------+  +-------------+  +-------------------------+    |
|  | HTTP Server |  |  WebSocket  |  |  Security/Auth Layer    |    |
|  |  (Qt HTTP)  |  |   Server    |  |  (localhost/token/TLS)  |    |
|  +-------------+  +-------------+  +-------------------------+    |
|                              |                                    |
|                    +---------+---------+                          |
|                    |   D-Bus Bridge    |                          |
|                    |  (Qt D-Bus API)   |                          |
|                    +-------------------+                          |
+------------------------------------------------------------------+
                              |
                           D-Bus
                              |
        +---------------------+---------------------+
        v                     v                     v
+--------------+    +--------------+    +--------------+
|   Dolphin    |    |    Kate      |    |   Konsole    |
|   Spectacle  |    |    KMail     |    |   KRunner    |
|   Konqueror  |    |   Plasmashell|    |    KWin      |
+--------------+    +--------------+    +--------------+
```

---

## D-Bus Interface Discovery (January 2025)

Documented interfaces for automation:

| App | D-Bus Richness | Key Capabilities |
|-----|----------------|------------------|
| **KWin** | Excellent | Virtual desktops, effects, Night Light, input devices, scripting |
| **Konqueror** | Excellent | Full browser control, navigation, tabs, URL reading, 80+ actions |
| **Plasmashell** | Good | OSD, clipboard (Klipper), wallpaper, widgets, activities |
| **Konsole** | Good | Run commands, read output, session management, splits |
| **Kate** | Good | Document control, cursor, selection, syntax, sessions |
| **Dolphin** | Moderate | File selection, navigation, view modes |
| **Spectacle** | Moderate | Screenshots (full, window, region, interactive) |
| **KRunner** | Moderate | Search/launch, activities, on-demand service |
| **KDE Connect** | Moderate | Device communication, notifications, clipboard sync |
| **Falkon** | Minimal | Open URLs only |
| **Firefox** | Minimal | Open URLs only |

### System Services

| Service | Purpose |
|---------|---------|
| `org.kde.kded6` | Module host (keyboard, bluetooth, network) |
| `org.kde.Solid.PowerManagement` | Power profiles, brightness, battery |
| `org.kde.kglobalaccel` | Global keyboard shortcuts |
| `org.freedesktop.Notifications` | Desktop notifications |
| `org.freedesktop.portal.*` | Sandboxed app permissions |

---

## API Design (Draft)

### REST Endpoints

```
GET  /dbus/services                    # List available services
GET  /dbus/service/{name}/objects      # List object paths
GET  /dbus/service/{name}/object/{path}/methods
GET  /dbus/service/{name}/object/{path}/properties

POST /dbus/call                        # Invoke method
     { "service": "org.kde.Dolphin",
       "path": "/MainWindow_1",
       "method": "openUrl",
       "args": ["file:///home"] }

GET  /dbus/property                    # Read property
PUT  /dbus/property                    # Write property
```

### WebSocket Events

```javascript
// Subscribe to D-Bus signals
ws.send({ subscribe: "org.kde.KWin", signal: "showingDesktopChanged" })

// Receive real-time updates
ws.onmessage = (e) => {
  // { signal: "showingDesktopChanged", args: [true] }
}
```

### High-Level Actions

```
POST /action/screenshot    # Wraps Spectacle
POST /action/notify        # Wraps Notifications
POST /action/clipboard     # Wraps Klipper
POST /action/launch        # Wraps KRunner
GET  /action/windows       # Wraps KWin window list
```

---

## Security Model

### Threat Model
- AppMesh runs on user's machine, has full D-Bus access
- Must prevent unauthorized remote access
- Must prevent malicious web pages from invoking

### Mitigations

1. **Localhost only by default** - no network exposure
2. **Token authentication** - bearer token for API access
3. **Origin checking** - validate request origins
4. **Capability system** - whitelist allowed D-Bus calls
5. **Optional TLS** - for non-localhost deployments
6. **User confirmation** - for sensitive operations (like KDE Connect)

---

## Implementation: FrankenPHP + HTMX + SSE

**Decision:** HTMX + Server-Sent Events for zero-JS-framework real-time UI.

### Architecture
```
+-------------+      HTTP/SSE      +-------------+     exec      +--------+
|   Browser   | <----------------> | FrankenPHP  | ------------> | qdbus6 |
|   (HTMX)    |                    | appmesh-sse |               +--------+
+-------------+                    +-------------+                    |
       |                                  |                        D-Bus
       | SSE stream                       |                           |
       +----------------------------------+          +----------------+--------+
                                                     v        v       v        v
                                                  Dolphin  Konsole  KWin   Spectacle
```

### Why This Stack

| Component | Choice | Rationale |
|-----------|--------|-----------|
| **HTTP Server** | FrankenPHP | Modern, fast, handles SSE + HTTP concurrently |
| **Frontend** | HTMX + SSE ext | No JS framework, ~18KB total, real-time push |
| **Real-time** | SSE | Simpler than WebSockets, auto-reconnect, firewall-friendly |
| **D-Bus Bridge** | qdbus6 | CLI, ~12ms latency, zero deps |
| **Language** | PHP 8.4+ | No frameworks, single-file deployment |

### Files

```
~/.appmesh/
├── web/
│   ├── index.php        - Entry point: routing, API handlers, HTMX UI
│   └── sse-signals.php  - SSE endpoint for D-Bus signal streaming
└── server/
    ├── appmesh-mcp.php  - MCP server for Claude Code
    ├── appmesh-core.php - Shared core (Tool class, registry)
    └── plugins/
        ├── dbus.php     - D-Bus tools
        └── osc.php      - OSC tools
```

### Endpoints

```
GET  /                      - HTMX UI
GET  /api/services          - List D-Bus services (all named services)
GET  /api/clipboard         - Get clipboard content
POST /api/clipboard/set     - Set clipboard content
POST /api/notify            - Send notification
GET  /api/introspect/{svc}  - Browse service objects/methods
POST /api/call              - Call D-Bus method
GET  /sse/signals           - SSE stream (real-time D-Bus signals)
```

### Running

```bash
cd ~/.appmesh/web
php -S localhost:8420

# Or with FrankenPHP:
frankenphp php-server --listen :8420

# Open http://localhost:8420
```

---

## Milestones

### Phase 1: Proof of Concept [Complete]
- [x] FrankenPHP + SSE architecture
- [x] Basic D-Bus bridge via qdbus6
- [x] Service/object/method listing
- [x] Method invocation endpoint

### Phase 2: High-Level Actions [Complete]
- [x] Screenshot capture (Spectacle)
- [x] Notifications (freedesktop)
- [x] Clipboard get/set (Klipper)
- [x] Window list (KWin)

### Phase 3: Real-time Events [Partial]
- [x] SSE stream infrastructure
- [x] Clipboard change monitoring (simulated)
- [ ] Active window tracking
- [ ] D-Bus signal subscriptions (needs PECL dbus)
- [ ] Custom event filters

### Phase 4: Integration [In Progress]
- [x] Claude Code MCP server
- [ ] Browser extension
- [ ] Workflow scripting
- [ ] Documentation & examples

### Phase 5: Security & Polish
- [ ] Token authentication
- [ ] Capability restrictions
- [ ] Rate limiting
- [ ] Localhost-only by default

---

## Success Criteria

1. **Browser can control KDE apps** - open files in Kate, take screenshots, manage windows
2. **Claude Code can orchestrate desktop** - "take a screenshot, open it in GIMP, export as PNG"
3. **Web apps can integrate** - custom dashboards showing desktop state
4. **Scripts work remotely** - SSH + AppMesh = remote desktop automation

---

## References

- [Qt HTTP Server](https://doc.qt.io/qt-6/qthttpserver-index.html)
- [Qt WebChannel](https://doc.qt.io/qt-6/qtwebchannel-index.html)
- [D-Bus Specification](https://dbus.freedesktop.org/doc/dbus-specification.html)
- [KDE D-Bus Documentation](https://develop.kde.org/docs/features/d-bus/)
- [KIO Handbook](https://docs.kde.org/stable5/en/kio/kioslave5/)

---

*Document created: January 2025*
*Updated: January 2026 (AppMesh rebranding)*
*Based on D-Bus exploration of KDE Plasma 6 on CachyOS*
