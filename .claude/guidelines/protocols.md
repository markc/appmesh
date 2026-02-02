# Protocol Guidelines

## D-Bus

### Discovery Pattern

1. List services: `qdbus6` (no args)
2. Introspect service: `qdbus6 org.kde.ServiceName`
3. Introspect path: `qdbus6 org.kde.ServiceName /path`
4. Call method: `qdbus6 org.kde.ServiceName /path method [args]`

### Common Gotchas

- **Secrets not exposed**: KWallet, Akonadi credentials are protected
- **Session vs System bus**: Desktop apps use session bus
- **KDE6 transition**: Some apps still use Qt5 names
- **Method signatures**: Use `qdbus6 --literal` for complex types

### Tool vs Documentation

Prefer the generic `appmesh_dbus_call` tool with good documentation over creating dozens of specialized tools. Document app-specific interfaces in `docs/`.

## OSC (Open Sound Control)

### Port Conventions

| Application | Default Port |
|-------------|--------------|
| Ardour | 3819 |
| Carla | 22752 (manual) |
| SuperCollider | 57110 |
| Pure Data | 9000 |
| TouchOSC | 8000 |

### Argument Types

- Integer: `i:123`
- Float: `f:0.5`
- String: `s:text`
- Blob: Not currently supported

### Enabling OSC

Most apps require explicit OSC enablement:
- Ardour: Edit > Preferences > Control Surfaces > OSC
- Carla: Launch with `--osc-gui=PORT`

## Chrome DevTools Protocol (Future)

### Target Applications

Any Electron app can be controlled via CDP:
- VS Code
- Discord
- Slack
- Obsidian
- Figma

### Connection Pattern

1. Launch with `--remote-debugging-port=9222`
2. Get WebSocket URL from `http://localhost:9222/json`
3. Connect and send JSON-RPC commands

### Capabilities

- Execute JavaScript in running app
- Take screenshots
- Inspect/modify DOM
- Monitor network, console

## MIDI (Future)

### PipeWire Integration

```bash
# List MIDI devices
pw-link -o | grep midi

# Create connection
pw-link "Midi-Bridge:input" "App:midi_in"
```

### Use Cases

- Hardware controller → DAW automation
- MIDI → OSC bridging
- Programmatic MIDI generation

## WebSocket (Future)

### Use Cases

- Bidirectional browser communication
- Real-time event streaming
- External tool integration

### Pattern

```
Browser/App ←→ WebSocket Server ←→ MCP Tools ←→ Claude Code
```
