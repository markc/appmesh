# PipeWire MIDI Control

<skill>
name: midi
description: Route and monitor MIDI via PipeWire - connect controllers, apps, and virtual ports
user-invocable: true
arguments: <action> [options]
</skill>

## Actions

- `list` - List all MIDI ports (inputs and outputs)
- `links` - Show current MIDI connections
- `devices` - List raw ALSA MIDI hardware
- `connect <out> <in>` - Connect output port to input port
- `disconnect <out> <in>` - Remove a connection
- `monitor <port> [timeout]` - Watch MIDI events (default 5s)
- `virtual [action]` - Manage virtual MIDI ports

## Examples

```bash
/midi list                           # Show all MIDI ports
/midi links                          # Show connections
/midi devices                        # Show hardware MIDI
/midi connect "Keyboard:out" "Ardour:in"
/midi disconnect "Keyboard:out" "Ardour:in"
/midi monitor "20:0" 10              # Monitor for 10 seconds
/midi virtual info                   # How to create virtual ports
```

## Instructions

When the user invokes this skill:

1. **For `list`**: Use `appmesh_midi_list`
2. **For `links`**: Use `appmesh_midi_links`
3. **For `devices`**: Use `appmesh_midi_devices`
4. **For `connect`**: Use `appmesh_midi_connect` with output and input names
5. **For `disconnect`**: Use `appmesh_midi_disconnect`
6. **For `monitor`**: Use `appmesh_midi_monitor` with port and optional timeout
7. **For `virtual`**: Use `appmesh_midi_virtual` with action (info/list/create)

## Common Workflows

### Route MIDI Keyboard to DAW

```bash
/midi list                           # Find port names
/midi connect "USB-Keyboard:out" "Ardour:midi_in"
```

### Monitor Controller Input

```bash
/midi devices                        # Find ALSA port (e.g., 20:0)
/midi monitor "20:0" 30              # Watch for 30 seconds
```

### Set Up Virtual MIDI

```bash
/midi virtual create                 # Start a2jmidid bridge
/midi list                           # See new virtual ports
```

## Requirements

- PipeWire with MIDI support (`pipewire-jack`)
- `pw-link` command (from pipewire package)
- Optional: `a2jmidid` for virtual ports
- Optional: `alsa-utils` for `aseqdump` monitoring

## Port Name Format

PipeWire MIDI ports use format: `ClientName:PortName`

Examples:
- `Midi-Bridge:MIDI 1 (capture)`
- `Ardour:physical_midi_input-0`
- `Carla:events-in`

Use exact names from `/midi list` output.
