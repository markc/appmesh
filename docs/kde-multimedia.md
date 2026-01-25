# KDE Multimedia Apps - D-Bus Integration Summary

Overview of D-Bus support in KDE multimedia applications.

## Quick Reference

| App | Type | D-Bus | Interface |
|-----|------|-------|-----------|
| **Haruna** | Video Player | MPRIS | Full playback control |
| **Elisa** | Music Player | MPRIS | Full playback control |
| **Dragon** | Video Player | MPRIS | Full playback control |
| **Kwave** | Audio Editor | Commands | `executeCommand()` scripting |
| **Kdenlive** | Video Editor | Full | Clips, timeline, effects, render |
| **K3b** | CD/DVD Burning | Good | Rip, burn, format operations |
| **KMix** | Volume Mixer | Full | Volume, mute, device control |
| **Juk** | Music Player | MPRIS | Full playback control |

---

## Media Players (MPRIS)

All KDE media players implement MPRIS (Media Player Remote Interfacing Specification).

### Elisa (Music Player)

```bash
# Service names
org.kde.elisa
org.mpris.MediaPlayer2.elisa

# Standard MPRIS controls
qdbus6 org.mpris.MediaPlayer2.elisa /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.PlayPause
qdbus6 org.mpris.MediaPlayer2.elisa /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Next
qdbus6 org.mpris.MediaPlayer2.elisa /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Previous

# Open files
qdbus6 org.kde.elisa /org/kde/elisa org.freedesktop.Application.Open '["file:///path/to/music.mp3"]' '{}'
```

### Dragon (Video Player)

```bash
# Service name
org.mpris.MediaPlayer2.org.kde.dragonplayer

# Standard MPRIS controls
qdbus6 org.mpris.MediaPlayer2.org.kde.dragonplayer /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.PlayPause
```

### Haruna (Video Player)

See `haruna.md` for full documentation.

```bash
org.mpris.MediaPlayer2.haruna
```

### Juk (Music Player/Organizer)

```bash
# MPRIS interface
org.mpris.MediaPlayer2.juk
```

---

## KMix (Volume Mixer)

Full volume control via D-Bus.

### Service

```bash
org.kde.kmix
```

### Mixer Control

Path: `/Mixers`

```bash
# Get available mixers
qdbus6 org.kde.kmix /Mixers org.kde.KMix.MixSet.mixers

# Get/set master
qdbus6 org.kde.kmix /Mixers org.kde.KMix.MixSet.currentMasterMixer
qdbus6 org.kde.kmix /Mixers org.kde.KMix.MixSet.currentMasterControl
```

### Individual Device Control

Path: `/Mixers/<mixer>/<device>`

```bash
DEVICE="/Mixers/PulseAudio__Playback_Devices_1/alsa_output_..."

# Get/set volume (0-100 or absolute)
qdbus6 org.kde.kmix $DEVICE org.kde.KMix.Control.volume
qdbus6 org.kde.kmix $DEVICE org.freedesktop.DBus.Properties.Set org.kde.KMix.Control volume 50

# Mute control
qdbus6 org.kde.kmix $DEVICE org.kde.KMix.Control.mute
qdbus6 org.kde.kmix $DEVICE org.kde.KMix.Control.toggleMute

# Convenience methods
qdbus6 org.kde.kmix $DEVICE org.kde.KMix.Control.increaseVolume
qdbus6 org.kde.kmix $DEVICE org.kde.KMix.Control.decreaseVolume
```

### Control Properties

| Property | Type | Description |
|----------|------|-------------|
| `volume` | int | Volume 0-100 |
| `absoluteVolume` | int | Raw volume value |
| `absoluteVolumeMin/Max` | int | Volume range |
| `mute` | bool | Muted state |
| `canMute` | bool | Supports muting |
| `readableName` | string | Display name |
| `iconName` | string | Icon for device |

---

## K3b (CD/DVD Burning)

### Service

```bash
org.k3b.k3b
```

### Main Operations

Path: `/k3b/MainWindow_1`
Interface: `kde.k3b.K3b.MainWindow`

```bash
# CD Audio ripping
qdbus6 org.k3b.k3b /k3b/MainWindow_1 kde.k3b.K3b.MainWindow.slotCddaRip

# Video CD ripping
qdbus6 org.k3b.k3b /k3b/MainWindow_1 kde.k3b.K3b.MainWindow.slotVideoCdRip

# DVD ripping
qdbus6 org.k3b.k3b /k3b/MainWindow_1 kde.k3b.K3b.MainWindow.slotVideoDvdRip

# Media copy (disc to disc)
qdbus6 org.k3b.k3b /k3b/MainWindow_1 kde.k3b.K3b.MainWindow.slotMediaCopy

# Write ISO image
qdbus6 org.k3b.k3b /k3b/MainWindow_1 kde.k3b.K3b.MainWindow.slotWriteImage

# Format medium (erase RW disc)
qdbus6 org.k3b.k3b /k3b/MainWindow_1 kde.k3b.K3b.MainWindow.slotFormatMedium

# Clear current project
qdbus6 org.k3b.k3b /k3b/MainWindow_1 kde.k3b.K3b.MainWindow.slotClearProject

# Check system (verify burning setup)
qdbus6 org.k3b.k3b /k3b/MainWindow_1 kde.k3b.K3b.MainWindow.slotCheckSystem
```

### Action System

```bash
# List all actions
qdbus6 org.k3b.k3b /k3b/MainWindow_1 org.kde.KMainWindow.actions

# Trigger action
qdbus6 org.k3b.k3b /k3b/MainWindow_1 org.kde.KMainWindow.activateAction "file_save"
```

---

## Kwave (Audio Editor)

See `kwave.md` for full documentation.

### Command Interface

```bash
org.kde.kwave

# Execute internal commands
qdbus6 org.kde.kwave /kwave/MainWindow_1 org.kde.kwave.Kwave.TopWidget.executeCommand "open(/path/to/audio.wav)"
```

---

## Kdenlive (Video Editor)

See `kdenlive.md` for full documentation.

### Highlights

```bash
org.kde.kdenlive-<PID>

# Add clip to project
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.addProjectClip "file:///path/to/video.mp4"

# Add to timeline
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.addTimelineClip "file:///path/to/video.mp4"
```

---

## DAWN Integration Examples

```php
// Volume control via KMix
function setMasterVolume(int $volume): void {
    // Get master device path first
    $mixer = qdbus('org.kde.kmix', '/Mixers', 'org.kde.KMix.MixSet.currentMasterMixer');
    $control = qdbus('org.kde.kmix', '/Mixers', 'org.kde.KMix.MixSet.currentMasterControl');
    // Then set volume on that device
}

// Play music in Elisa
qdbus('org.mpris.MediaPlayer2.elisa', '/org/mpris/MediaPlayer2',
      'org.mpris.MediaPlayer2.Player.Play');

// Rip CD with K3b
qdbus('org.k3b.k3b', '/k3b/MainWindow_1', 'kde.k3b.K3b.MainWindow.slotCddaRip');
```

---

## Apps Without D-Bus

| App | Type | Notes |
|-----|------|-------|
| Kamoso | Webcam | Not tested |
| Audiotube | YouTube Music | Not tested |
| Plasmatube | YouTube | Installed, not tested |
| Kasts | Podcasts | Not tested |

---

## MPRIS Quick Reference

All MPRIS players share these methods:

```bash
SERVICE="org.mpris.MediaPlayer2.<player>"

# Playback
qdbus6 $SERVICE /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Play
qdbus6 $SERVICE /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Pause
qdbus6 $SERVICE /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.PlayPause
qdbus6 $SERVICE /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Stop
qdbus6 $SERVICE /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Next
qdbus6 $SERVICE /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Previous

# Seeking
qdbus6 $SERVICE /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Seek 10000000  # +10s

# Volume (0.0-1.0)
qdbus6 $SERVICE /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Volume
qdbus6 $SERVICE /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.setVolume 0.5

# Status
qdbus6 $SERVICE /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.PlaybackStatus
qdbus6 $SERVICE /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.Metadata

# Open URI
qdbus6 $SERVICE /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Player.OpenUri "file:///path/to/media"

# Window
qdbus6 $SERVICE /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Raise
qdbus6 $SERVICE /org/mpris/MediaPlayer2 org.mpris.MediaPlayer2.Quit
```

---

## Summary

KDE multimedia apps have **excellent D-Bus support**:

- **Media players** all implement MPRIS standard
- **KMix** provides full system volume control
- **K3b** exposes CD/DVD burning operations
- **Kwave** has unique command-based scripting
- **Kdenlive** has the most comprehensive interface

This makes KDE the best Linux desktop for multimedia automation.
