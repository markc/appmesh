# Kate D-Bus Plugin Proposal

## Goal

Create a Kate/KTextEditor plugin that exposes full text editing capabilities via D-Bus, enabling external control of document content from Claude Code, scripts, and other applications.

## Current Limitation

Kate's built-in D-Bus interface (`org.kde.Kate.Application`) only exposes:
- File operations (open, close)
- Navigation (cursor position)
- Window management

**Missing:** Reading/writing document content, text manipulation, selection, find/replace.

## Proposed Solution

A KTextEditor plugin that:
1. Registers a new D-Bus interface (e.g., `org.kde.Kate.TextEditor`)
2. Exposes KTextEditor::Document methods via D-Bus
3. Works with Kate, KWrite, KDevelop, and any KTextEditor-based app

## Technical Feasibility: HIGH

### Why It's Feasible

1. **KTextEditor has the APIs** - Full text manipulation already exists:
   - `document->text()` - Get all content
   - `document->setText(QString)` - Replace all content
   - `document->insertText(Cursor, QString)` - Insert at position
   - `document->removeText(Range)` - Delete range
   - `document->replaceText(Range, QString)` - Replace range
   - Selection, undo/redo, and more

2. **Qt D-Bus is straightforward** - Register object with:
   ```cpp
   QDBusConnection::sessionBus().registerObject(
       "/TextEditor",
       this,
       QDBusConnection::ExportAllSlots
   );
   ```

3. **Kate plugin system is mature** - Well-documented, actively maintained

4. **Precedent exists** - Kate already exposes some D-Bus, just not text content

## Proposed D-Bus Interface

### Service: `org.kde.kate-{PID}` (existing)
### New Path: `/TextEditor`
### Interface: `org.kde.Kate.TextEditor`

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `getText` | - | QString | Get entire document |
| `setText` | QString text | bool | Replace document content |
| `getSelectedText` | - | QString | Get selection |
| `insertText` | int line, int col, QString text | bool | Insert at position |
| `removeText` | int startLine, int startCol, int endLine, int endCol | bool | Delete range |
| `replaceText` | int startLine, int startCol, int endLine, int endCol, QString text | bool | Replace range |
| `selectText` | int startLine, int startCol, int endLine, int endCol | bool | Set selection |
| `selectAll` | - | bool | Select all |
| `getCursor` | - | (int, int) | Get cursor position |
| `setCursor` | int line, int col | bool | Move cursor |
| `getLineCount` | - | int | Number of lines |
| `getLine` | int line | QString | Get specific line |
| `undo` | - | bool | Undo last action |
| `redo` | - | bool | Redo last action |
| `find` | QString pattern, bool regex, bool caseSensitive | (int, int) | Find text |
| `replace` | QString find, QString replace, bool all | int | Replace occurrences |
| `getHighlightingMode` | - | QString | Syntax highlighting mode |
| `setHighlightingMode` | QString mode | bool | Set highlighting |

### Signals

| Signal | Parameters | Description |
|--------|------------|-------------|
| `textChanged` | - | Content modified |
| `cursorPositionChanged` | int line, int col | Cursor moved |
| `selectionChanged` | - | Selection changed |

## Implementation Outline

### File Structure
```
kate-dbus-text-plugin/
├── CMakeLists.txt
├── plugin.json
├── plugin.h
├── plugin.cpp
├── texteditoradaptor.h      # D-Bus adaptor
├── texteditoradaptor.cpp
└── org.kde.Kate.TextEditor.xml  # D-Bus interface definition
```

### Core Plugin Class
```cpp
#include <KTextEditor/Plugin>
#include <KTextEditor/Document>
#include <KTextEditor/View>
#include <KTextEditor/MainWindow>
#include <QDBusConnection>

class KateDBusTextPlugin : public KTextEditor::Plugin {
    Q_OBJECT
public:
    explicit KateDBusTextPlugin(QObject *parent, const QVariantList &);
    QObject *createView(KTextEditor::MainWindow *mainWindow) override;
};

class KateDBusTextView : public QObject, public KXMLGUIClient {
    Q_OBJECT
    Q_CLASSINFO("D-Bus Interface", "org.kde.Kate.TextEditor")

public:
    explicit KateDBusTextView(KTextEditor::MainWindow *mainWindow);

public Q_SLOTS:
    // D-Bus exposed methods
    QString getText();
    bool setText(const QString &text);
    QString getSelectedText();
    bool insertText(int line, int col, const QString &text);
    // ... etc

private:
    KTextEditor::MainWindow *m_mainWindow;
    KTextEditor::Document *activeDocument();
};
```

### D-Bus Registration
```cpp
KateDBusTextView::KateDBusTextView(KTextEditor::MainWindow *mainWindow)
    : m_mainWindow(mainWindow)
{
    // Register on session bus
    QDBusConnection::sessionBus().registerObject(
        QStringLiteral("/TextEditor"),
        this,
        QDBusConnection::ExportAllSlots | QDBusConnection::ExportAllSignals
    );
}

QString KateDBusTextView::getText()
{
    auto doc = activeDocument();
    return doc ? doc->text() : QString();
}

bool KateDBusTextView::setText(const QString &text)
{
    auto doc = activeDocument();
    return doc ? doc->setText(text) : false;
}
```

## Build Requirements

- CMake 3.16+
- Qt 6 (Core, Widgets, DBus)
- KDE Frameworks 6 (KTextEditor, KCoreAddons, KI18n, KXmlGui)
- Extra CMake Modules (ECM)

```cmake
find_package(Qt6 REQUIRED COMPONENTS Core Widgets DBus)
find_package(KF6 REQUIRED COMPONENTS TextEditor CoreAddons I18n XmlGui)

kcoreaddons_add_plugin(katetextdbus
    SOURCES plugin.cpp texteditoradaptor.cpp
    INSTALL_NAMESPACE ktexteditor
)
target_link_libraries(katetextdbus
    KF6::TextEditor
    Qt6::DBus
)
```

## Development Effort Estimate

| Phase | Tasks | Complexity |
|-------|-------|------------|
| Setup | Build environment, plugin skeleton | Low |
| Core | getText, setText, cursor ops | Low |
| Text Ops | insert, remove, replace, selection | Medium |
| Find/Replace | Regex support, replace all | Medium |
| Testing | All editors (Kate, KWrite, KDevelop) | Medium |
| Polish | Error handling, documentation | Low |

**Total: 2-4 days for experienced Qt/KDE developer**

## Security Considerations

1. **Same as Konsole** - Could optionally require user confirmation
2. **Session bus only** - Not exposed system-wide
3. **Per-document tokens** - Could add authentication

## Benefits for AppMesh

1. **Full text control** - Read, write, edit any document
2. **Cross-editor** - Works with Kate, KWrite, KDevelop, Kile
3. **Native integration** - Uses KDE's own plugin system
4. **Scriptable** - Bash, Python, PHP can all use D-Bus
5. **AI-friendly** - Claude Code can fully manipulate documents

## Alternative: Kate Script

Kate also supports JavaScript-based scripts, but:
- Limited to Kate (not KTextEditor-wide)
- Harder to invoke externally
- Less integration with D-Bus

The plugin approach is more robust and universal.

## Next Steps

1. Set up KDE development environment
2. Create minimal plugin skeleton
3. Implement getText/setText first
4. Test with Kate
5. Expand to full API
6. Package for distribution

## Resources

- [Kate Plugin Tutorial](https://develop.kde.org/docs/apps/kate/plugin/)
- [KTextEditor API](https://api.kde.org/ktexteditor-index.html)
- [Qt D-Bus](https://doc.qt.io/qt-6/qtdbus-index.html)
- [Creating D-Bus Interfaces](https://develop.kde.org/docs/features/d-bus/creating_dbus_interfaces/)
- [Kate Source Code](https://github.com/KDE/kate)
- [KTextEditor Source](https://github.com/KDE/ktexteditor)
