# Pro Audio on Linux with PipeWire/JACK

Overview of professional audio effects processing and signal chain systems on Linux.

## Architecture

```
+------------------------------------------------------------------+
|                         PipeWire                                  |
|  (Low-latency audio/video router - replaces PulseAudio + JACK)   |
+------------------------------------------------------------------+
|  pipewire-jack: JACK compatibility layer                          |
|  pipewire-pulse: PulseAudio compatibility                         |
+------------------------------------------------------------------+
         |                    |                    |
    +----v----+          +----v----+         +----v----+
    |  Carla  |          |Guitarix |         |EasyEffects|
    |(Plugin  |          |(Amp Sim)|         |(System FX)|
    |  Host)  |          |         |         |           |
    +---------+          +---------+         +-----------+
```

## Key Components

### Audio Servers & Routing

| Package | Description | Status |
|---------|-------------|--------|
| `pipewire` | Modern audio/video router | Installed |
| `pipewire-jack` | JACK compatibility layer | Installed |
| `pipewire-audio` | Audio support | Installed |
| `qpwgraph` | Qt patchbay for PipeWire | Available |
| `helvum` | GTK patchbay for PipeWire | Available |

### Plugin Hosts

| Package | Description | D-Bus/OSC |
|---------|-------------|-----------|
| `carla` | Full-featured plugin host (LV2/VST/VST3/CLAP) | OSC remote control |
| `jalv` | Simple LV2 host for JACK | CLI only |
| `mod-host` (AUR) | Headless LV2 host, socket control | Socket API |

### System-Wide Effects

| Package | Description | Notes |
|---------|-------------|-------|
| `easyeffects` | Effects for PipeWire apps | Qt/Kirigami, presets |

### Guitar/Instrument Processing

| Package | Description | Notes |
|---------|-------------|-------|
| `guitarix` | Virtual amp + effects | JACK native, LV2 plugins |
| `gxplugins.lv2` | Extra Guitarix LV2 plugins | |
| `aida-x-*` | AI amp modeler (NAM support) | LV2/VST/CLAP |
| `neural-amp-modeler-lv2` (AUR) | NAM LV2 plugin | |

### Plugin Suites

| Suite | Plugins | Formats |
|-------|---------|---------|
| `lsp-plugins` | 200+ pro effects | LV2/VST/VST3/CLAP/LADSPA/Standalone |
| `calf` | Synths, EQ, compressors | LV2 |
| `zam-plugins` | Mastering, dynamics | LV2/VST/VST3/CLAP/LADSPA |
| `dragonfly-reverb` | Hall, room, plate reverb | LV2/VST/VST3/CLAP |
| `x42-plugins` | Meters, EQ, delays | LV2/Standalone |

---

## AppMesh Integration - OSC Support

AppMesh MCP server includes native OSC support via the `plugins/osc.php` plugin.

### Available Tools

| Tool | Target | Description |
|------|--------|-------------|
| `appmesh_osc_send` | Any OSC app | Generic OSC message sender |
| `appmesh_osc_ardour` | Ardour | Pre-configured for Ardour (port 3819) |
| `appmesh_osc_carla` | Carla | Pre-configured for Carla (port 22752) |

### Architecture

```
+-----------------+     +-----------------+     +-----------------+
|  Claude Code    |---->|  appmesh-mcp    |---->|  plugins/       |
|  (MCP client)   |     |  (MCP server)   |     |  osc.php        |
+-----------------+     +-----------------+     +--------+--------+
                                                         | UDP
                              +--------------------------+---------------------------+
                              |                          |                           |
                              v                          v                           v
                      +---------------+           +---------------+           +---------------+
                      |    Ardour     |           |    Carla      |           |  Any OSC App  |
                      |  (port 3819)  |           | (port 22752)  |           |               |
                      +---------------+           +---------------+           +---------------+
```

### Ardour Control Examples

```bash
# Via Claude/AppMesh MCP:

# Transport
appmesh_osc_ardour(command: "/transport_play")
appmesh_osc_ardour(command: "/transport_stop")
appmesh_osc_ardour(command: "/goto_start")
appmesh_osc_ardour(command: "/rec_enable_toggle")

# Mixing
appmesh_osc_ardour(command: "/strip/gain", args: ["1", "-6.0"])  # Track 1 to -6dB
appmesh_osc_ardour(command: "/strip/mute", args: ["1", "1"])     # Mute track 1
appmesh_osc_ardour(command: "/strip/solo", args: ["2", "1"])     # Solo track 2
appmesh_osc_ardour(command: "/strip/fader", args: ["1", "0.75"]) # Fader position

# Session
appmesh_osc_ardour(command: "/save_state")
appmesh_osc_ardour(command: "/undo")
```

### Generic OSC Example

```bash
# Send to any OSC application
appmesh_osc_send(host: "localhost", port: 9000, address: "/my/command", args: ["i:42", "f:3.14", "hello"])
```

### Argument Format

Arguments can be typed explicitly or auto-detected:

| Format | Type | Example |
|--------|------|---------|
| `i:N` | Integer | `i:42`, `i:-6` |
| `f:N` | Float | `f:3.14`, `f:0.75` |
| `s:text` | String | `s:hello` |
| Plain number | Auto | `42` -> int, `3.14` -> float |
| Plain text | String | `hello` |

### Setup Requirements

**Ardour**: Enable OSC in Edit -> Preferences -> Control Surfaces -> OSC

**Carla**: Start with `carla --osc-gui=22752`

---

## Installation Summary

### Minimal Pro Audio Setup

```bash
sudo pacman -S pipewire-jack qpwgraph carla lsp-plugins-lv2 easyeffects
```

### Full Guitar Rig

```bash
sudo pacman -S guitarix gxplugins.lv2 dragonfly-reverb-lv2 calf
paru -S neural-amp-modeler-lv2  # AI amp models
```

### Complete Suite

```bash
sudo pacman -S carla jalv qpwgraph helvum \
  lsp-plugins-lv2 calf zam-plugins-lv2 dragonfly-reverb-lv2 x42-plugins-lv2 \
  guitarix easyeffects ardour
paru -S raysession mod-host-git
```

---

## Sources

- [PipeWire Guide](https://github.com/mikeroyal/PipeWire-Guide)
- [KXStudio Carla](https://kx.studio/Applications:Carla)
- [Guitarix](https://guitarix.org/)
- [LSP Plugins](https://lsp-plug.in/)
- [Linux Audio Wiki](https://wiki.linuxaudio.org/)
- [Arch Wiki - PipeWire](https://wiki.archlinux.org/title/PipeWire)
