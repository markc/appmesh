# Dolphin (KDE File Manager) Integration Notes

## D-Bus Services

Dolphin exposes two interfaces:
- `org.freedesktop.FileManager1` - Standard freedesktop interface (stable)
- `org.kde.dolphin-NNNN` - Instance-specific interface (PID varies)

## QUIRK: Array Syntax in qdbus6

**Problem**: `qdbus6` interprets bracket array syntax literally!

```bash
# WRONG - creates path /home/user/['file:/tmp/
qdbus6 org.freedesktop.FileManager1 /org/freedesktop/FileManager1 \
  org.freedesktop.FileManager1.ShowFolders "['file:///tmp']" ""

# RIGHT - use busctl with type signature
busctl --user call org.freedesktop.FileManager1 /org/freedesktop/FileManager1 \
  org.freedesktop.FileManager1 ShowFolders ass 1 "file:///tmp" ""
```

The `ass` signature means: array of strings, string (for startUpId).
The `1` is the array length.

## Freedesktop FileManager1 Interface

Path: `/org/freedesktop/FileManager1`

### Show Folders (Open Location)

```bash
busctl --user call org.freedesktop.FileManager1 /org/freedesktop/FileManager1 \
  org.freedesktop.FileManager1 ShowFolders ass 1 "file:///path/to/folder" ""
```

### Show Items (Highlight File)

Opens parent folder and selects/highlights the file:

```bash
busctl --user call org.freedesktop.FileManager1 /org/freedesktop/FileManager1 \
  org.freedesktop.FileManager1 ShowItems ass 1 "file:///path/to/file.txt" ""
```

### Show Item Properties

Opens properties dialog for file/folder:

```bash
busctl --user call org.freedesktop.FileManager1 /org/freedesktop/FileManager1 \
  org.freedesktop.FileManager1 ShowItemProperties ass 1 "file:///path/to/file.txt" ""
```

**Note**: May return NoReply error but still works (dialog is modal).

## Dolphin-Specific Interface

Service name varies: `org.kde.dolphin-NNNN` (use `qdbus6 | grep dolphin` to find it)

### Get Current Service Name

```bash
DOLPHIN_SVC=$(qdbus6 | grep org.kde.dolphin | head -1)
```

### Open Directories

```bash
# Single directory
busctl --user call $DOLPHIN_SVC /dolphin/Dolphin_1 \
  org.kde.dolphin.MainWindow openDirectories asb 1 "file:///home" false

# Split view (two directories)
busctl --user call $DOLPHIN_SVC /dolphin/Dolphin_1 \
  org.kde.dolphin.MainWindow openDirectories asb 2 "file:///home" "file:///tmp" true
```

### Check If URL Is Open

```bash
qdbus6 $DOLPHIN_SVC /dolphin/Dolphin_1 \
  org.kde.dolphin.MainWindow.isUrlOpen "file:///tmp"
```

### Other Methods

```bash
# Activate window
qdbus6 $DOLPHIN_SVC /dolphin/Dolphin_1 org.kde.dolphin.MainWindow.activateWindow ""

# Check if active
qdbus6 $DOLPHIN_SVC /dolphin/Dolphin_1 org.kde.dolphin.MainWindow.isActiveWindow

# Quit Dolphin
qdbus6 $DOLPHIN_SVC /dolphin/Dolphin_1 org.kde.dolphin.MainWindow.quit
```

## Triggering Menu Actions

Every Dolphin action is exposed at `/dolphin/Dolphin_1/actions/<action_name>`.

### Trigger Any Action

```bash
qdbus6 $DOLPHIN_SVC /dolphin/Dolphin_1/actions/<action_name> \
  org.qtproject.Qt.QAction.trigger
```

### Useful Actions

| Action | Description |
|--------|-------------|
| `create_dir` | Create New Folder dialog |
| `create_file` | Create New File |
| `show_hidden_files` | Toggle hidden files |
| `split_view` | Toggle split view |
| `open_terminal_here` | Open terminal in current dir |
| `go_home` | Navigate to home |
| `go_up` | Navigate to parent |
| `view_redisplay` | Refresh view |
| `edit_copy` | Copy selected |
| `edit_cut` | Cut selected |
| `edit_paste` | Paste |
| `renamefile` | Rename selected |
| `movetotrash` | Move to trash |
| `deletefile` | Delete permanently |
| `properties` | Show properties |
| `edit_select_all` | Select all |
| `invert_selection` | Invert selection |
| `icons` | Icon view |
| `compact` | Compact view |
| `details` | Details view |

### Get Action Text/Status

```bash
# Get display text
qdbus6 $DOLPHIN_SVC /dolphin/Dolphin_1/actions/show_hidden_files \
  org.qtproject.Qt.QAction.text

# Check if enabled
qdbus6 $DOLPHIN_SVC /dolphin/Dolphin_1/actions/edit_paste \
  org.qtproject.Qt.QAction.enabled

# Check if checked (for toggles)
qdbus6 $DOLPHIN_SVC /dolphin/Dolphin_1/actions/show_hidden_files \
  org.qtproject.Qt.QAction.checked
```

### Toggle Actions

```bash
# Toggle hidden files
qdbus6 $DOLPHIN_SVC /dolphin/Dolphin_1/actions/show_hidden_files \
  org.qtproject.Qt.QAction.toggle

# Set checked state explicitly
qdbus6 $DOLPHIN_SVC /dolphin/Dolphin_1/actions/show_hidden_files \
  org.qtproject.Qt.QAction.setChecked true
```

### Check Current View Mode

```bash
for mode in icons compact details; do
  checked=$(qdbus6 $DOLPHIN_SVC /dolphin/Dolphin_1/actions/$mode \
    org.qtproject.Qt.QAction.checked)
  echo "$mode: $checked"
done
```

## Window Control

### Get Window Title (shows current path)
```bash
qdbus6 $DOLPHIN_SVC /dolphin/Dolphin_1 org.qtproject.Qt.QWidget.windowTitle
```

### Minimize/Maximize/Restore
```bash
# Minimize
qdbus6 $DOLPHIN_SVC /dolphin/Dolphin_1 org.qtproject.Qt.QWidget.showMinimized

# Maximize
qdbus6 $DOLPHIN_SVC /dolphin/Dolphin_1 org.qtproject.Qt.QWidget.showMaximized

# Restore normal
qdbus6 $DOLPHIN_SVC /dolphin/Dolphin_1 org.qtproject.Qt.QWidget.showNormal
```

### Get Window Geometry
```bash
qdbus6 $DOLPHIN_SVC /dolphin/Dolphin_1 org.qtproject.Qt.QWidget.geometry
```

## gdbus Alternative for Arrays

`gdbus` handles array syntax more naturally than qdbus6:

```bash
# Open directory with gdbus
gdbus call --session \
  --dest org.kde.dolphin-210553 \
  --object-path /dolphin/Dolphin_1 \
  --method org.kde.dolphin.MainWindow.openDirectories \
  "['file:///home/user/Documents']" false

# Show items with gdbus
gdbus call --session \
  --dest org.kde.dolphin-210553 \
  --object-path /org/freedesktop/FileManager1 \
  --method org.freedesktop.FileManager1.ShowItems \
  "['file:///path/to/file.txt']" ""
```

## CLI Alternative

For simple operations, `kioclient` may be simpler:

```bash
# Open folder
kioclient exec file:///tmp

# Open file with default app
kioclient exec file:///path/to/file.pdf
```

## InterAPP Example: Screenshot → Open in Dolphin

```bash
#!/bin/bash
# Take screenshot and reveal in file manager

OUTFILE="/tmp/screenshot-$(date +%Y%m%d_%H%M%S).png"

# Capture
spectacle -b -f -n -o "$OUTFILE"

# Reveal in Dolphin (highlight the file)
busctl --user call org.freedesktop.FileManager1 /org/freedesktop/FileManager1 \
  org.freedesktop.FileManager1 ShowItems ass 1 "file://$OUTFILE" ""
```

## KIO File Operations (kioclient5)

`kioclient5` provides command-line access to ALL KIO protocols (ftp, sftp, smb, webdav, etc.)

### List Remote Directory

```bash
kioclient5 --noninteractive ls "ftp://user@host/path/"
kioclient5 --noninteractive ls "sftp://user@host/path/"
kioclient5 --noninteractive ls "smb://server/share/"
```

### Copy Files (works across protocols!)

```bash
# FTP to local
kioclient5 --noninteractive copy \
  "ftp://user@host/path/file.mkv" \
  "file:///home/user/Downloads/"

# Local to SFTP
kioclient5 --noninteractive copy \
  "file:///home/user/doc.pdf" \
  "sftp://server/remote/path/"

# WebDAV to local
kioclient5 --noninteractive copy \
  "webdavs://cloud.example.com/remote.php/dav/files/user/doc.pdf" \
  "file:///home/user/Downloads/"
```

### Other Operations

```bash
# Move/rename
kioclient5 move "src-url" "dest-url"

# Delete
kioclient5 remove "url"

# Create directory
kioclient5 mkdir "url"

# Print file contents
kioclient5 cat "url"

# File info
kioclient5 stat "url"
```

### Flags

| Flag | Description |
|------|-------------|
| `--noninteractive` | No GUI dialogs (for scripting) |
| `--overwrite` | Overwrite existing files |

### URL Encoding

Special characters in usernames need URL encoding:
- `@` → `%40`
- Example: `ftp://user%40domain@host/path/`

## Summary

| Task | Best Method |
|------|-------------|
| Open folder | `busctl ... ShowFolders` |
| Highlight file | `busctl ... ShowItems` |
| File properties | `busctl ... ShowItemProperties` |
| Split view | Dolphin-specific `openDirectories` |
| Menu actions | `qdbus6 ... trigger` |
| Simple open | `kioclient exec` |
| Copy/move files | `kioclient5 copy/move` |
| List remote dir | `kioclient5 ls` |
