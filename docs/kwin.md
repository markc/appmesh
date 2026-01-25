# KWin D-Bus Integration

KWin is the KDE window manager/compositor. It exposes extensive D-Bus interfaces for window management, virtual desktops, effects, Night Light, input devices, and scripting.

## Services

| Service | Purpose |
|---------|---------|
| `org.kde.KWin` | Main window manager |
| `org.kde.KWin.Effect.WindowView1` | Present Windows effect |
| `org.kde.KWin.HighlightWindow` | Window highlighting |
| `org.kde.KWin.NightLight` | Blue light filter |
| `org.kde.KWin.ScreenShot2` | Screenshot capture |

---

## Main KWin Interface

```bash
# Get current desktop number (1-indexed)
qdbus6 org.kde.KWin /KWin org.kde.KWin.currentDesktop

# Set current desktop
qdbus6 org.kde.KWin /KWin org.kde.KWin.setCurrentDesktop 2

# Navigate desktops
qdbus6 org.kde.KWin /KWin org.kde.KWin.nextDesktop
qdbus6 org.kde.KWin /KWin org.kde.KWin.previousDesktop

# Show/hide desktop (minimize all windows)
qdbus6 org.kde.KWin /KWin org.kde.KWin.showDesktop true

# Get active output/monitor name
qdbus6 org.kde.KWin /KWin org.kde.KWin.activeOutputName
# Returns: "HDMI-A-1", "DP-1", etc.

# Kill window (interactive - click to select)
qdbus6 org.kde.KWin /KWin org.kde.KWin.killWindow

# Query window info (interactive - click to select)
qdbus6 org.kde.KWin /KWin org.kde.KWin.queryWindowInfo

# Show KWin debug console
qdbus6 org.kde.KWin /KWin org.kde.KWin.showDebugConsole

# Reload configuration
qdbus6 org.kde.KWin /KWin org.kde.KWin.reconfigure

# Get support info (useful for debugging)
qdbus6 org.kde.KWin /KWin org.kde.KWin.supportInformation
```

---

## Virtual Desktop Manager

Full control over virtual desktops.

```bash
# Get desktop count
qdbus6 org.kde.KWin /VirtualDesktopManager org.freedesktop.DBus.Properties.Get \
  org.kde.KWin.VirtualDesktopManager count

# Get current desktop UUID
qdbus6 org.kde.KWin /VirtualDesktopManager org.freedesktop.DBus.Properties.Get \
  org.kde.KWin.VirtualDesktopManager current

# Get all desktops (with UUIDs and names)
qdbus6 --literal org.kde.KWin /VirtualDesktopManager \
  org.freedesktop.DBus.Properties.Get org.kde.KWin.VirtualDesktopManager desktops

# Get/set grid rows
qdbus6 org.kde.KWin /VirtualDesktopManager org.freedesktop.DBus.Properties.Get \
  org.kde.KWin.VirtualDesktopManager rows
qdbus6 org.kde.KWin /VirtualDesktopManager org.freedesktop.DBus.Properties.Set \
  org.kde.KWin.VirtualDesktopManager rows 2

# Navigation wrapping
qdbus6 org.kde.KWin /VirtualDesktopManager org.freedesktop.DBus.Properties.Get \
  org.kde.KWin.VirtualDesktopManager navigationWrappingAround

# Create new desktop (position, name)
qdbus6 org.kde.KWin /VirtualDesktopManager \
  org.kde.KWin.VirtualDesktopManager.createDesktop 6 "New Desktop"

# Remove desktop by UUID
qdbus6 org.kde.KWin /VirtualDesktopManager \
  org.kde.KWin.VirtualDesktopManager.removeDesktop "uuid-here"

# Rename desktop
qdbus6 org.kde.KWin /VirtualDesktopManager \
  org.kde.KWin.VirtualDesktopManager.setDesktopName "uuid-here" "Work"

# Switch to desktop by UUID
qdbus6 org.kde.KWin /VirtualDesktopManager org.freedesktop.DBus.Properties.Set \
  org.kde.KWin.VirtualDesktopManager current "uuid-here"
```

---

## Compositor

Check compositor status (cannot toggle on Wayland - always compositing).

```bash
# Get all compositor properties
qdbus6 org.kde.KWin /Compositor org.freedesktop.DBus.Properties.GetAll org.kde.kwin.Compositing

# Key properties:
# active: true
# compositingType: gl2
# compositingPossible: true
# openGLIsBroken: false
# platformRequiresCompositing: true (Wayland)
# supportedOpenGLPlatformInterfaces: egl
```

---

## Effects

Load, unload, and query desktop effects.

```bash
# List all available effects
qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.listOfEffects

# List loaded effects
qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.loadedEffects

# List currently active effects
qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.activeEffects

# Check if effect is loaded
qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.isEffectLoaded "wobblywindows"

# Check if effect is supported
qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.isEffectSupported "blur"

# Load/unload effect
qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.loadEffect "wobblywindows"
qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.unloadEffect "wobblywindows"

# Toggle effect
qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.toggleEffect "wobblywindows"

# Reconfigure effect (reload settings)
qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.reconfigureEffect "blur"

# Get effect support info
qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.supportInformation "blur"
```

### Common Effects
| Effect | Description |
|--------|-------------|
| blur | Window blur/transparency |
| wobblywindows | Wobbly windows when dragging |
| windowview | Present Windows (overview) |
| overview | Desktop overview |
| zoom | Screen magnification |
| shakecursor | Find cursor by shaking |
| dimscreen | Dim inactive screens |
| slidingpopups | Animated popups |

---

## Window View (Present Windows)

Trigger the Present Windows effect.

```bash
# Activate with all windows
qdbus6 org.kde.KWin.Effect.WindowView1 /org/kde/KWin/Effect/WindowView1 \
  org.kde.KWin.Effect.WindowView1.activate '[]'

# Activate with specific windows (by handle)
qdbus6 org.kde.KWin.Effect.WindowView1 /org/kde/KWin/Effect/WindowView1 \
  org.kde.KWin.Effect.WindowView1.activate '["window-handle-1", "window-handle-2"]'
```

---

## Night Light (Blue Light Filter)

Control the blue light filter for eye comfort.

```bash
# Get all Night Light properties
qdbus6 org.kde.KWin /org/kde/KWin/NightLight \
  org.freedesktop.DBus.Properties.GetAll org.kde.KWin.NightLight

# Key properties:
# available: true
# enabled: true
# running: true
# currentTemperature: 4500 (Kelvin)
# targetTemperature: 4500
# daylight: false (currently night mode)
# mode: 0 (automatic)

# Preview a temperature (temporary)
qdbus6 org.kde.KWin /org/kde/KWin/NightLight org.kde.KWin.NightLight.preview 3500

# Stop preview
qdbus6 org.kde.KWin /org/kde/KWin/NightLight org.kde.KWin.NightLight.stopPreview

# Temporarily inhibit Night Light (returns cookie)
cookie=$(qdbus6 org.kde.KWin /org/kde/KWin/NightLight org.kde.KWin.NightLight.inhibit)

# Uninhibit
qdbus6 org.kde.KWin /org/kde/KWin/NightLight org.kde.KWin.NightLight.uninhibit $cookie
```

### Temperature Guide
| Kelvin | Description |
|--------|-------------|
| 6500 | Daylight (no filter) |
| 5000 | Neutral |
| 4500 | Slightly warm |
| 4000 | Warm |
| 3500 | Very warm |
| 2700 | Candlelight |

---

## Input Devices

Configure mice, keyboards, touchpads, and other input devices.

```bash
# List input devices
for dev in /org/kde/KWin/InputDevice/event{0..20}; do
  name=$(qdbus6 org.kde.KWin $dev org.freedesktop.DBus.Properties.Get \
    org.kde.KWin.InputDevice name 2>/dev/null)
  [ -n "$name" ] && echo "$dev: $name"
done

# Get all properties for a device
qdbus6 org.kde.KWin /org/kde/KWin/InputDevice/event3 \
  org.freedesktop.DBus.Properties.GetAll org.kde.KWin.InputDevice

# Enable/disable device
qdbus6 org.kde.KWin /org/kde/KWin/InputDevice/event3 \
  org.freedesktop.DBus.Properties.Set org.kde.KWin.InputDevice enabled true

# Set pointer acceleration (-1.0 to 1.0)
qdbus6 org.kde.KWin /org/kde/KWin/InputDevice/event3 \
  org.freedesktop.DBus.Properties.Set org.kde.KWin.InputDevice pointerAcceleration 0.0

# Enable/disable natural scroll (for touchpad/mouse)
qdbus6 org.kde.KWin /org/kde/KWin/InputDevice/event3 \
  org.freedesktop.DBus.Properties.Set org.kde.KWin.InputDevice naturalScroll true

# Left-handed mode
qdbus6 org.kde.KWin /org/kde/KWin/InputDevice/event3 \
  org.freedesktop.DBus.Properties.Set org.kde.KWin.InputDevice leftHanded true
```

### Device Properties (Selection)
| Property | Type | Description |
|----------|------|-------------|
| enabled | bool | Device active |
| name | string | Device name |
| pointer | bool | Is pointing device |
| keyboard | bool | Is keyboard |
| touchpad | bool | Is touchpad |
| pointerAcceleration | double | -1.0 to 1.0 |
| naturalScroll | bool | Inverted scroll |
| leftHanded | bool | Swap buttons |
| tapToClick | bool | Touchpad tap |
| scrollFactor | double | Scroll speed |

---

## Scripting

Load and run KWin scripts dynamically.

```bash
# Load a script (returns script ID)
qdbus6 org.kde.KWin /Scripting org.kde.kwin.Scripting.loadScript "/path/to/script.js"

# Load with plugin name
qdbus6 org.kde.KWin /Scripting org.kde.kwin.Scripting.loadScript \
  "/path/to/script.js" "my-script"

# Load declarative (QML) script
qdbus6 org.kde.KWin /Scripting org.kde.kwin.Scripting.loadDeclarativeScript \
  "/path/to/script.qml" "my-qml-script"

# Check if script is loaded
qdbus6 org.kde.KWin /Scripting org.kde.kwin.Scripting.isScriptLoaded "my-script"

# Unload script
qdbus6 org.kde.KWin /Scripting org.kde.kwin.Scripting.unloadScript "my-script"

# Start all loaded scripts
qdbus6 org.kde.KWin /Scripting org.kde.kwin.Scripting.start
```

---

## Screenshot Interface

KWin provides a screenshot API (used by Spectacle internally).

```bash
# Note: These methods require a file descriptor pipe, making them
# complex to call from shell. Use Spectacle D-Bus instead for easy screenshots.

# Available methods:
# CaptureActiveScreen, CaptureActiveWindow, CaptureArea,
# CaptureInteractive, CaptureScreen, CaptureWindow, CaptureWorkspace
```

---

## Practical Examples

### Toggle Wobbly Windows
```bash
#!/bin/bash
if qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.isEffectLoaded "wobblywindows"; then
  qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.unloadEffect "wobblywindows"
  echo "Wobbly windows disabled"
else
  qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.loadEffect "wobblywindows"
  echo "Wobbly windows enabled"
fi
```

### Night Mode Toggle
```bash
#!/bin/bash
# Toggle between warm (3500K) and normal (6500K)
current=$(qdbus6 org.kde.KWin /org/kde/KWin/NightLight \
  org.freedesktop.DBus.Properties.Get org.kde.KWin.NightLight currentTemperature)

if [ "$current" -lt 5000 ]; then
  qdbus6 org.kde.KWin /org/kde/KWin/NightLight org.kde.KWin.NightLight.preview 6500
else
  qdbus6 org.kde.KWin /org/kde/KWin/NightLight org.kde.KWin.NightLight.preview 3500
fi
```

### Create Workspace Layout
```bash
#!/bin/bash
# Ensure we have 4 desktops with names
desktops=("Main" "Code" "Communication" "Media")

for i in "${!desktops[@]}"; do
  pos=$((i + 1))
  # Would need to track UUIDs for renaming
done

# Set 2x2 grid
qdbus6 org.kde.KWin /VirtualDesktopManager org.freedesktop.DBus.Properties.Set \
  org.kde.KWin.VirtualDesktopManager rows 2
```

---

## Current System State

```
Desktops: 6 (2 rows × 3 columns)
Current: Desktop 1
Compositor: OpenGL 2 (EGL)
Output: HDMI-A-1
Night Light: Enabled, 4500K
```

---

## Quirks & Notes

1. **Wayland limitation** - Cannot toggle compositing on Wayland (always on)
2. **Desktop UUIDs** - Desktops use UUIDs internally, not just numbers
3. **queryWindowInfo** - Interactive, requires clicking a window
4. **Night Light preview** - Temporary; call stopPreview to reset
5. **Input device paths** - `/event0`, `/event1` etc. vary by system
6. **Script loading** - Scripts must be started with `start()` after loading
7. **Effects persistence** - Loading/unloading is temporary; configure in System Settings for permanence
