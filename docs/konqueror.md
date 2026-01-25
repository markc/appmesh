# Konqueror D-Bus Integration

Konqueror is KDE's classic file manager and web browser. It has a **rich D-Bus interface** - significantly more capable than Falkon or Firefox.

## Service

```bash
# Service appears when Konqueror is running
qdbus6 | grep konqueror
# Returns: org.kde.konqueror
```

## Object Paths

```
/KonqMain                    # Main application control
/KonqHistoryManager          # Browsing history
/KonqSessionManager          # Session save/restore
/konqueror/MainWindow_N      # Window instances
/konqueror/MainWindow_N/actions/*  # All menu/toolbar actions
```

---

## Main Application Interface

Control Konqueror at the application level.

```bash
# Get all open URLs across all windows/tabs
qdbus6 org.kde.konqueror /KonqMain org.kde.Konqueror.Main.urls

# Get list of window object paths
qdbus6 org.kde.konqueror /KonqMain org.kde.Konqueror.Main.getWindows

# Create new window with URL
qdbus6 org.kde.konqueror /KonqMain org.kde.Konqueror.Main.createNewWindow \
  "https://example.com" "" "" false

# Open browser window
qdbus6 org.kde.konqueror /KonqMain org.kde.Konqueror.Main.openBrowserWindow \
  "https://example.com" ""

# Create window with file selection (for file manager mode)
qdbus6 org.kde.konqueror /KonqMain org.kde.Konqueror.Main.createNewWindowWithSelection \
  "file:///home" '["file1.txt", "file2.txt"]' ""
```

---

## Window Interface

Full control over individual windows.

### Reading State
```bash
WINDOW="/konqueror/MainWindow_1"

# Get current URL
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.currentURL

# Get current page title
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.currentTitle

# Get location bar URL (may differ from actual page)
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.locationBarURL

# Get view count
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.viewCount

# Check fullscreen mode
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.fullScreenMode
```

### Navigation
```bash
# Open URL in current view
qdbus6 org.kde.konqueror $WINDOW org.kde.Konqueror.MainWindow.openUrl \
  "https://httpbin.org/html" false

# Open URL in new tab
qdbus6 org.kde.konqueror $WINDOW org.kde.Konqueror.MainWindow.newTab \
  "https://example.com" false

# Reload current page
qdbus6 org.kde.konqueror $WINDOW org.kde.Konqueror.MainWindow.reload

# Navigation methods
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.slotBack
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.slotForward
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.slotUp
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.slotHome
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.slotStop
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.slotReload
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.slotForceReload
```

### Tab Management
```bash
# Add new empty tab
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.slotAddTab

# Activate specific tab (0-indexed)
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.activateTab 0

# Close other tabs
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.slotRemoveOtherTabs

# Duplicate current tab
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.slotDuplicateTabPopup
```

### View Splitting
```bash
# Split view horizontally
qdbus6 org.kde.konqueror $WINDOW org.kde.Konqueror.MainWindow.splitViewHorizontally

# Split view vertically
qdbus6 org.kde.konqueror $WINDOW org.kde.Konqueror.MainWindow.splitViewVertically

# Remove current view
qdbus6 org.kde.konqueror $WINDOW org.kde.konqueror.KonqMainWindow.slotRemoveView
```

### Window Control
```bash
# Fullscreen
qdbus6 org.kde.konqueror $WINDOW org.qtproject.Qt.QWidget.showFullScreen
qdbus6 org.kde.konqueror $WINDOW org.qtproject.Qt.QWidget.showNormal

# Minimize/Maximize
qdbus6 org.kde.konqueror $WINDOW org.qtproject.Qt.QWidget.showMinimized
qdbus6 org.kde.konqueror $WINDOW org.qtproject.Qt.QWidget.showMaximized

# Close window
qdbus6 org.kde.konqueror $WINDOW org.qtproject.Qt.QWidget.close

# Set window title
qdbus6 org.kde.konqueror $WINDOW org.kde.KMainWindow.setCaption "Custom Title"
```

---

## Action Interface

Every menu item and toolbar button is accessible as an action.

```bash
# List all available actions
qdbus6 org.kde.konqueror /konqueror/MainWindow_1 org.kde.KMainWindow.actions

# Trigger an action
qdbus6 org.kde.konqueror /konqueror/MainWindow_1/actions/reload \
  org.qtproject.Qt.QAction.trigger

# Check if action is enabled
qdbus6 org.kde.konqueror /konqueror/MainWindow_1 \
  org.kde.KMainWindow.actionIsEnabled "reload"

# Enable/disable action
qdbus6 org.kde.konqueror /konqueror/MainWindow_1 \
  org.kde.KMainWindow.enableAction "reload"
qdbus6 org.kde.konqueror /konqueror/MainWindow_1 \
  org.kde.KMainWindow.disableAction "reload"
```

### Key Actions
| Action | Description |
|--------|-------------|
| `newtab` | New tab |
| `new_window` | New window |
| `reload` | Reload page |
| `hard_reload` | Reload ignoring cache |
| `stop` | Stop loading |
| `go_back` | Navigate back |
| `go_forward` | Navigate forward |
| `go_up` | Parent directory |
| `go_home` | Home page |
| `fullscreen` | Toggle fullscreen |
| `splitviewh` | Split horizontally |
| `splitviewv` | Split vertically |
| `inspect_page` | Open developer tools |
| `print` | Print page |

---

## History Manager

Monitor browsing history changes.

```bash
qdbus6 org.kde.konqueror /KonqHistoryManager

# Signals:
# notifyHistoryEntry(QByteArray) - new entry added
# notifyRemove(QString url) - entry removed
# notifyClear() - history cleared
# notifyMaxAge(int days) - max age changed
# notifyMaxCount(int count) - max count changed
```

---

## Session Manager

Save and restore sessions.

```bash
qdbus6 org.kde.konqueror /KonqSessionManager

# Signal: saveCurrentSession(QString path)
```

---

## Practical Examples

### Open Multiple Sites in Tabs
```bash
#!/bin/bash
KONQ="org.kde.konqueror"
WINDOW="/konqueror/MainWindow_1"

sites=("https://kde.org" "https://github.com" "https://news.ycombinator.com")

for site in "${sites[@]}"; do
  qdbus6 $KONQ $WINDOW org.kde.Konqueror.MainWindow.newTab "$site" false
  sleep 0.5
done
```

### Monitor All Open URLs
```bash
#!/bin/bash
watch -n 2 'qdbus6 org.kde.konqueror /KonqMain org.kde.Konqueror.Main.urls'
```

### Quick Navigate Script
```bash
#!/bin/bash
# Usage: konq-go.sh <url>
url="${1:-https://kde.org}"
qdbus6 org.kde.konqueror /konqueror/MainWindow_1 \
  org.kde.Konqueror.MainWindow.openUrl "$url" false
```

### Split Browser Layout
```bash
#!/bin/bash
KONQ="org.kde.konqueror"
WINDOW="/konqueror/MainWindow_1"

# Create 2x2 grid
qdbus6 $KONQ $WINDOW org.kde.Konqueror.MainWindow.splitViewHorizontally
qdbus6 $KONQ $WINDOW org.kde.Konqueror.MainWindow.splitViewVertically
```

---

## Limitations

Konqueror's D-Bus interface **cannot**:
- Fetch page content/source directly
- Execute JavaScript in the page
- Access DOM elements
- Read page HTML

It provides **control** but not **content access**.

For content fetching, still use:
- `kioclient cat https://...`
- Direct HTTP libraries

---

## Comparison

| Feature | Konqueror | Falkon | Firefox |
|---------|-----------|--------|---------|
| Open URL | ✓ | ✓ | ✓ |
| Read current URL | ✓ | ✗ | ✗ |
| Read page title | ✓ | ✗ | ✗ |
| Navigation (back/fwd) | ✓ | ✗ | ✗ |
| Tab management | ✓ | ✗ | ✗ |
| View splitting | ✓ | ✗ | ✗ |
| List all URLs | ✓ | ✗ | ✗ |
| Action triggers | ✓ | ✗ | ✗ |
| Fetch content | ✗ | ✗ | ✗ |

Konqueror has the **richest D-Bus interface** of any Linux browser.

---

## For InterAPP

Konqueror is the best browser for D-Bus automation:
- Can programmatically control browsing
- Can query current state (URL, title)
- Can manage tabs and windows
- Integrates with KDE ecosystem

Still cannot fetch content via D-Bus - use alongside `kioclient` for that.
