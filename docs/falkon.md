# Falkon D-Bus Integration

Falkon is KDE's QtWebEngine-based browser. Its D-Bus interface is **minimal** - just a single-instance message passthrough for CLI arguments.

## Service

```bash
# Service appears when Falkon is running
qdbus6 | grep Falkon
# Returns: org.kde.Falkon
```

## Interface

Only one object path with `QtSingleApplication` interface:

```bash
qdbus6 org.kde.Falkon /
# method void org.kde.QtSingleApplication.SendMessage(QString message)
# signal void org.kde.QtSingleApplication.messageReceived(QString message)
```

## Available Commands

SendMessage accepts the same arguments as the CLI:

```bash
# Open URL in new tab
qdbus6 org.kde.Falkon / org.kde.QtSingleApplication.SendMessage \
  "--new-tab https://example.com"

# Open URL (creates new tab or uses empty tab)
qdbus6 org.kde.Falkon / org.kde.QtSingleApplication.SendMessage \
  "https://httpbin.org/get"

# Navigate current tab
qdbus6 org.kde.Falkon / org.kde.QtSingleApplication.SendMessage \
  "--current-tab https://example.com"

# Open URL in new window
qdbus6 org.kde.Falkon / org.kde.QtSingleApplication.SendMessage \
  "--new-window https://example.com"

# Open download manager
qdbus6 org.kde.Falkon / org.kde.QtSingleApplication.SendMessage \
  "--download-manager"

# Toggle fullscreen
qdbus6 org.kde.Falkon / org.kde.QtSingleApplication.SendMessage \
  "--fullscreen"

# Start private browsing window
qdbus6 org.kde.Falkon / org.kde.QtSingleApplication.SendMessage \
  "--private-browsing"
```

## Limitations

Falkon's D-Bus interface **cannot**:
- Fetch page content
- Read current URL or tab info
- Execute JavaScript
- Access DOM
- Get page source
- Control navigation (back/forward/reload)
- List or manage tabs
- Access bookmarks or history

It's purely for **launching URLs** and **toggling UI states**.

## Plugins

Falkon has plugins but none expose D-Bus interfaces:
- AutoScroll
- FlashCookieManager
- GreaseMonkey (userscripts)
- KDEFrameworksIntegration (KWallet, KIO)
- MouseGestures
- PIM (KDE contacts integration)
- PyFalkon (Python scripting)
- StatusBarIcons

The **PyFalkon** plugin allows Python scripting inside Falkon, but scripts run within the browser context, not accessible via D-Bus.

## Comparison

| Browser | D-Bus Capability |
|---------|-----------------|
| Falkon | Open URLs only |
| Firefox | Open URLs only |
| Chrome | None (use browser integration) |

## For InterAPP

Falkon is **not suitable** for D-Bus-based HTTP fetching. Use:
- `kioclient cat https://...` for HTTP content
- Direct HTTP libraries in your coordinator (PHP curl, Python requests)

Falkon is useful only for:
- Opening URLs for user viewing
- Launching private browsing sessions
- Triggering download manager
