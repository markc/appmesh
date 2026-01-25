# Gwenview Integration

KDE's default image viewer.

## D-Bus Status

**No D-Bus interface exposed.** Unlike many KDE applications, Gwenview does not register a session bus service. This means it cannot be controlled remotely via D-Bus once launched.

```bash
# Gwenview does not appear in service list
qdbus6 | grep -i gwenview
# (no output)
```

## Command-Line Interface

Launch options are the primary automation method:

```bash
# Open an image
gwenview /path/to/image.png

# Start in fullscreen mode
gwenview -f /path/to/image.png

# Start slideshow of a directory
gwenview -s /path/to/images/

# Start in spotlight mode (minimal UI)
gwenview -m /path/to/image.png
```

### All Options

| Option | Description |
|--------|-------------|
| `-f, --fullscreen` | Start in fullscreen mode |
| `-s, --slideshow` | Start in slideshow mode |
| `-m, --spotlight` | Start in spotlight mode (minimal chrome) |

## Automation Patterns

### Open Screenshot for Review

```bash
# Take screenshot with Spectacle, open in Gwenview
spectacle -f -b -n -o /tmp/screenshot.png && gwenview /tmp/screenshot.png
```

### Slideshow from Directory

```bash
gwenview -s ~/Pictures/
```

### Quick Image Preview (Spotlight)

```bash
gwenview -m /tmp/image.png &
```

## DAWN Integration

Since Gwenview lacks D-Bus, integration is limited to launching:

```php
// In DAWN context - open image in Gwenview
shell_exec('gwenview ' . escapeshellarg($imagePath) . ' &');

// Fullscreen presentation
shell_exec('gwenview -f ' . escapeshellarg($imagePath) . ' &');
```

## Limitations

- **No remote control**: Cannot change images, zoom, or navigate once launched
- **No status queries**: Cannot ask "what image is displayed?"
- **No events**: Cannot subscribe to "image changed" notifications
- **Fire and forget**: Launch it and hope for the best

## Alternatives for Scriptable Image Viewing

If D-Bus control is needed, consider:

- **feh**: Lightweight, has remote control via signals
- **sxiv**: Simple, scriptable with key bindings
- **imv**: Wayland-native, some IPC support

## Wishlist

Features that would make Gwenview more automation-friendly:

1. D-Bus interface for basic operations (next/prev, zoom, close)
2. Ability to update displayed image without relaunching
3. Status queries (current file, zoom level)
4. Signal emission on user actions

## Notes

- Gwenview is primarily designed as an interactive application
- For programmatic image display in KDE, consider using `kioclient5` or embedding via KParts
- The spotlight mode (`-m`) is useful for temporary "just show this" scenarios
