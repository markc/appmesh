# KRunner D-Bus Integration

KRunner is the KDE application launcher and search interface. It launches on-demand and provides a plugin-based search system with the `krunner1` protocol.

## Service Discovery

KRunner is **not a persistent service** - it launches when invoked.

```bash
# Launch KRunner daemon
krunner -d &

# Or trigger via global shortcut (Alt+Space or Alt+F2)

# Find the service
qdbus6 | grep krunner
# Returns: org.kde.krunner
```

---

## Main KRunner Interface

Control the KRunner UI.

```bash
# Show KRunner
qdbus6 org.kde.krunner /App org.kde.krunner.App.display

# Toggle visibility
qdbus6 org.kde.krunner /App org.kde.krunner.App.toggleDisplay

# Show with a pre-filled query
qdbus6 org.kde.krunner /App org.kde.krunner.App.query "firefox"

# Show with clipboard contents as query
qdbus6 org.kde.krunner /App org.kde.krunner.App.displayWithClipboardContents

# Show only a specific runner plugin
qdbus6 org.kde.krunner /App org.kde.krunner.App.displaySingleRunner "org.kde.windowedwidgets"

# Query with specific runner only
qdbus6 org.kde.krunner /App org.kde.krunner.App.querySingleRunner "calculator" "2+2"
```

### Common Queries
```bash
# Calculator
qdbus6 org.kde.krunner /App org.kde.krunner.App.query "= 15 * 7"
qdbus6 org.kde.krunner /App org.kde.krunner.App.query "calc 100/4"

# Applications
qdbus6 org.kde.krunner /App org.kde.krunner.App.query "firefox"

# Commands
qdbus6 org.kde.krunner /App org.kde.krunner.App.query "> ls -la"

# Web shortcuts
qdbus6 org.kde.krunner /App org.kde.krunner.App.query "gg:kde plasma"

# Unit conversion
qdbus6 org.kde.krunner /App org.kde.krunner.App.query "100 USD in EUR"
```

---

## Runner Plugin Protocol (krunner1)

Runners implement the `org.kde.krunner1` interface for programmatic matching.

### Available Runners (via D-Bus)
```bash
# Window runner (in KWin)
qdbus6 org.kde.KWin /WindowsRunner

# Activities runner
qdbus6 org.kde.runners.activities /runner
```

### Querying Runners Directly
```bash
# Match windows containing "chrome"
qdbus6 --literal org.kde.KWin /WindowsRunner org.kde.krunner1.Match "chrome"
# Returns: array of (id, text, subtext, iconname, relevance, properties)

# Get available actions for matches
qdbus6 --literal org.kde.KWin /WindowsRunner org.kde.krunner1.Actions

# Run a match (activate window, launch app, etc.)
qdbus6 org.kde.KWin /WindowsRunner org.kde.krunner1.Run "match_id" ""
```

### Match Response Format
```
(sssida{sv})
 │││││└── properties (dict)
 ││││└─── relevance (double 0.0-1.0)
 │││└──── icon name (int - deprecated)
 ││└───── subtext (string)
 │└────── text (string)
 └─────── matchId (string)
```

---

## Activity Manager

The activities runner also hosts the KDE Activity Manager.

### List Activities
```bash
# Get all activities with info
qdbus6 --literal org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.ListActivitiesWithInformation
# Returns: [(uuid, name, description, icon, state)]

# Simple list (just UUIDs)
qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.ListActivities

# Get current activity UUID
qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.CurrentActivity
```

### Switch Activities
```bash
# Switch to activity by UUID
qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.SetCurrentActivity "uuid-here"

# Navigate activities
qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.NextActivity

qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.PreviousActivity
```

### Manage Activities
```bash
# Create new activity (returns UUID)
qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.AddActivity "Work"

# Remove activity
qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.RemoveActivity "uuid-here"

# Rename activity
qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.SetActivityName "uuid-here" "New Name"

# Set activity icon
qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.SetActivityIcon "uuid-here" "folder-work"

# Set description
qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.SetActivityDescription "uuid-here" "Work projects"
```

### Query Activity Info
```bash
# Get name
qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.ActivityName "uuid-here"

# Get icon
qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.ActivityIcon "uuid-here"

# Get full info
qdbus6 --literal org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.ActivityInformation "uuid-here"
```

---

## Runner Plugins

### Built-in Runners
Located in `/usr/lib/qt6/plugins/kf6/krunner/`:

| Plugin | Description |
|--------|-------------|
| kwin-runner-windows | Window switching |
| plasma-runner-baloosearch | File search (Baloo) |
| plasma-runner-browserhistory | Browser history |
| plasma-runner-browsertabs | Open browser tabs |
| plasma-runners-activities | Activity switching |

### Configuration
Runner settings in `~/.config/krunnerrc`:
```ini
[General]
FreeFloating=true

[Plugins]
baloosearchEnabled=false
```

### Listing Enabled Runners
```bash
# Check if a runner is enabled
kreadconfig6 --file krunnerrc --group Plugins --key "baloosearchEnabled"
```

---

## Practical Examples

### Quick Calculator
```bash
#!/bin/bash
# Show KRunner with calculation
expr="$1"
qdbus6 org.kde.krunner /App org.kde.krunner.App.query "= $expr"
```

### Switch to Window by Name
```bash
#!/bin/bash
# Find and activate window containing search term
search="$1"
match=$(qdbus6 --literal org.kde.KWin /WindowsRunner org.kde.krunner1.Match "$search" | \
  grep -oP '"0_\{[^}]+\}"' | head -1 | tr -d '"')

if [ -n "$match" ]; then
  qdbus6 org.kde.KWin /WindowsRunner org.kde.krunner1.Run "$match" ""
fi
```

### Activity Switcher
```bash
#!/bin/bash
# Cycle through activities
qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.NextActivity
```

### Create Work/Home Activities
```bash
#!/bin/bash
# Create standard activity set
work=$(qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.AddActivity "Work")
qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.SetActivityIcon "$work" "folder-work"

home=$(qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.AddActivity "Personal")
qdbus6 org.kde.runners.activities /ActivityManager/Activities \
  org.kde.ActivityManager.Activities.SetActivityIcon "$home" "user-home"
```

---

## Current System State

```
Activity: Default (650f90db-c094-4d46-99f5-b0c416fcdf4c)
KRunner style: Free floating
Baloo search: Disabled
```

---

## Quirks & Notes

1. **On-demand service** - KRunner only registers on D-Bus when running
2. **Launch with `-d`** - Use `krunner -d` for daemon mode (stays running)
3. **Match IDs** - Window match IDs include UUIDs, not stable across queries
4. **Icon data** - Match results include raw icon bitmap data (very large)
5. **Single runner mode** - `displaySingleRunner` needs the runner's plugin ID
6. **Activities vs Desktops** - Activities group windows; desktops are spatial arrangement
7. **Runner protocol** - Any app can implement `org.kde.krunner1` to be searchable
