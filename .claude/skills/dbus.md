# D-Bus Desktop Automation

<skill>
name: dbus
description: Control desktop applications via D-Bus - clipboard, notifications, screenshots, window management
user-invocable: true
arguments: <action> [target] [options]
</skill>

## Actions

- `clipboard` - Get or set clipboard contents
- `notify <title> [body]` - Send desktop notification
- `screenshot [mode]` - Take screenshot (fullscreen|activewindow|region)
- `windows` - List all open windows
- `focus <window-id>` - Focus a window by ID
- `list [service]` - List D-Bus services or introspect one
- `call <service> <path> <method> [args...]` - Call any D-Bus method

## Examples

```bash
/dbus clipboard                    # Get clipboard
/dbus notify "Build complete"      # Send notification
/dbus screenshot activewindow      # Screenshot current window
/dbus windows                      # List windows
/dbus focus {uuid}                 # Focus window
/dbus list org.kde.Dolphin         # Introspect Dolphin's D-Bus API
```

## Instructions

When the user invokes this skill:

1. **For `clipboard`**: Use `appmesh_clipboard_get` or `appmesh_clipboard_set`
2. **For `notify`**: Use `appmesh_notify` with title and optional body
3. **For `screenshot`**: Use `appmesh_screenshot` with mode (default: fullscreen)
4. **For `windows`**: Use `appmesh_kwin_list_windows`
5. **For `focus`**: Use `appmesh_kwin_activate_window`
6. **For `list`**: Use `appmesh_dbus_list` to discover services/methods
7. **For `call`**: Use `appmesh_dbus_call` with service, path, method, args

## Common D-Bus Services

| Service | Purpose |
|---------|---------|
| `org.kde.klipper` | Clipboard manager |
| `org.kde.Spectacle` | Screenshots |
| `org.kde.KWin` | Window manager |
| `org.kde.StatusNotifierWatcher` | System tray |
| `org.kde.kglobalaccel` | Global shortcuts |
| `org.freedesktop.Notifications` | Desktop notifications |
