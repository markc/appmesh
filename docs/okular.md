# Okular Integration

KDE's universal document viewer (PDF, EPUB, DjVu, etc.) with comprehensive D-Bus support.

## D-Bus Status

**Excellent D-Bus interface.** Okular provides extensive control through multiple interfaces covering document navigation, window management, actions, and toolbars.

## Service Names

Okular registers two service types:

```bash
# PID-based service (main)
org.kde.okular-<PID>

# Instance-based service (UUID)
org.kde.okular.Instance_<UUID>

# Find running instance
qdbus6 | grep okular
```

Both services expose the same interfaces. Use the PID-based service for scripts:

```bash
SERVICE="org.kde.okular-$(pgrep -o okular)"
```

## Object Paths

| Path | Interface | Purpose |
|------|-----------|---------|
| `/okular` | `org.kde.okular` | Document control, navigation, metadata |
| `/okularshell` | `org.kde.okular` | Open documents, raise window |
| `/okular/okular__Shell_1` | Multiple | Window control, actions, toolbars |

## Document Interface (`/okular`)

Service: `org.kde.okular-<PID>`
Path: `/okular`
Interface: `org.kde.okular`

### Document Control

```bash
SERVICE="org.kde.okular-$(pgrep -o okular)"

# Open a document
qdbus6 $SERVICE /okular org.kde.okular.openDocument "file:///path/to/document.pdf"

# Reload current document
qdbus6 $SERVICE /okular org.kde.okular.reload

# Get current document path
qdbus6 $SERVICE /okular org.kde.okular.currentDocument

# Open containing folder
qdbus6 $SERVICE /okular org.kde.okular.slotOpenContainingFolder
```

### Page Navigation

```bash
# Get total pages
qdbus6 $SERVICE /okular org.kde.okular.pages

# Get current page (0-indexed)
qdbus6 $SERVICE /okular org.kde.okular.currentPage

# Go to specific page (0-indexed)
qdbus6 $SERVICE /okular org.kde.okular.goToPage 5

# Navigation shortcuts
qdbus6 $SERVICE /okular org.kde.okular.slotNextPage
qdbus6 $SERVICE /okular org.kde.okular.slotPreviousPage
qdbus6 $SERVICE /okular org.kde.okular.slotGotoFirst
qdbus6 $SERVICE /okular org.kde.okular.slotGotoLast
```

### Search

```bash
# Open find dialog
qdbus6 $SERVICE /okular org.kde.okular.slotFind

# Start with find text (on open)
qdbus6 $SERVICE /okular org.kde.okular.enableStartWithFind "search term"
```

### Presentation & Display

```bash
# Toggle presentation mode
qdbus6 $SERVICE /okular org.kde.okular.slotTogglePresentation

# Toggle color inversion (dark mode)
qdbus6 $SERVICE /okular org.kde.okular.slotToggleChangeColors

# Set color change mode explicitly
qdbus6 $SERVICE /okular org.kde.okular.slotSetChangeColors true
```

### Printing

```bash
# Print preview
qdbus6 $SERVICE /okular org.kde.okular.slotPrintPreview

# Enable print on open
qdbus6 $SERVICE /okular org.kde.okular.enableStartWithPrint

# Exit after printing
qdbus6 $SERVICE /okular org.kde.okular.enableExitAfterPrint
```

### Metadata

```bash
# Standard PDF metadata keys
qdbus6 $SERVICE /okular org.kde.okular.documentMetaData "Title"
qdbus6 $SERVICE /okular org.kde.okular.documentMetaData "Author"
qdbus6 $SERVICE /okular org.kde.okular.documentMetaData "Subject"
qdbus6 $SERVICE /okular org.kde.okular.documentMetaData "Creator"
qdbus6 $SERVICE /okular org.kde.okular.documentMetaData "Producer"
qdbus6 $SERVICE /okular org.kde.okular.documentMetaData "CreationDate"
qdbus6 $SERVICE /okular org.kde.okular.documentMetaData "ModificationDate"
qdbus6 $SERVICE /okular org.kde.okular.documentMetaData "Keywords"
```

### Editor Integration

```bash
# Set external editor command (for TeX synctex)
qdbus6 $SERVICE /okular org.kde.okular.setEditorCmd "kate %f -l %l"
```

### Preferences

```bash
# Open preferences dialog
qdbus6 $SERVICE /okular org.kde.okular.slotPreferences
```

## Shell Interface (`/okularshell`)

Higher-level operations for the shell/application level.

```bash
# Open document with return value
qdbus6 $SERVICE /okularshell org.kde.okular.openDocument "file:///path/to/doc.pdf"

# Open with serialized options
qdbus6 $SERVICE /okularshell org.kde.okular.openDocument "file:///path/to/doc.pdf" "page=5"

# Check if can open more documents
qdbus6 $SERVICE /okularshell org.kde.okular.canOpenDocs 1 0

# Raise window (with startup ID for proper activation)
qdbus6 $SERVICE /okularshell org.kde.okular.tryRaise ""
```

## Window Interface (`/okular/okular__Shell_1`)

Full KDE MainWindow interface with actions, toolbars, and window control.

### Action System (`org.kde.KMainWindow`)

```bash
SHELL="/okular/okular__Shell_1"

# List all available actions
qdbus6 $SERVICE $SHELL org.kde.KMainWindow.actions

# Check if action is enabled
qdbus6 $SERVICE $SHELL org.kde.KMainWindow.actionIsEnabled file_print

# Get action tooltip/description
qdbus6 $SERVICE $SHELL org.kde.KMainWindow.actionToolTip file_print

# Activate an action (trigger it)
qdbus6 $SERVICE $SHELL org.kde.KMainWindow.activateAction file_print

# Enable/disable actions
qdbus6 $SERVICE $SHELL org.kde.KMainWindow.enableAction file_print
qdbus6 $SERVICE $SHELL org.kde.KMainWindow.disableAction file_print

# Grab window to clipboard
qdbus6 $SERVICE $SHELL org.kde.KMainWindow.grabWindowToClipBoard

# Get window ID
qdbus6 $SERVICE $SHELL org.kde.KMainWindow.winId
```

### Available Actions

| Action | Description |
|--------|-------------|
| `open_kcommand_bar` | Find Action... (Ctrl+Alt+I) |
| `file_open` | Open an existing document |
| `file_open_recent` | Open recent file |
| `file_print` | Print document |
| `file_close` | Close document |
| `file_quit` | Quit application |
| `options_show_menubar` | Show or hide menubar |
| `fullscreen` | Display in full screen |
| `tab-next` | Next Tab |
| `tab-previous` | Previous Tab |
| `undo-close-tab` | Undo close tab |
| `okular_lock_sidebar` | Lock Sidebar |
| `options_configure_keybinding` | Configure Keyboard Shortcuts |
| `options_configure_toolbars` | Configure Toolbars |
| `help_contents` | Help contents |
| `help_about_app` | About Okular |
| `help_donate` | Donate |

### Toolbar Control (`org.kde.okular.KXmlGuiWindow`)

```bash
# List available toolbars
qdbus6 $SERVICE $SHELL org.kde.okular.KXmlGuiWindow.toolBars
# Returns: mainToolBar, annotationToolBar, quickAnnotationToolBar

# Check toolbar visibility
qdbus6 $SERVICE $SHELL org.kde.okular.KXmlGuiWindow.isToolBarVisible mainToolBar

# Show/hide toolbar
qdbus6 $SERVICE $SHELL org.kde.okular.KXmlGuiWindow.setToolBarVisible annotationToolBar true
qdbus6 $SERVICE $SHELL org.kde.okular.KXmlGuiWindow.setToolBarVisible annotationToolBar false

# Configure toolbars dialog
qdbus6 $SERVICE $SHELL org.kde.okular.KXmlGuiWindow.configureToolbars
```

### Window Caption (`org.kde.okular.KMainWindow`)

```bash
# Set window title
qdbus6 $SERVICE $SHELL org.kde.okular.KMainWindow.setCaption "My Document"
qdbus6 $SERVICE $SHELL org.kde.okular.KMainWindow.setCaption "My Document" true  # modified flag

# Set plain caption (no app name suffix)
qdbus6 $SERVICE $SHELL org.kde.okular.KMainWindow.setPlainCaption "Custom Title"
```

### Window Control (`org.qtproject.Qt.QWidget`)

```bash
# Get window title
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.windowTitle

# Get window geometry (x, y, width, height)
qdbus6 --literal $SERVICE $SHELL org.qtproject.Qt.QWidget.geometry

# Window visibility
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.show
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.hide
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.showMinimized
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.showMaximized
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.showFullScreen
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.showNormal

# Close window
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.close

# Window state queries
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.minimized
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.maximized
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.fullScreen
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.isActiveWindow

# Focus control
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.setFocus
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.raise

# Window properties
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.width
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.height
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.x
qdbus6 $SERVICE $SHELL org.qtproject.Qt.QWidget.y
```

## Method Reference

### `/okular` Interface

| Method | Description |
|--------|-------------|
| `openDocument(path)` | Open a document |
| `reload()` | Reload current document |
| `currentDocument()` | Get current document path |
| `pages()` | Get total page count |
| `currentPage()` | Get current page (0-indexed) |
| `goToPage(n)` | Navigate to page n |
| `slotNextPage()` | Go to next page |
| `slotPreviousPage()` | Go to previous page |
| `slotGotoFirst()` | Go to first page |
| `slotGotoLast()` | Go to last page |
| `slotFind()` | Open find dialog |
| `slotPreferences()` | Open preferences |
| `slotTogglePresentation()` | Toggle presentation mode |
| `slotToggleChangeColors()` | Toggle color inversion |
| `slotSetChangeColors(bool)` | Set color inversion state |
| `slotPrintPreview()` | Open print preview |
| `slotOpenContainingFolder()` | Open file's folder |
| `documentMetaData(key)` | Get document metadata |
| `enableStartWithPrint()` | Print on open |
| `enableExitAfterPrint()` | Exit after printing |
| `enableStartWithFind(text)` | Find text on open |
| `setEditorCmd(cmd)` | Set external editor |

### `/okularshell` Interface

| Method | Description |
|--------|-------------|
| `openDocument(url)` | Open document (returns bool) |
| `openDocument(url, options)` | Open with options |
| `canOpenDocs(num, desktop)` | Check if can open more |
| `tryRaise(startupId)` | Raise window |

## Command-Line Interface

```bash
# Open a document
okular document.pdf

# Open at specific page
okular --page 10 document.pdf

# Start in presentation mode
okular --presentation document.pdf

# Print and exit
okular --print document.pdf

# Open with find
okular --find "search term" document.pdf

# Unique instance (reuse existing window)
okular --unique document.pdf
```

## AppMesh Integration

```php
// Helper to get Okular service name
function okularService(): ?string {
    $pid = trim(shell_exec('pgrep -o okular 2>/dev/null'));
    return $pid ? "org.kde.okular-$pid" : null;
}

// Open a PDF
$service = okularService();
if ($service) {
    appmesh_dbus_call($service, '/okular', 'org.kde.okular.openDocument',
          ['file:///home/user/document.pdf']);
}

// Get document info
$doc = appmesh_dbus_call($service, '/okular', 'org.kde.okular.currentDocument');
$pages = appmesh_dbus_call($service, '/okular', 'org.kde.okular.pages');
$current = appmesh_dbus_call($service, '/okular', 'org.kde.okular.currentPage');

// Navigate
appmesh_dbus_call($service, '/okular', 'org.kde.okular.goToPage', ['5']);

// Trigger an action
appmesh_dbus_call($service, '/okular/okular__Shell_1',
    'org.kde.KMainWindow.activateAction', ['file_print']);

// Control window
appmesh_dbus_call($service, '/okular/okular__Shell_1',
    'org.qtproject.Qt.QWidget.showFullScreen');

// Toggle toolbar
appmesh_dbus_call($service, '/okular/okular__Shell_1',
    'org.kde.okular.KXmlGuiWindow.setToolBarVisible', ['annotationToolBar', 'true']);
```

## Supported Formats

Okular supports many document formats:

- PDF, PostScript, DjVu
- EPUB, FictionBook, Mobipocket
- TIFF, CHM, ComicBook (CBR/CBZ)
- XPS, OpenDocument (ODP)
- Markdown, plain text

## Notes

- Page numbers are 0-indexed in D-Bus (page 1 = goToPage(0))
- `--unique` flag is useful for scripting (reuses existing instance)
- Editor integration (`setEditorCmd`) enables LaTeX synctex support
- Presentation mode is great for automation (PDF slideshows)
- The Shell_1 path contains the KDE MainWindow interface for full window control
- Actions can be triggered programmatically via `activateAction`
- Multiple tabs are supported - use `tab-next`/`tab-previous` actions

## Comparison

| Feature | Okular | Gwenview | Haruna |
|---------|--------|----------|--------|
| D-Bus interface | Excellent | None | MPRIS |
| Document navigation | Yes | N/A | N/A |
| Page queries | Yes | N/A | N/A |
| Action system | Yes | No | No |
| Toolbar control | Yes | No | No |
| Window control | Yes | No | Limited |
| Presentation mode | Yes | No | N/A |
| Metadata access | Yes | No | Yes |
| Automation-friendly | Excellent | Poor | Good |

Okular provides the most comprehensive D-Bus interface of any KDE document viewer.
