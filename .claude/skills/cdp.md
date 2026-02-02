# Chrome DevTools Protocol Control

<skill>
name: cdp
description: Control Electron apps (VS Code, Discord, Slack, Obsidian) via Chrome DevTools Protocol
user-invocable: true
arguments: <action> [target] [options]
</skill>

## Actions

- `list [port]` - List debugging targets (tabs, pages)
- `version [port]` - Get browser/app version info
- `eval <js> [port]` - Execute JavaScript in app
- `screenshot [port]` - Take screenshot of app
- `navigate <url> [port]` - Navigate to URL
- `click <selector> [port]` - Click element by CSS selector

## Prerequisites

Launch Electron app with debugging enabled:

```bash
# VS Code
code --remote-debugging-port=9222

# Discord
discord --remote-debugging-port=9223

# Slack
slack --remote-debugging-port=9224

# Obsidian
obsidian --remote-debugging-port=9225

# Any Electron app
/path/to/app --remote-debugging-port=PORT
```

## Examples

```bash
/cdp list                          # List targets on port 9222
/cdp list 9223                     # List Discord targets
/cdp version                       # Get VS Code version
/cdp eval "document.title"         # Get page title
/cdp eval "window.location.href"   # Get current URL
/cdp screenshot                    # Take screenshot
/cdp navigate "https://example.com"
/cdp click "#my-button"
```

## Instructions

When the user invokes this skill:

1. **For `list`**: Use `appmesh_cdp_list` with optional port
2. **For `version`**: Use `appmesh_cdp_version`
3. **For `eval`**: Use `appmesh_cdp_eval` with expression
4. **For `screenshot`**: Use `appmesh_cdp_screenshot`
5. **For `navigate`**: Use `appmesh_cdp_navigate` with URL
6. **For `click`**: Use `appmesh_cdp_click` with CSS selector

## Common Target Ports

| App | Suggested Port |
|-----|---------------|
| VS Code | 9222 |
| Discord | 9223 |
| Slack | 9224 |
| Obsidian | 9225 |
| Chrome/Chromium | 9222 |

## Requirements

Install `websocat` for WebSocket communication:

```bash
# Arch/CachyOS
paru -S websocat

# Or via cargo
cargo install websocat
```

## Troubleshooting

**"Cannot connect"**: App not running with `--remote-debugging-port`

**"No targets"**: App may need a window open, or check port number

**"No WebSocket URL"**: Target may be a service worker or background page
