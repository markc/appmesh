# Krita Integration

KDE's professional digital painting application.

## D-Bus Status

**No D-Bus interface.** Krita does not register a session bus service, making it uncontrollable via D-Bus once launched.

```bash
# Krita does not appear in service list
qdbus6 | grep -i krita
# (no output)
```

## Command-Line Interface

Krita has a rich CLI for batch operations and automation:

### Opening Files

```bash
# Open an image
krita /path/to/image.png

# Open multiple files
krita image1.png image2.kra

# Open with template
krita --template

# Start with specific workspace
krita --workspace "Animation"

# Start with window layout
krita --windowlayout "Big Paint"

# Load a saved session
krita --load-session "MyProject"
```

### Display Modes

```bash
# Start in fullscreen
krita --fullscreen

# Start in canvas-only mode (no UI chrome)
krita --canvasonly

# Skip splash screen
krita --nosplash
```

### Creating New Images

```bash
# Create new image with specific parameters
# Format: colorspace,depth,width,height
krita --new-image RGBA,U8,1920,1080

# Colorspace options: RGBA, XYZA, LABA, CMYKA, GRAY, YCbCrA
# Depth options: U8, U16, F16, F32
```

### Batch Export (Headless)

```bash
# Export single file and exit
krita --export --export-filename output.png input.kra

# Export animation sequence
krita --export-sequence --export-filename frame_.png animation.kra
```

### File Layers

```bash
# Add file as layer to existing document
krita --file-layer overlay.png base.kra
```

## Batch Processing Examples

### Convert KRA to PNG

```bash
krita --export --export-filename output.png input.kra
```

### Export Animation Frames

```bash
krita --export-sequence --export-filename /tmp/frame_.png animation.kra
```

### Create Blank Canvas

```bash
# Create 4K canvas with 16-bit color
krita --new-image RGBA,U16,3840,2160
```

## Python Scripting

Krita has an internal Python scripting API for plugins and automation **within** the application. This is not accessible externally via D-Bus.

Scripts are stored in:
- `~/.local/share/krita/pykrita/`

The API allows:
- Document manipulation
- Layer operations
- Filter application
- Tool automation
- Custom UI panels

However, this requires running scripts **inside** Krita, not remotely.

## DAWN Integration

Limited to launching and batch export:

```php
// Open an image in Krita
shell_exec('krita ' . escapeshellarg($imagePath) . ' &');

// Batch export (blocking)
shell_exec('krita --export --export-filename ' .
           escapeshellarg($output) . ' ' .
           escapeshellarg($input));

// Create new document with specific size
shell_exec('krita --new-image RGBA,U8,1920,1080 &');
```

## Limitations

- **No remote control**: Cannot manipulate open documents via D-Bus
- **No status queries**: Cannot ask "what file is open?" or "what tool is selected?"
- **No events**: Cannot subscribe to document changes
- **Batch only**: Export works headless, but interactive control requires manual use

## Comparison

| Feature | Krita | Haruna | Gwenview |
|---------|-------|--------|----------|
| D-Bus interface | None | Full MPRIS | None |
| CLI batch operations | Excellent | Basic | None |
| Remote control | No | Yes | No |
| Internal scripting | Python (plugins) | No | No |
| Automation-friendly | Partial (batch) | Excellent | Poor |

## Wishlist

Features that would make Krita more automation-friendly:

1. D-Bus interface for basic operations (open file, save, export)
2. Remote script execution (run Python script in running instance)
3. Status queries (current document, layer, tool)
4. Document change notifications

## Notes

- Krita's Python API is powerful but internal-only
- Batch export (`--export`) is the main automation path
- For viewing images, Krita is overkill - use Gwenview
- Animation export works well for automated pipelines
- The `--canvasonly` mode is useful for presentation/drawing sessions
