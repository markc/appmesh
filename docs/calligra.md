# Calligra Integration

KDE's office suite with D-Bus support. Includes Words (word processor), Sheets (spreadsheet), and Stage (presentations).

## D-Bus Status

**Good D-Bus interface.** Each Calligra app registers as `org.kde.calligra<app>-<PID>` with document and window management capabilities.

```bash
# Find running instances
qdbus6 | grep calligra
# org.kde.calligrawords-549318
# org.kde.calligrasheets-549854
```

## Common Interface (All Apps)

Service: `org.kde.calligra<app>-<PID>`

### Application Level

Path: `/application`
Interface: `org.kde.calligra.application`

```bash
SERVICE="org.kde.calligrawords-$(pgrep -o calligrawords)"

# Get open documents
qdbus6 $SERVICE /application org.kde.calligra.application.getDocuments

# Get views
qdbus6 $SERVICE /application org.kde.calligra.application.getViews

# Get windows
qdbus6 $SERVICE /application org.kde.calligra.application.getWindows
```

**Signals:**
- `documentOpened(QString ref)` - Emitted when document opens
- `documentClosed(QString ref)` - Emitted when document closes

### Main Window

Path: `/calligra<app>/MainWindow_1`
Interface: `org.kde.calligra<app>.KoMainWindow`

```bash
# File operations
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.slotFileNew
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.slotFileOpen
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.slotFileSave
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.slotFileSaveAs
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.slotFileClose
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.slotFileQuit

# Save with options
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.saveDocument false  # save
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.saveDocument true   # save as

# Printing
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.slotFilePrint
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.slotFilePrintPreview

# Export/Import
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.slotExportFile
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.slotImportFile

# View
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.viewFullscreen true

# Document info
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.slotDocumentInfo

# Reload
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.slotReloadFile

# Email document
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.slotEmailFile

# Encrypt document
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.calligrawords.KoMainWindow.slotEncryptDocument
```

### Action System

All menu actions are accessible via KMainWindow interface:

```bash
# List all actions
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.KMainWindow.actions

# Trigger action
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.KMainWindow.activateAction "file_save"

# Check if action is enabled
qdbus6 $SERVICE /calligrawords/MainWindow_1 org.kde.KMainWindow.actionIsEnabled "file_save"
```

## Calligra Sheets Specific

### Sheet Management

Path: `/document_0/Map`
Interface: `org.kde.calligra.spreadsheet.map`

```bash
SERVICE="org.kde.calligrasheets-$(pgrep -o calligrasheets)"

# Get sheet count
qdbus6 $SERVICE /document_0/Map org.kde.calligra.spreadsheet.map.sheetCount

# Get sheet names
qdbus6 $SERVICE /document_0/Map org.kde.calligra.spreadsheet.map.sheetNames

# Get sheet paths (for further D-Bus access)
qdbus6 $SERVICE /document_0/Map org.kde.calligra.spreadsheet.map.sheets

# Insert new sheet
qdbus6 $SERVICE /document_0/Map org.kde.calligra.spreadsheet.map.insertSheet "NewSheet"

# Get sheet by name
qdbus6 $SERVICE /document_0/Map org.kde.calligra.spreadsheet.map.sheet "Sheet1"

# Get sheet by index
qdbus6 $SERVICE /document_0/Map org.kde.calligra.spreadsheet.map.sheetByIndex 0
```

## Available Methods Summary

### Common (All Apps)

| Method | Description |
|--------|-------------|
| `getDocuments()` | List open documents |
| `getViews()` | List document views |
| `getWindows()` | List windows |
| `slotFileNew()` | New document |
| `slotFileOpen()` | Open file dialog |
| `slotFileSave()` | Save document |
| `slotFileSaveAs()` | Save as dialog |
| `slotFilePrint()` | Print dialog |
| `slotExportFile()` | Export dialog |
| `slotImportFile()` | Import dialog |
| `saveDocument(saveas, silent)` | Programmatic save |
| `viewFullscreen(bool)` | Toggle fullscreen |
| `activateAction(name)` | Trigger menu action |

### Sheets Specific

| Method | Description |
|--------|-------------|
| `sheetCount()` | Number of sheets |
| `sheetNames()` | List of sheet names |
| `sheets()` | Sheet object paths |
| `insertSheet(name)` | Create new sheet |
| `sheet(name)` | Get sheet by name |
| `sheetByIndex(n)` | Get sheet by index |

## Command-Line Interface

```bash
# Open document
calligrawords document.odt
calligrasheets spreadsheet.ods
calligrastage presentation.odp

# Calligra launcher (choose app)
calligra
```

## DAWN Integration

```php
// Helper to get Calligra service
function calligraService(string $app): ?string {
    $pid = trim(shell_exec("pgrep -o calligra$app 2>/dev/null"));
    return $pid ? "org.kde.calligra$app-$pid" : null;
}

// Save document in Words
$service = calligraService('words');
if ($service) {
    qdbus($service, '/calligrawords/MainWindow_1',
          'org.kde.calligrawords.KoMainWindow.saveDocument', ['false']);
}

// Get sheet count in Sheets
$service = calligraService('sheets');
if ($service) {
    $count = qdbus($service, '/document_0/Map',
                   'org.kde.calligra.spreadsheet.map.sheetCount');
}

// Trigger any action
qdbus($service, '/calligrawords/MainWindow_1',
      'org.kde.KMainWindow.activateAction', ['file_print']);
```

## Signals

Monitor Calligra events:

```bash
# Watch for document open/close
dbus-monitor "interface='org.kde.calligra.application'"
```

## Limitations

- No direct cell read/write in Sheets (sheet management only)
- No text manipulation in Words (file operations only)
- Document must be open for document-specific methods
- Lost Kross scripting in 4.0 Qt6 port

## Comparison with LibreOffice

| Feature | Calligra | LibreOffice |
|---------|----------|-------------|
| D-Bus interface | Yes | No |
| Remote control | Basic | No (UNO only) |
| Sheet management | Yes | No |
| Cell operations | No | Via UNO |
| Action system | Yes | No |
| Scripting | Limited | Extensive (UNO) |

Calligra offers D-Bus for basic automation. For deep document manipulation, LibreOffice's UNO API is more powerful but requires different approach.

## Notes

- Service names include PID - must discover dynamically
- Document paths (`/document_0`) increment with each open document
- Sheets lost its scripting system in 4.0, but D-Bus remains
- Action system means most UI operations are accessible
- Good for workflow automation, less so for data manipulation
