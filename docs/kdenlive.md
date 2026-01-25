# Kdenlive Integration

KDE's professional video editor with comprehensive D-Bus support.

## D-Bus Status

**Full D-Bus interface.** Kdenlive registers as `org.kde.kdenlive-<PID>` and exposes extensive automation capabilities.

```bash
# Find running instance
qdbus6 | grep kdenlive
# org.kde.kdenlive-415857

# Service name includes PID - changes each launch
```

## D-Bus Interface

Service: `org.kde.kdenlive-<PID>`
Path: `/kdenlive/MainWindow_1`
Interface: `org.kde.kdenlive.MainWindow`

### Project Clip Management

```bash
SERVICE="org.kde.kdenlive-$(pgrep -o kdenlive)"

# Add clip to project bin
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.addProjectClip "file:///path/to/video.mp4"

# Add clip to specific folder in bin
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.addProjectClip "file:///path/to/video.mp4" "Footage"

# Add clip directly to timeline
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.addTimelineClip "file:///path/to/video.mp4"
```

### Effects

```bash
# Add effect to selected clip
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.addEffect "fadeIn"
```

### Rendering

```bash
# Trigger render with script
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.scriptRender "/path/to/render/script.sh"

# Report rendering progress (for external renderers)
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.setRenderingProgress "file:///output.mp4" 50 1200

# Report rendering finished
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.setRenderingFinished "file:///output.mp4" 0 ""
```

### Subtitles

```bash
# Show subtitle track
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.showSubtitleTrack

# Add subtitle
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.slotAddSubtitle "Hello World"

# Edit subtitle
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.slotEditSubtitle

# Export subtitles
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.slotExportSubtitle

# Toggle subtitle visibility
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.slotShowSubtitles true
```

### Zoom Control

```bash
# Zoom in
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.slotZoomIn

# Zoom out
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.slotZoomOut

# Set specific zoom level
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.slotSetZoom 5
```

### Transcoding

```bash
# Open transcode dialog
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.slotTranscode

# Transcode specific files
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.slotTranscode "file1.mov,file2.mov"
```

### Project Management

```bash
# Clean project (remove unused clips)
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.slotCleanProject

# Edit project settings
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.slotEditProjectSettings

# Update project path
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.updateProjectPath "/new/path"
```

### Window Control

```bash
# Exit application
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.exitApp

# Clean restart
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.MainWindow.cleanRestart true

# Set window title
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.kdenlive.KMainWindow.setCaption "My Project"

# Show fullscreen
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.qtproject.Qt.QWidget.showFullScreen
```

### Action System

Kdenlive exposes all menu actions via D-Bus:

```bash
# List all available actions
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.KMainWindow.actions

# Trigger an action by name
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.KMainWindow.activateAction "file_save"
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.KMainWindow.activateAction "edit_undo"

# Check if action is enabled
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.KMainWindow.actionIsEnabled "file_save"

# Enable/disable actions
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.KMainWindow.enableAction "file_save"
qdbus6 $SERVICE /kdenlive/MainWindow_1 org.kde.KMainWindow.disableAction "file_save"
```

## Available Methods

| Method | Description |
|--------|-------------|
| `addProjectClip(url)` | Add clip to project bin |
| `addProjectClip(url, folder)` | Add clip to specific bin folder |
| `addTimelineClip(url)` | Add clip directly to timeline |
| `addEffect(effectId)` | Apply effect to selection |
| `scriptRender(url)` | Execute render script |
| `slotAddSubtitle(text)` | Add subtitle at playhead |
| `slotExportSubtitle()` | Export subtitles |
| `slotZoomIn/Out()` | Zoom timeline |
| `slotSetZoom(level)` | Set specific zoom |
| `slotTranscode(urls)` | Transcode files |
| `slotCleanProject()` | Remove unused clips |
| `exitApp()` | Close Kdenlive |
| `activateAction(name)` | Trigger menu action |

## Command-Line Interface

Kdenlive also has powerful CLI options:

```bash
# Open a project
kdenlive project.kdenlive

# Headless render
kdenlive --render project.kdenlive output.mp4

# Render with specific preset
kdenlive --render --render-preset "MP4-H264/AAC" project.kdenlive output.mp4

# Async render (exit immediately)
kdenlive --render --render-async project.kdenlive output.mp4

# Add clips on launch
kdenlive -i "clip1.mp4,clip2.mp4" project.kdenlive

# Skip welcome screen
kdenlive --no-welcome
```

## DAWN Integration

```php
// Helper to get Kdenlive service name
function kdenliveService(): ?string {
    $pid = trim(shell_exec('pgrep -o kdenlive 2>/dev/null'));
    return $pid ? "org.kde.kdenlive-$pid" : null;
}

// Add clip to project
$service = kdenliveService();
if ($service) {
    qdbus($service, '/kdenlive/MainWindow_1',
          'org.kde.kdenlive.MainWindow.addProjectClip',
          ['file:///tmp/video.mp4']);
}

// Trigger save
qdbus($service, '/kdenlive/MainWindow_1',
      'org.kde.KMainWindow.activateAction', ['file_save']);

// Add subtitle
qdbus($service, '/kdenlive/MainWindow_1',
      'org.kde.kdenlive.MainWindow.slotAddSubtitle',
      ['Generated subtitle text']);
```

## Signals

Monitor Kdenlive events:

```bash
dbus-monitor "sender='org.kde.kdenlive-$(pgrep -o kdenlive)'"
```

## Notes

- Service name includes PID - must discover dynamically
- Full action system means any menu item can be triggered
- Headless rendering (`--render`) works without X display
- Rendering progress can be monitored/controlled via D-Bus
- Subtitle automation is well-supported

## Comparison

| Feature | Kdenlive | Haruna | Krita | Gwenview |
|---------|----------|--------|-------|----------|
| D-Bus interface | Full | MPRIS | None | None |
| Remote control | Extensive | Playback | No | No |
| CLI batch | Render | Basic | Export | None |
| Action system | Yes | No | No | No |
| Automation-friendly | Excellent | Excellent | Partial | Poor |

Kdenlive is the gold standard for KDE application automation. Every KDE app should aspire to this level of D-Bus integration.
