# OSC Audio Control

<skill>
name: osc
description: Control audio applications via Open Sound Control - Ardour, Carla, generic OSC
user-invocable: true
arguments: <target> <command> [args...]
</skill>

## Targets

- `ardour` - Ardour DAW (port 3819)
- `carla` - Carla plugin host (port 22752)
- `send <port> <address>` - Generic OSC to any port

## Ardour Commands

```bash
/osc ardour play              # Start playback
/osc ardour stop              # Stop playback
/osc ardour goto_start        # Go to beginning
/osc ardour rec_enable        # Enable record
/osc ardour strip/gain 1 0.8  # Set track 1 gain to 0.8
```

## Carla Commands

```bash
/osc carla /Carla/set_active 1 1     # Activate plugin 1
/osc carla /Carla/set_parameter 1 0 0.5  # Set param
```

## Generic OSC

```bash
/osc send 9000 /my/address arg1 arg2
```

## Instructions

When the user invokes this skill:

1. **For `ardour`**: Use `appmesh_osc_ardour` - Ardour must have OSC enabled (Edit > Preferences > Control Surfaces > OSC)
2. **For `carla`**: Use `appmesh_osc_carla` - Start Carla with `carla --osc-gui=22752`
3. **For `send`**: Use `appmesh_osc_send` with port, address, and args

## Argument Types

For generic OSC, prefix arguments with type:
- `i:123` - Integer
- `f:0.5` - Float
- `s:text` - String
- No prefix - Auto-detect

## Enabling OSC in Applications

### Ardour
Edit > Preferences > Control Surfaces > Enable OSC

### Carla
Start with: `carla --osc-gui=22752`

### Other Apps
Check application documentation for OSC support and port configuration.
