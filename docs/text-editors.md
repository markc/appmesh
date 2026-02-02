# Plasma Text Editor D-Bus Comparison

Summary of D-Bus text editing capabilities across KDE/Plasma applications.

## Quick Comparison

| App | Open File | Inject Text | Read Text | Edit Text | Status |
|-----|-----------|-------------|-----------|-----------|--------|
| **Kate** | ✅ | ✅ `openInput()` | ❌ | ❌ | Best for files |
| **KWrite** | ✅ | ✅ `openInput()` | ❌ | ❌ | Same as Kate |
| **Konsole** | N/A | ✅ `sendText()` | ✅ `getAllDisplayedText()` | ❌ | Best for text I/O |
| **Ghostwriter** | ❌ | ❌ | ❌ | ❌ | No D-Bus |

## Kate / KWrite

Both use the same `org.kde.Kate.Application` interface.

**Can do:**
- Open files: `openUrl("file:///path", "")`
- Inject text (creates new doc): `openInput("text content", "")`
- Position cursor: `setCursor(line, column)`
- Navigate: `tokenOpenUrlAt(url, line, col, "", false)`
- Window control: activate, minimize, sessions

**Cannot do:**
- Read document content
- Modify existing text
- Select text
- Copy/paste within document
- Find/replace

**Best for:** Opening files, creating documents from generated text.

## Konsole

Has the richest text I/O via `org.kde.konsole.Session` interface.

**Can do:**
- Send text to terminal: `sendText("text")`
- Run commands: `runCommand("ls -la")`
- Read terminal content: `getAllDisplayedText()`
- Read specific lines: `getDisplayedText(startLine, endLine)`
- Monitor activity/silence

**Cannot do (by default):**
- Security-sensitive APIs are disabled by default

**To enable:** Add to `~/.config/konsolerc`:
```ini
[KonsoleWindow]
EnableSecuritySensitiveDBusAPI=true
```

**Best for:** Text I/O, command execution, reading output.

## Recommendations

### For injecting text into an editor
Use **Kate** with `openInput()`:
```bash
KATE=$(qdbus6 | grep kate | head -1)
qdbus6 $KATE /MainApplication org.kde.Kate.Application.openInput "Your text here" ""
```

### For reading/writing terminal text
Use **Konsole** (after enabling D-Bus API):
```bash
KONSOLE=$(qdbus6 | grep konsole | head -1)
# Send text
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.sendText "echo hello"
# Read output
qdbus6 $KONSOLE /Sessions/1 org.kde.konsole.Session.getAllDisplayedText true
```

### For file-based workflow
Write to temp file, open in Kate:
```bash
echo "Content" > /tmp/doc.md
qdbus6 $KATE /MainApplication org.kde.Kate.Application.openUrl "file:///tmp/doc.md" ""
```

## Why Not Full Text Editing?

Kate/KWrite use the **KTextEditor** framework internally which has full text manipulation APIs, but these are designed for:
- C++/Qt applications
- Kate plugins
- Direct library usage

The D-Bus interface intentionally exposes only:
- Document/file operations
- Navigation
- Window management

This is a design choice, not a limitation - D-Bus is for IPC, not as a full editor API.

## Alternative Approaches

1. **Clipboard bridge**: Use Klipper + keyboard simulation
2. **File-based**: Write content to file, open in editor
3. **Kate plugin**: For deep integration, write a Kate plugin
4. **Konsole**: For terminal-based text I/O
5. **ydotool/wtype**: Simulate keyboard input (Wayland)

## Service Discovery

```bash
# Find running editors
qdbus6 | grep -E 'kate|kwrite|konsole'

# Example output:
# org.kde.kate-12345
# org.kde.kwrite-23456
# org.kde.konsole-34567
```

Services include PID, so they change each launch.
