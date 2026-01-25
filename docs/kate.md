# Kate D-Bus Integration

D-Bus service: `org.kde.kate-{PID}` (PID-specific, find with `qdbus6 | grep kate`)

## Key Object Paths

| Path | Purpose |
|------|---------|
| `/MainApplication` | Application-level control, file opening |
| `/kate/MainWindow_1` | Main window control |
| `/kate/MainWindow_1/actions/*` | Menu actions |
| `/org/kde/kate` | Freedesktop Application interface |

---

## Finding Kate Service

```bash
KATE=$(qdbus6 2>/dev/null | grep 'org.kde.kate-')
echo $KATE
# Example: org.kde.kate-249352
```

---

## Opening Files

### Open file
```bash
qdbus6 org.kde.kate-249352 /MainApplication \
  org.kde.Kate.Application.openUrl "file:///path/to/file.txt" ""
```

Second parameter is encoding (empty = auto-detect).

### Open file at specific line and column
```bash
qdbus6 org.kde.kate-249352 /MainApplication \
  org.kde.Kate.Application.tokenOpenUrlAt \
  "file:///path/to/file.txt" 25 0 "" false
```

Parameters: url, line, column, encoding, isTempFile

Returns a document token ID.

### Open text directly (create new unsaved document)
```bash
qdbus6 org.kde.kate-249352 /MainApplication \
  org.kde.Kate.Application.openInput "Your text content here" "UTF-8"
```

This is useful for piping command output to Kate:
```bash
# Pipe ls output to Kate
ls -la | qdbus6 org.kde.kate-* /MainApplication \
  org.kde.Kate.Application.openInput "$(cat)" "UTF-8"
```

### Set cursor position
```bash
qdbus6 org.kde.kate-249352 /MainApplication \
  org.kde.Kate.Application.setCursor 50 0
```

Parameters: line (1-indexed), column (0-indexed)

---

## Window Control

### Activate Kate (bring to front)
```bash
qdbus6 org.kde.kate-249352 /MainApplication \
  org.kde.Kate.Application.activate ""
```

### Get window title
```bash
qdbus6 org.kde.kate-249352 /kate/MainWindow_1 \
  org.qtproject.Qt.QWidget.windowTitle
```

### Minimize/Maximize
```bash
# Minimize
qdbus6 org.kde.kate-249352 /kate/MainWindow_1 \
  org.qtproject.Qt.QWidget.showMinimized

# Maximize
qdbus6 org.kde.kate-249352 /kate/MainWindow_1 \
  org.qtproject.Qt.QWidget.showMaximized
```

### Hide/show sidebars
```bash
qdbus6 org.kde.kate-249352 /kate/MainWindow_1 \
  org.kde.kate.KateMDI.MainWindow.setSidebarsVisible false
```

---

## Sessions

### Get current session
```bash
qdbus6 org.kde.kate-249352 /MainApplication \
  org.kde.Kate.Application.activeSession
```

### Activate session
```bash
qdbus6 org.kde.kate-249352 /MainApplication \
  org.kde.Kate.Application.activateSession "MySession"
```

---

## Triggering Actions

### Via KMainWindow.activateAction
```bash
qdbus6 org.kde.kate-249352 /kate/MainWindow_1 \
  org.kde.KMainWindow.activateAction "action_name"
```

### Via QAction.trigger (direct)
```bash
qdbus6 org.kde.kate-249352 /kate/MainWindow_1/actions/file_new \
  org.qtproject.Qt.QAction.trigger
```

### Common Actions

| Action | Description |
|--------|-------------|
| `file_new` | New document |
| `file_open` | Open file dialog |
| `file_save_all` | Save all documents |
| `file_reload_all` | Reload all documents |
| `file_close` | Close current document |
| `file_close_all` | Close all documents |
| `file_quit` | Quit Kate |
| `view_split_vert` | Split view vertically |
| `view_split_horiz` | Split view horizontally |
| `view_close_current_space` | Close current split |
| `view_quick_open` | Quick open dialog |
| `view_prev_tab` | Previous tab |
| `view_next_tab` | Next tab |
| `switch_to_tab_1` ... `switch_to_tab_10` | Switch to tab N |
| `sessions_new` | New session |
| `sessions_save` | Save session |
| `sessions_manage` | Manage sessions |
| `fullscreen` | Toggle fullscreen |
| `options_configure` | Open settings |
| `file_copy_filepath` | Copy file path |
| `file_copy_filename` | Copy filename |
| `file_open_containing_folder` | Open in file manager |
| `git_show_file_history` | Show git history |

---

## Action Control (QAction Interface)

Each action at `/kate/MainWindow_1/actions/{name}` has:

### Trigger action
```bash
qdbus6 org.kde.kate-249352 /kate/MainWindow_1/actions/file_new \
  org.qtproject.Qt.QAction.trigger
```

### Toggle checkable action
```bash
qdbus6 org.kde.kate-249352 /kate/MainWindow_1/actions/options_show_statusbar \
  org.qtproject.Qt.QAction.toggle
```

### Check if enabled
```bash
qdbus6 org.kde.kate-249352 /kate/MainWindow_1/actions/file_save_all \
  org.qtproject.Qt.QAction.enabled
```

### Get action text
```bash
qdbus6 org.kde.kate-249352 /kate/MainWindow_1/actions/file_new \
  org.qtproject.Qt.QAction.text
```

---

## Freedesktop Application Interface

At `/org/kde/kate`:

### Open files
```bash
gdbus call --session \
  --dest org.kde.kate-249352 \
  --object-path /org/kde/kate \
  --method org.freedesktop.Application.Open \
  "['file:///path/to/file.txt']" '{}'
```

### Activate application
```bash
gdbus call --session \
  --dest org.kde.kate-249352 \
  --object-path /org/kde/kate \
  --method org.freedesktop.Application.Activate '{}'
```

---

## Signals

### Document closed
```bash
# Monitor document close events
dbus-monitor "interface='org.kde.Kate.Application',member='documentClosed'"
```

### Exiting
```bash
# Monitor Kate exit
dbus-monitor "interface='org.kde.Kate.Application',member='exiting'"
```

---

## Quirks and Notes

1. **Service name includes PID**: Like Dolphin, discover with `qdbus6 | grep kate`.

2. **Multiple windows**: Each window is `MainWindow_1`, `MainWindow_2`, etc.

3. **openInput is powerful**: Can create documents from any text source - pipe output, clipboard, generated content.

4. **tokenOpenUrlAt returns token**: The token can be used to track the document (useful for scripts that need to wait for document close).

5. **Line numbers are 1-indexed**: Unlike many editors, Kate's line parameter starts at 1.

6. **Sessions**: Kate has full session support - save/restore document sets.

---

## Integration Examples

### Open file at error line from compiler output
```bash
#!/bin/bash
# Parse GCC error: filename:line:column: error: message
ERROR="$1"
FILE=$(echo "$ERROR" | cut -d: -f1)
LINE=$(echo "$ERROR" | cut -d: -f2)
COL=$(echo "$ERROR" | cut -d: -f3)

KATE=$(qdbus6 | grep 'org.kde.kate-' | head -1)
if [ -z "$KATE" ]; then
    kate "$FILE" --line "$LINE" --column "$COL" &
else
    qdbus6 "$KATE" /MainApplication \
      org.kde.Kate.Application.tokenOpenUrlAt \
      "file://$FILE" "$LINE" "$COL" "" false
fi
```

### Pipe command output to Kate
```bash
#!/bin/bash
# Open command output in Kate
KATE=$(qdbus6 | grep 'org.kde.kate-' | head -1)
CONTENT=$("$@")

if [ -z "$KATE" ]; then
    echo "$CONTENT" | kate --stdin &
else
    qdbus6 "$KATE" /MainApplication \
      org.kde.Kate.Application.openInput "$CONTENT" "UTF-8"
fi
```

### Quick edit from Dolphin integration
```bash
#!/bin/bash
# Called from Dolphin service menu
FILE="$1"
LINE="${2:-1}"

KATE=$(qdbus6 | grep 'org.kde.kate-' | head -1)
if [ -n "$KATE" ]; then
    qdbus6 "$KATE" /MainApplication \
      org.kde.Kate.Application.tokenOpenUrlAt \
      "file://$FILE" "$LINE" 0 "" false
    qdbus6 "$KATE" /MainApplication \
      org.kde.Kate.Application.activate ""
else
    kate --line "$LINE" "$FILE" &
fi
```

---

## InterAPP Ideas

### PHP code review workflow
```php
<?php
// Open file in Kate at specific line from PHP
function openInKate($path, $line = 1) {
    $kate = trim(shell_exec("qdbus6 | grep 'org.kde.kate-' | head -1"));
    if (empty($kate)) {
        exec("kate --line $line " . escapeshellarg($path) . " &");
    } else {
        $cmd = sprintf(
            'qdbus6 %s /MainApplication org.kde.Kate.Application.tokenOpenUrlAt ' .
            '"file://%s" %d 0 "" false',
            escapeshellarg($kate),
            $path,
            $line
        );
        exec($cmd);
        exec("qdbus6 $kate /MainApplication org.kde.Kate.Application.activate ''");
    }
}
```

### Show notification then open log
```bash
# Notify user and open log file at end
gdbus call --session --dest org.kde.plasmashell \
  --object-path /org/freedesktop/Notifications \
  --method org.freedesktop.Notifications.Notify \
  "Build" 0 "dialog-warning" "Build Failed" "Check log for errors" '[]' '{}' 5000

KATE=$(qdbus6 | grep 'org.kde.kate-' | head -1)
LINES=$(wc -l < /tmp/build.log)
qdbus6 "$KATE" /MainApplication \
  org.kde.Kate.Application.tokenOpenUrlAt \
  "file:///tmp/build.log" "$LINES" 0 "" false
```
