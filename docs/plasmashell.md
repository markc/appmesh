# Plasma Shell D-Bus Integration

D-Bus service: `org.kde.plasmashell`

## Key Object Paths

| Path | Purpose |
|------|---------|
| `/PlasmaShell` | Main shell control, scripting engine |
| `/klipper` | Clipboard manager |
| `/org/freedesktop/Notifications` | Desktop notifications |
| `/org/kde/osdService` | On-screen display (volume/brightness popups) |
| `/MainApplication` | Qt application control |

---

## Clipboard (Klipper)

Full clipboard access via `/klipper` with interface `org.kde.klipper.klipper`.

### Read clipboard
```bash
qdbus6 org.kde.plasmashell /klipper org.kde.klipper.klipper.getClipboardContents
```

### Write to clipboard
```bash
qdbus6 org.kde.plasmashell /klipper org.kde.klipper.klipper.setClipboardContents "text here"
```

### Get clipboard history
```bash
qdbus6 org.kde.plasmashell /klipper org.kde.klipper.klipper.getClipboardHistoryMenu
```

### Get specific history item (0-indexed)
```bash
qdbus6 org.kde.plasmashell /klipper org.kde.klipper.klipper.getClipboardHistoryItem 0
```

### Clear clipboard
```bash
qdbus6 org.kde.plasmashell /klipper org.kde.klipper.klipper.clearClipboardContents
qdbus6 org.kde.plasmashell /klipper org.kde.klipper.klipper.clearClipboardHistory
```

---

## Notifications

Standard freedesktop notification interface at `/org/freedesktop/Notifications`.

### Send notification (using gdbus for proper type handling)
```bash
gdbus call --session \
  --dest org.kde.plasmashell \
  --object-path /org/freedesktop/Notifications \
  --method org.freedesktop.Notifications.Notify \
  "App Name" 0 "dialog-information" "Title" "Body text" '[]' '{}' 5000
```

Returns notification ID (uint32).

### Close notification
```bash
gdbus call --session \
  --dest org.kde.plasmashell \
  --object-path /org/freedesktop/Notifications \
  --method org.freedesktop.Notifications.CloseNotification \
  42  # notification ID
```

### Alternative: notify-send
```bash
notify-send "Title" "Body text"
```

---

## OSD (On-Screen Display)

Shows volume/brightness-style popups via `/org/kde/osdService`.

### Show text OSD
```bash
qdbus6 org.kde.plasmashell /org/kde/osdService org.kde.osdService.showText \
  "dialog-information" "Your message here"
```

### Simulate volume change (shows volume OSD)
```bash
qdbus6 org.kde.plasmashell /org/kde/osdService org.kde.osdService.volumeChanged 75
```

### Simulate brightness change
```bash
qdbus6 org.kde.plasmashell /org/kde/osdService org.kde.osdService.brightnessChanged 50
```

---

## PlasmaShell Scripting Engine

The `/PlasmaShell` object exposes a JavaScript scripting engine via `evaluateScript`. Output goes through `print()`.

### Basic usage
```bash
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.evaluateScript \
  'print("Hello from Plasma scripting!")'
```

### Scripting API Reference

#### Global Properties
| Property | Description |
|----------|-------------|
| `screenCount` | Number of screens |
| `activityIds` | Array of activity UUIDs |
| `panelIds` | Array of panel IDs |
| `knownWidgetTypes` | Array of available widget plugin IDs |
| `knownPanelTypes` | Array of available panel types |
| `scriptingVersion` | API version (currently 20) |
| `locked` | Whether shell is locked |
| `hasBattery` | Whether system has battery |

#### Global Functions
| Function | Description |
|----------|-------------|
| `panels()` | Get array of Panel objects |
| `panelById(id)` | Get specific panel |
| `desktops()` | Get array of Desktop containments |
| `desktopById(id)` | Get specific desktop |
| `desktopForScreen(n)` | Get desktop for screen number |
| `currentActivity()` | Get current activity UUID |
| `activityName(uuid)` | Get activity name |
| `setCurrentActivity(uuid)` | Switch to activity |
| `createActivity(name)` | Create new activity |
| `screenGeometry(n)` | Get screen geometry as QRectF |
| `knownWallpaperPlugins()` | Get available wallpaper plugins |
| `lockCorona()` | Lock the shell |
| `sleep(ms)` | Sleep for milliseconds |

### Panel Object

```javascript
var p = panels()[0];

// Properties
p.location      // "top", "bottom", "left", "right"
p.height        // Height in pixels
p.hiding        // "none", "autohide", "dodgewindows", "windowsgobelow"
p.floating      // Boolean
p.opacity       // "adaptive", "opaque", "translucent"
p.alignment     // "left", "center", "right"
p.widgetIds     // Array of widget IDs

// Methods
p.widgetById(id)     // Get widget object
p.addWidget("org.kde.plasma.kickoff")  // Add widget
p.remove()           // Remove panel
```

### Widget Object

```javascript
var w = panels()[0].widgetById(3);

// Properties
w.type          // Plugin ID (e.g., "org.kde.plasma.kickoff")
w.id            // Widget ID number

// Config methods
w.currentConfigGroup = ["/"]  // Set config path
w.readConfig("key", "default")
w.writeConfig("key", "value")
```

### Practical Examples

#### List all widgets on panel
```bash
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.evaluateScript '
var p = panels()[0];
var ids = p.widgetIds;
for (var i = 0; i < ids.length; i++) {
    var w = p.widgetById(ids[i]);
    print(ids[i] + ": " + w.type);
}
'
```

#### Get screen info
```bash
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.evaluateScript '
print("Screens: " + screenCount);
for (var i = 0; i < screenCount; i++) {
    var g = screenGeometry(i);
    print("  Screen " + i + ": " + g.width + "x" + g.height);
}
'
```

#### Get current activity
```bash
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.evaluateScript '
var aid = currentActivity();
print(activityName(aid));
'
```

---

## Direct Shell Actions

### Open app launcher
```bash
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.activateLauncherMenu
```

### Toggle dashboard (widget view)
```bash
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.toggleDashboard
```

### Toggle widget explorer
```bash
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.toggleWidgetExplorer
```

### Toggle activity manager
```bash
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.toggleActivityManager
```

### Enter/exit edit mode
```bash
# Read current state
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.editMode

# Set edit mode (true/false)
qdbus6 org.kde.plasmashell /PlasmaShell org.freedesktop.DBus.Properties.Set \
  org.kde.PlasmaShell editMode true
```

### Get wallpaper info
```bash
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.wallpaper 0
```

### Dump current layout as JavaScript
```bash
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.dumpCurrentLayoutJS
```

---

## Available Widget Types (67 total)

Common useful widgets:
- `org.kde.plasma.kickoff` - App launcher
- `org.kde.plasma.icontasks` - Taskbar
- `org.kde.plasma.systemtray` - System tray
- `org.kde.plasma.digitalclock` - Clock
- `org.kde.plasma.pager` - Virtual desktop pager
- `org.kde.plasma.notes` - Sticky notes
- `org.kde.plasma.folder` - Folder view
- `org.kde.plasma.systemmonitor` - System monitor
- `org.kde.plasma.systemmonitor.cpu` - CPU monitor
- `org.kde.plasma.systemmonitor.memory` - Memory monitor
- `org.kde.plasma.systemmonitor.net` - Network monitor
- `org.kde.plasma.clipboard` - Clipboard widget
- `org.kde.plasma.colorpicker` - Color picker
- `org.kde.plasma.quicklaunch` - Quick launch
- `org.kde.plasma.showdesktop` - Show desktop button
- `org.kde.milou` - Search (KRunner)

---

## Quirks and Notes

1. **evaluateScript output**: Only `print()` produces output. Expression results are silently discarded.

2. **Type handling with qdbus6**: Complex types (arrays, maps) work poorly. Use `gdbus` for notifications and other calls requiring structured data.

3. **Widget IDs**: IDs are persistent across sessions. When scripting, always look up current IDs rather than hardcoding.

4. **Edit mode**: Some operations require edit mode to be enabled first.

5. **Activity switching**: `setCurrentActivity()` is available but activities need to exist first.

---

## Integration Ideas

### Clipboard bridge for PHP
```bash
# PHP can call these to access desktop clipboard
content=$(qdbus6 org.kde.plasmashell /klipper org.kde.klipper.klipper.getClipboardContents)
```

### Notification from scripts
```bash
# Show notification from any script
gdbus call --session --dest org.kde.plasmashell \
  --object-path /org/freedesktop/Notifications \
  --method org.freedesktop.Notifications.Notify \
  "My Script" 0 "dialog-ok" "Done" "Task completed" '[]' '{}' 3000
```

### Status OSD for long operations
```bash
# Show progress-style feedback
qdbus6 org.kde.plasmashell /org/kde/osdService org.kde.osdService.showText \
  "process-working" "Processing files..."
```

---

## Browser Integration

Separate service: `org.kde.plasma.browser_integration`

Requires the Plasma Browser Integration extension in Firefox/Chrome.

### Object Paths

| Path | Purpose |
|------|---------|
| `/TabsRunner` | Search/switch browser tabs |
| `/HistoryRunner` | Search browser history |
| `/org/mpris/MediaPlayer2` | Control browser media playback |

### Search Browser Tabs

```bash
gdbus call --session \
  --dest org.kde.plasma.browser_integration \
  --object-path /TabsRunner \
  --method org.kde.krunner1.Match "search term"
```

Returns array of: `(id, title, browser, relevance, score, metadata)`

### Switch to Browser Tab

```bash
gdbus call --session \
  --dest org.kde.plasma.browser_integration \
  --object-path /TabsRunner \
  --method org.kde.krunner1.Run "tab_id" ""
```

### Media Control (MPRIS)

```bash
# Get playback status
qdbus6 org.kde.plasma.browser_integration /org/mpris/MediaPlayer2 \
  org.freedesktop.DBus.Properties.Get org.mpris.MediaPlayer2.Player PlaybackStatus

# Play/Pause
qdbus6 org.kde.plasma.browser_integration /org/mpris/MediaPlayer2 \
  org.mpris.MediaPlayer2.Player.PlayPause

# Next/Previous
qdbus6 org.kde.plasma.browser_integration /org/mpris/MediaPlayer2 \
  org.mpris.MediaPlayer2.Player.Next

# Get current track metadata
qdbus6 org.kde.plasma.browser_integration /org/mpris/MediaPlayer2 \
  org.freedesktop.DBus.Properties.Get org.mpris.MediaPlayer2.Player Metadata
```

### Integration Example: Focus Tab Playing Audio

```bash
#!/bin/bash
# Find and focus the tab that's playing audio
status=$(qdbus6 org.kde.plasma.browser_integration /org/mpris/MediaPlayer2 \
  org.freedesktop.DBus.Properties.Get org.mpris.MediaPlayer2.Player PlaybackStatus)

if [ "$status" = "Playing" ]; then
    qdbus6 org.kde.plasma.browser_integration /org/mpris/MediaPlayer2 \
      org.mpris.MediaPlayer2.Raise
fi
```
