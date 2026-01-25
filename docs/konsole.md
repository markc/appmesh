# Konsole D-Bus Integration

Konsole exposes rich D-Bus interfaces for terminal automation - creating sessions, sending commands, reading output, and managing splits.

## Service Discovery

Konsole uses a **per-process service name**: `org.kde.konsole-$PID`

```bash
# Find running Konsole services
qdbus6 | grep konsole

# Get the oldest Konsole PID
KONSOLE_PID=$(pgrep -o konsole)

# Explore the service
qdbus6 org.kde.konsole-$KONSOLE_PID
```

### Object Paths
```
/Sessions/1, /Sessions/2, ...    # Individual terminal sessions
/Windows/1, /Windows/2, ...      # Window containers
/konsole/MainWindow_1/actions/*  # Menu actions
```

---

## Session Interface

Each tab/terminal is a "Session" with full control over the shell.

### Running Commands
```bash
KONSOLE="org.kde.konsole-$(pgrep -o konsole)"

# Run a command (presses Enter automatically)
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.runCommand "ls -la"

# Send raw text (no Enter - useful for partial input)
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.sendText "partial command"
```

### Reading Terminal Output
```bash
# Get ALL displayed text (scrollback + visible)
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.getAllDisplayedText

# Get displayed text as array of lines
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.getAllDisplayedTextList

# Get specific line range (offsets from current view)
# Negative = lines above, positive = lines below
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.getDisplayedText -10 0
```

### Session Information
```bash
# Get the shell's PID
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.processId

# Get the foreground process PID (currently running command)
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.foregroundProcessId

# Get current profile name
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.profile

# Get scrollback size
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.historySize
```

### Session Configuration
```bash
# Set history/scrollback size
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.setHistorySize 5000

# Change profile
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.setProfile "Solarized"

# Set environment variables (before shell starts)
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.setEnvironment '["VAR=value"]'

# Set tab title (role: 0=local, 1=remote)
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.setTitle 0 "Build Server"
```

### Activity Monitoring
```bash
# Monitor for any activity
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.setMonitorActivity true

# Monitor for silence (command finished)
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.setMonitorSilence true
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.setMonitorSilenceSeconds 5

# Monitor for shell prompt (command completed)
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.setMonitorPrompt true

# Check monitoring status
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.isMonitorActivity
```

### Input Mirroring
```bash
# Copy input to ALL sessions (broadcast mode)
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.copyInputToAllSessions

# Copy to specific sessions
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.copyInputToSessions '[2, 3]'

# Stop copying
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.copyInputToNone
```

---

## Window Interface

Manage tabs, splits, and session navigation.

### Session Management
```bash
KONSOLE="org.kde.konsole-$(pgrep -o konsole)"

# Create new session (tab)
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.newSession
# Returns: new session ID

# Create with specific profile
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.newSession "Solarized"

# Create with profile and working directory
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.newSession "Default" "/home/user/project"

# Get current session ID
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.currentSession

# Switch to specific session
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.setCurrentSession 3

# Navigate sessions
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.nextSession
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.prevSession
```

### Tab Information
```bash
# List all sessions in window
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.sessionList

# Count sessions
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.sessionCount

# List available profiles
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.profileList

# Get/set default profile
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.defaultProfile
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.setDefaultProfile "MyProfile"
```

### Split Management
```bash
# Create horizontal split from view
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.createSplit 1 true

# Create vertical split
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.createSplit 1 false

# Get current split layout
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.viewHierarchy

# Save/load layouts
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.saveLayoutFile
qdbus6 $KONSOLE /Windows/1 org.kde.konsole.Window.loadLayoutFile
```

---

## Practical Examples

### Run Command and Capture Output
```bash
#!/bin/bash
KONSOLE="org.kde.konsole-$(pgrep -o konsole)"
SESSION="/Sessions/1"

# Run command
qdbus6 $KONSOLE $SESSION org.kde.konsole.Session.runCommand "whoami"
sleep 0.5

# Capture last few lines of output
qdbus6 $KONSOLE $SESSION org.kde.konsole.Session.getDisplayedText -5 0
```

### Create Dev Environment Layout
```bash
#!/bin/bash
KONSOLE="org.kde.konsole-$(pgrep -o konsole)"
WINDOW="/Windows/1"

# Create three sessions
s1=$(qdbus6 $KONSOLE $WINDOW org.kde.konsole.Window.newSession "Default" "/home/user/project")
s2=$(qdbus6 $KONSOLE $WINDOW org.kde.konsole.Window.newSession "Default" "/home/user/project")
s3=$(qdbus6 $KONSOLE $WINDOW org.kde.konsole.Window.newSession "Default" "/home/user/project")

# Name them
qdbus6 $KONSOLE /Sessions/$s1 org.kde.konsole.Session.setTitle 0 "Editor"
qdbus6 $KONSOLE /Sessions/$s2 org.kde.konsole.Session.setTitle 0 "Server"
qdbus6 $KONSOLE /Sessions/$s3 org.kde.konsole.Session.setTitle 0 "Git"

# Start services
qdbus6 $KONSOLE /Sessions/$s1 org.kde.konsole.Session.runCommand "vim ."
qdbus6 $KONSOLE /Sessions/$s2 org.kde.konsole.Session.runCommand "npm run dev"
```

### Broadcast to All Terminals
```bash
#!/bin/bash
# Run same command on all sessions (useful for cluster admin)
KONSOLE="org.kde.konsole-$(pgrep -o konsole)"
WINDOW="/Windows/1"

sessions=$(qdbus6 $KONSOLE $WINDOW org.kde.konsole.Window.sessionList)

for sid in $sessions; do
  qdbus6 $KONSOLE /Sessions/$sid org.kde.konsole.Session.runCommand "uptime"
done
```

---

## Quirks & Notes

1. **Service naming** - Each Konsole process has its own D-Bus name (`org.kde.konsole-$PID`)
2. **Session IDs** - Not necessarily sequential; use `sessionList` to get valid IDs
3. **runCommand vs sendText** - `runCommand` adds Enter, `sendText` doesn't
4. **Output timing** - After `runCommand`, wait briefly before reading output
5. **Foreground PID** - Returns -1 if shell is idle (no foreground process)
6. **Closed sessions** - Session paths remain but methods will fail; check `sessionList`
