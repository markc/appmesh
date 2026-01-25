# KDE System Settings via D-Bus

Unlike traditional GUI apps, "System Settings" itself doesn't expose much via D-Bus. Instead, system configuration is managed through various **persistent services** that run in the background.

## Key Discovery

System Settings (`systemsettings`) is just a GUI wrapper. The real work happens through:
- **kded6** - KDE Daemon hosting loadable modules
- **org.kde.Solid.PowerManagement** - Power management
- **org.kde.kglobalaccel** - Global keyboard shortcuts
- **org.kde.plasmashell** - Desktop shell with OSD, clipboard, wallpaper

---

## kded6 - The Module Host

The KDE daemon loads modules on-demand for various system functions.

```bash
# List all loaded modules
qdbus6 org.kde.kded6 /kded org.kde.kded6.loadedModules

# Load/unload a module
qdbus6 org.kde.kded6 /kded org.kde.kded6.loadModule "modulename"
qdbus6 org.kde.kded6 /kded org.kde.kded6.unloadModule "modulename"
```

### Notable Modules
| Module | Purpose |
|--------|---------|
| keyboard | Keyboard layout switching |
| bluedevil | Bluetooth management |
| networkmanagement | WiFi/network secrets |
| plasma_accentcolor_service | Theme accent color |
| freespacenotifier | Low disk space warnings |
| ktimezoned | Timezone detection |

### Keyboard Layout Switching
```bash
# Get current layout index
qdbus6 org.kde.kded6 /modules/keyboard org.kde.KeyboardLayouts.getLayout

# Switch layouts
qdbus6 org.kde.kded6 /modules/keyboard org.kde.KeyboardLayouts.switchToNextLayout
```

---

## Power Management

Full control over power profiles and screen brightness.

### Power Profiles
```bash
# Get available profiles
qdbus6 org.kde.Solid.PowerManagement /org/kde/Solid/PowerManagement/Actions/PowerProfile profileChoices
# Returns: power-saver, balanced, performance

# Get/set current profile
qdbus6 org.kde.Solid.PowerManagement /org/kde/Solid/PowerManagement/Actions/PowerProfile currentProfile
qdbus6 org.kde.Solid.PowerManagement /org/kde/Solid/PowerManagement/Actions/PowerProfile setProfile "balanced"

# Temporarily hold a profile (returns cookie for release)
qdbus6 org.kde.Solid.PowerManagement /org/kde/Solid/PowerManagement/Actions/PowerProfile holdProfile "performance" "Heavy compilation" "myapp"
```

### Screen Brightness
```bash
# Get brightness info (external display example)
qdbus6 org.kde.Solid.PowerManagement /org/kde/ScreenBrightness/display1 \
  org.freedesktop.DBus.Properties.GetAll org.kde.ScreenBrightness.Display
# Returns: Brightness: 4000, MaxBrightness: 10000, Label: "LG Electronics LG ULTRAFINE"

# Set brightness (value, flags)
qdbus6 org.kde.Solid.PowerManagement /org/kde/ScreenBrightness/display1 \
  org.kde.ScreenBrightness.Display.SetBrightness 5000 0
```

### Other Power Methods
```bash
# Check lid state (laptops)
qdbus6 org.kde.Solid.PowerManagement /org/kde/Solid/PowerManagement isLidClosed

# Battery remaining time (milliseconds, 0 if on AC)
qdbus6 org.kde.Solid.PowerManagement /org/kde/Solid/PowerManagement batteryRemainingTime

# Current power source
qdbus6 org.kde.Solid.PowerManagement /org/kde/Solid/PowerManagement currentProfile
# Returns: "AC" or "Battery"
```

---

## Global Keyboard Shortcuts

Query and modify system-wide keyboard shortcuts.

```bash
# List all registered shortcut components
qdbus6 --literal org.kde.kglobalaccel /kglobalaccel allMainComponents

# Get all actions for a component
qdbus6 --literal org.kde.kglobalaccel /kglobalaccel allActionsForComponent \
  '["plasmashell", "plasmashell", "", ""]'

# Block/unblock global shortcuts (useful during presentations)
qdbus6 org.kde.kglobalaccel /kglobalaccel blockGlobalShortcuts true
```

---

## Plasmashell Services

The desktop shell exposes several useful interfaces.

### Desktop/Shell Control
```bash
# Toggle widget explorer (add widgets mode)
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.toggleWidgetExplorer

# Toggle dashboard/overview
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.toggleDashboard

# Toggle activity manager
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.toggleActivityManager

# Activate app launcher (like pressing Meta key)
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.activateLauncherMenu

# Enter/exit edit mode
qdbus6 org.kde.plasmashell /PlasmaShell org.freedesktop.DBus.Properties.Set \
  org.kde.PlasmaShell editMode true
```

### Wallpaper
```bash
# Get current wallpaper info
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.wallpaper 0

# Set wallpaper (screenNum 0 = primary)
qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.setWallpaper \
  "org.kde.image" '{"Image": "/path/to/image.jpg"}' 0
```

### Klipper (Clipboard Manager)
```bash
# Get current clipboard contents
qdbus6 org.kde.plasmashell /klipper org.kde.klipper.klipper.getClipboardContents

# Set clipboard
qdbus6 org.kde.plasmashell /klipper org.kde.klipper.klipper.setClipboardContents "text"

# Get clipboard history
qdbus6 org.kde.plasmashell /klipper org.kde.klipper.klipper.getClipboardHistoryMenu

# Get specific history item (0 = most recent)
qdbus6 org.kde.plasmashell /klipper org.kde.klipper.klipper.getClipboardHistoryItem 0

# Clear history
qdbus6 org.kde.plasmashell /klipper org.kde.klipper.klipper.clearClipboardHistory
```

### OSD Notifications (On-Screen Display)
```bash
# Show custom OSD message
qdbus6 org.kde.plasmashell /org/kde/osdService org.kde.osdService.showText \
  "preferences-system" "Custom message here"

# Trigger specific OSD types
qdbus6 org.kde.plasmashell /org/kde/osdService org.kde.osdService.volumeChanged 75
qdbus6 org.kde.plasmashell /org/kde/osdService org.kde.osdService.brightnessChanged 50
```

---

## Desktop Notifications (freedesktop standard)

Standard notification API, works with any desktop.

```bash
# Send notification
gdbus call --session --dest org.freedesktop.Notifications \
  --object-path /org/freedesktop/Notifications \
  --method org.freedesktop.Notifications.Notify \
  "AppName" 0 "dialog-information" "Title" "Body text" "[]" "{}" 5000

# Parameters: app_name, replaces_id, icon, summary, body, actions, hints, timeout_ms

# Close a notification by ID
gdbus call --session --dest org.freedesktop.Notifications \
  --object-path /org/freedesktop/Notifications \
  --method org.freedesktop.Notifications.CloseNotification 42
```

---

## Accent Color

Set the system accent color dynamically.

```bash
# Set accent color (uint32 ARGB format)
qdbus6 org.kde.kded6 /modules/plasma_accentcolor_service \
  org.kde.plasmashell.accentColor.setAccentColor 0xFF2196F3
```

---

## Practical Script Examples

### Toggle Power Profile
```bash
#!/bin/bash
current=$(qdbus6 org.kde.Solid.PowerManagement \
  /org/kde/Solid/PowerManagement/Actions/PowerProfile currentProfile)

case "$current" in
  "balanced") next="performance" ;;
  "performance") next="power-saver" ;;
  *) next="balanced" ;;
esac

qdbus6 org.kde.Solid.PowerManagement \
  /org/kde/Solid/PowerManagement/Actions/PowerProfile setProfile "$next"

qdbus6 org.kde.plasmashell /org/kde/osdService \
  org.kde.osdService.showText "battery-profile-$next" "Power: $next"
```

### Clipboard Backup
```bash
#!/bin/bash
# Save clipboard history to file
qdbus6 org.kde.plasmashell /klipper org.kde.klipper.klipper.getClipboardHistoryMenu \
  > ~/.clipboard-backup-$(date +%Y%m%d).txt
```

---

## Display Scaling Configuration

**Important:** Display scaling is NOT exposed via D-Bus. It requires editing config files directly.

### D-Bus vs KConfig

KDE uses two complementary systems:
- **D-Bus**: Runtime communication (call methods, get/set runtime properties, signals)
- **KConfig (files)**: Persistent configuration storage

Display scaling requires a compositor restart, so it's stored in config files, not D-Bus.

### Config Files

| File | Purpose |
|------|---------|
| `~/.config/kwinoutputconfig.json` | Wayland output config (scale, resolution, HDR) |
| `~/.config/kwinrc` | KWin settings including Xwayland scaling |

### Changing Display Scale

```bash
# Check current scale
cat ~/.config/kwinoutputconfig.json | grep '"scale"'

# Change scale from 2.5 to 2.0 (edit the JSON)
sed -i 's/"scale": 2.5/"scale": 2.0/g' ~/.config/kwinoutputconfig.json

# Also update Xwayland scale
sed -i 's/Scale=2.5/Scale=2.0/g' ~/.config/kwinrc

# Requires logout/login or reboot to take effect
```

### KScreen D-Bus (Limited)

KScreen exposes minimal D-Bus interface - no scale configuration:

```bash
qdbus6 org.kde.KScreen /
# Only: backend(), quit(), requestBackend()
# No methods for changing scale or output configuration
```

### Note on HiDPI and Qt

Fractional scaling (2.25x, 2.5x) can cause font rendering issues in Qt WebEngine apps (like KMail's message viewer) due to [QTBUG-113574](https://bugreports.qt.io/browse/QTBUG-113574). Integer scaling (1x, 2x) is more reliable until the Qt bug is fixed.

---

## Quirks & Notes

1. **System Settings app** - Doesn't need to be running; services are always available
2. **Brightness paths** - Display paths like `/org/kde/ScreenBrightness/display1` may vary
3. **Complex types** - Use `--literal` flag for array/struct arguments
4. **Module loading** - Some kded6 modules load on-demand; may need explicit load
5. **OSD vs Notifications** - OSD is transient overlay, notifications persist in history
6. **Display scaling** - Not available via D-Bus; must edit config files directly
