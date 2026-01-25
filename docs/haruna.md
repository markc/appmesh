# Haruna Integration

KDE's mpv-based video player with full MPRIS D-Bus support.

## D-Bus Status

**Full MPRIS implementation.** Haruna registers as `org.mpris.MediaPlayer2.haruna` and exposes the standard media player interface.

```bash
# Verify service is running
qdbus6 | grep haruna
# org.mpris.MediaPlayer2.haruna
```

## MPRIS Interface

Service: `org.mpris.MediaPlayer2.haruna`
Path: `/org/mpris/MediaPlayer2`

### Playback Control

```bash
# Play/Pause toggle
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.PlayPause

# Play
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Play

# Pause
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Pause

# Stop
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Stop

# Next track
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Next

# Previous track
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Previous
```

### Seeking

```bash
# Seek forward 10 seconds (10000000 microseconds)
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Seek 10000000

# Seek backward 10 seconds
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Seek -10000000

# Get current position (microseconds)
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Position

# Set absolute position (requires track ID)
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.setPosition 30000000
```

### Volume Control

```bash
# Get volume (0.0 to 1.0)
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Volume

# Set volume to 50%
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.setVolume 0.5
```

### Open Media

```bash
# Open a file
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.OpenUri "file:///path/to/video.mp4"

# Open a URL (including YouTube via yt-dlp)
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.OpenUri "https://example.com/video.mp4"
```

### Status Queries

```bash
# Playback status (Playing, Paused, Stopped)
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.PlaybackStatus

# Get metadata (title, artist, duration, etc.)
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Metadata

# Loop status (None, Track, Playlist)
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.LoopStatus

# Shuffle status
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Shuffle
```

### Loop and Shuffle

```bash
# Set loop mode: None, Track, Playlist
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.setLoopStatus "Track"

# Enable shuffle
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.setShuffle true
```

### Window Control

```bash
# Bring window to front
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Raise

# Quit application
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Quit
```

## Available Methods

| Method | Description |
|--------|-------------|
| `Play` | Start playback |
| `Pause` | Pause playback |
| `PlayPause` | Toggle play/pause |
| `Stop` | Stop playback |
| `Next` | Next track in playlist |
| `Previous` | Previous track |
| `Seek(offset)` | Seek relative (microseconds) |
| `SetPosition(trackId, pos)` | Seek absolute |
| `OpenUri(uri)` | Open file or URL |
| `Raise` | Bring window to front |
| `Quit` | Close application |

## Available Properties

| Property | Type | Description |
|----------|------|-------------|
| `PlaybackStatus` | string | Playing, Paused, Stopped |
| `Position` | int64 | Current position (μs) |
| `Volume` | double | 0.0 to 1.0 |
| `LoopStatus` | string | None, Track, Playlist |
| `Shuffle` | bool | Shuffle enabled |
| `Metadata` | dict | Track info (title, duration, etc.) |
| `CanPlay/Pause/Seek/...` | bool | Capability flags |

## Signals (Events)

Haruna emits signals you can monitor:

- `playbackStatusChanged` - Play state changed
- `metadataChanged` - Track changed
- `volumeChanged` - Volume adjusted
- `loopStatusChanged` - Loop mode changed
- `shuffleChanged` - Shuffle toggled

Monitor with:
```bash
dbus-monitor "interface='org.mpris.MediaPlayer2.Player'"
```

## DAWN Integration

```php
// Play/Pause toggle
qdbus('org.mpris.MediaPlayer2.haruna', '/org/mpris/MediaPlayer2',
      'org.mpris.MediaPlayer2.Player.PlayPause');

// Open a video
qdbus('org.mpris.MediaPlayer2.haruna', '/org/mpris/MediaPlayer2',
      'org.mpris.MediaPlayer2.Player.OpenUri', ['file:///tmp/video.mp4']);

// Get playback status
$status = qdbus('org.mpris.MediaPlayer2.haruna', '/org/mpris/MediaPlayer2',
                'org.mpris.MediaPlayer2.Player.PlaybackStatus');

// Set volume to 75%
qdbus('org.mpris.MediaPlayer2.haruna', '/org/mpris/MediaPlayer2',
      'org.mpris.MediaPlayer2.Player.setVolume', ['0.75']);
```

## Command Line

```bash
# Open a file
haruna /path/to/video.mp4

# With yt-dlp format selection
haruna --ytdl-format-selection "bestvideo+bestaudio" "https://youtube.com/watch?v=..."
```

## Supported MIME Types

Haruna explicitly reports its supported formats via D-Bus:

```bash
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.SupportedMimeTypes
# video/*
# audio/*
```

**No image support.** Despite being built on mpv (which can display images), Haruna only accepts video and audio files. Attempting to open an image via `OpenUri` silently fails.

```bash
# This does NOT work - image is ignored
qdbus6 org.mpris.MediaPlayer2.haruna /org/mpris/MediaPlayer2 \
  org.mpris.MediaPlayer2.Player.OpenUri "file:///tmp/screenshot.png"
```

For a D-Bus controllable image viewer, see alternatives in gwenview.md.

## Notes

- MPRIS is a freedesktop.org standard - same interface works with VLC, mpv, Elisa, etc.
- Position and seek values are in **microseconds** (multiply seconds by 1,000,000)
- Haruna must be running for D-Bus control to work
- yt-dlp integration allows streaming from YouTube and other sites

## Comparison with Gwenview

| Feature | Haruna | Gwenview |
|---------|--------|----------|
| D-Bus interface | Full MPRIS | None |
| Remote control | Yes | No |
| Status queries | Yes | No |
| Event signals | Yes | No |
| Automation-friendly | Excellent | Poor |

Haruna demonstrates what good desktop automation looks like. Gwenview could learn from this.
