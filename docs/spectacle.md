# Spectacle (KDE Screenshot Tool) Integration Notes

## CLI Usage (Recommended for Scripting)

Background mode (`-b`) is the best approach for automation - no GUI, just capture and save.

### Basic Commands

```bash
# Fullscreen
spectacle -b -f -o /path/to/output.png

# Current monitor only
spectacle -b -m -o /path/to/output.png

# Active window
spectacle -b -a -o /path/to/output.png

# Window under cursor
spectacle -b -u -o /path/to/output.png

# Rectangular region (interactive selection)
spectacle -b -r -o /path/to/output.png

# With delay (milliseconds)
spectacle -b -f -d 2000 -o /path/to/output.png
```

### Useful Flags

| Flag | Description |
|------|-------------|
| `-b` | Background mode (no GUI) |
| `-f` | Fullscreen capture |
| `-m` | Current monitor |
| `-a` | Active window |
| `-u` | Window under cursor |
| `-r` | Rectangular region (user selects) |
| `-o <file>` | Output file path |
| `-d <ms>` | Delay in milliseconds |
| `-p` | Include mouse pointer |
| `-e` | Exclude window decorations |
| `-S` | Exclude window shadow |
| `-c` | Copy to clipboard (instead of file) |
| `-C` | Copy file path to clipboard |
| `-n` | No notification |

### Examples

```bash
# Screenshot with pointer, no notification
spectacle -b -f -p -n -o /tmp/shot.png

# Active window without decorations or shadow
spectacle -b -a -e -S -o /tmp/window.png

# Delayed fullscreen (3 seconds)
spectacle -b -f -d 3000 -o /tmp/delayed.png

# Copy to clipboard only
spectacle -b -f -c
```

## D-Bus Interface

Service: `org.kde.Spectacle`
Path: `/`

### Screenshot Methods

```bash
# Fullscreen (includeMousePointer: 0 or 1)
qdbus6 org.kde.Spectacle / org.kde.Spectacle.FullScreen 0

# Current screen
qdbus6 org.kde.Spectacle / org.kde.Spectacle.CurrentScreen 0

# Active window (includeDecorations, includePointer, includeShadow)
qdbus6 org.kde.Spectacle / org.kde.Spectacle.ActiveWindow 1 0 1

# Window under cursor
qdbus6 org.kde.Spectacle / org.kde.Spectacle.WindowUnderCursor 1 0 1

# Rectangular region
qdbus6 org.kde.Spectacle / org.kde.Spectacle.RectangularRegion 0

# Open without screenshot
qdbus6 org.kde.Spectacle / org.kde.Spectacle.OpenWithoutScreenshot
```

### Recording Methods

```bash
# Record region
qdbus6 org.kde.Spectacle / org.kde.Spectacle.RecordRegion 0

# Record screen
qdbus6 org.kde.Spectacle / org.kde.Spectacle.RecordScreen 0

# Record window
qdbus6 org.kde.Spectacle / org.kde.Spectacle.RecordWindow 0
```

### Signals

| Signal | Parameters | Description |
|--------|------------|-------------|
| `ScreenshotTaken` | fileName (QString) | Emitted when screenshot is saved |
| `ScreenshotFailed` | message (QString) | Emitted on capture failure |
| `RecordingTaken` | fileName (QString) | Emitted when recording is saved |
| `RecordingFailed` | message (QString) | Emitted on recording failure |

### Monitor Signals

```bash
# Watch for screenshot completion
dbus-monitor "interface='org.kde.Spectacle'" &
```

## CLI vs D-Bus

| Feature | CLI (`spectacle -b`) | D-Bus |
|---------|---------------------|-------|
| Auto-save to file | Yes (`-o`) | No (opens GUI) |
| Headless operation | Yes | No |
| Signal on completion | Via notification | Yes |
| Best for scripting | **Yes** | Interactive only |

**Recommendation**: Use CLI with `-b` flag for automation. D-Bus is useful for triggering interactive captures or monitoring completion signals.

## InterAPP Example: Screenshot Pipeline

```bash
#!/bin/bash
# Take screenshot, resize, and copy path to clipboard

OUTFILE="/tmp/screenshot-$(date +%Y%m%d_%H%M%S).png"

# Capture
spectacle -b -f -n -o "$OUTFILE"

# Resize (requires ImageMagick)
convert "$OUTFILE" -resize 50% "${OUTFILE%.png}-small.png"

# Copy path to clipboard
echo "${OUTFILE%.png}-small.png" | xclip -selection clipboard

echo "Screenshot saved and path copied to clipboard"
```
