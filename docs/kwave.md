# Kwave Integration

KDE's audio editor with D-Bus command interface.

## D-Bus Status

**Command-based D-Bus interface.** Kwave exposes its internal scripting language via D-Bus, allowing full automation through `executeCommand()`.

```bash
# Service has clean name (no PID)
qdbus6 | grep kwave
# org.kde.kwave
```

## D-Bus Interface

Service: `org.kde.kwave`
Path: `/kwave/MainWindow_1`
Interface: `org.kde.kwave.Kwave.TopWidget`

### Core Methods

| Method | Description |
|--------|-------------|
| `executeCommand(QString)` | Execute Kwave internal command |
| `forwardCommand(QString)` | Forward command to context |
| `updateRecentFiles()` | Refresh recent files list |

### Execute Commands via D-Bus

```bash
# Basic pattern
qdbus6 org.kde.kwave /kwave/MainWindow_1 org.kde.kwave.Kwave.TopWidget.executeCommand "command(args)"

# Examples
qdbus6 org.kde.kwave /kwave/MainWindow_1 org.kde.kwave.Kwave.TopWidget.executeCommand "open(/path/to/audio.wav)"
qdbus6 org.kde.kwave /kwave/MainWindow_1 org.kde.kwave.Kwave.TopWidget.executeCommand "plugin:execute(about)"
```

## Available Plugins

Kwave uses plugins for most operations. Available plugins (found in `/usr/lib/qt6/plugins/kwave/`):

### Playback & Recording
- `playback` - Audio playback
- `record` - Audio recording

### Effects & Filters
- `amplifyfree` - Amplify/volume adjustment
- `band_pass` - Band pass filter
- `lowpass` - Low pass filter
- `notch_filter` - Notch filter
- `normalize` - Normalize audio levels
- `pitch_shift` - Change pitch
- `reverse` - Reverse audio
- `noise` - Add/generate noise
- `volume` - Volume adjustment

### File Operations
- `newsignal` - Create new signal
- `saveblocks` - Save selection as blocks
- `export_k3b` - Export for K3b burning

### Codecs
- `codec_wav` - WAV format
- `codec_flac` - FLAC format
- `codec_ogg` - OGG Vorbis format
- `codec_mp3` - MP3 format
- `codec_audiofile` - Other formats
- `codec_ascii` - ASCII export

### Analysis & Tools
- `sonagram` - Sonagram visualization
- `fileinfo` - File information
- `selectrange` - Select range
- `goto` - Go to position
- `samplerate` - Sample rate conversion

## Command Syntax

Kwave uses an internal command language. Commands follow patterns like:

```
# File operations
open(filename)
save()
close()

# Plugin execution
plugin:execute(pluginname)

# Signal creation
newsignal(samplerate, bits, channels, length_ms)

# Example: Create 1 second stereo signal at 44.1kHz, 16-bit
newsignal(44100, 16, 2, 1000)
```

## Command-Line Interface

```bash
# Open a file
kwave audio.wav

# Execute commands on startup
kwave --command "open(/path/to/file.wav)"
```

## DAWN Integration

```php
// Execute any Kwave command
function kwaveCommand(string $cmd): int {
    return (int) qdbus('org.kde.kwave', '/kwave/MainWindow_1',
                       'org.kde.kwave.Kwave.TopWidget.executeCommand', [$cmd]);
}

// Open a file
kwaveCommand('open(/tmp/audio.wav)');

// Apply normalize effect (via plugin)
kwaveCommand('plugin:execute(normalize)');

// Create new signal
kwaveCommand('newsignal(44100,16,2,5000)');  // 5 second stereo
```

## Return Codes

`executeCommand()` returns an integer status:
- `0` - Success (or file not found for open)
- `38` - Command not found / invalid

## Limitations

- Documentation for command syntax is sparse
- Some commands open dialogs (blocking)
- Plugin parameters not well documented
- No query methods (can't ask "what file is open?")

## Notes

- Service name is stable (`org.kde.kwave`) - no PID suffix
- Command interface is powerful but requires knowing command syntax
- Kwave has been around since 1998 - mature but development slowed since 2020
- Plugins are the primary way to add functionality

## Comparison

| Feature | Kwave | Haruna | Kdenlive |
|---------|-------|--------|----------|
| D-Bus interface | Command-based | MPRIS | Full |
| Execute commands | Yes | Playback | Yes |
| Query state | Limited | Yes | Yes |
| Plugin system | Yes | No | Yes |
| Automation | Good | Excellent | Excellent |

Kwave's command interface is powerful for audio automation once you learn the syntax. It's the only KDE audio editor with D-Bus support.

## Sources

- [Kwave - KDE Applications](https://apps.kde.org/kwave/)
- [Kwave SourceForge](https://kwave.sourceforge.net/)
- [GitHub - KDE/kwave](https://github.com/KDE/kwave)
