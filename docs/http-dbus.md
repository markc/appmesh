# HTTP/HTTPS via D-Bus on Linux

There is **no dedicated D-Bus HTTP client service** in the standard Linux desktop stack. HTTP requests are typically handled by applications directly, not exposed as system services.

## Available Options

### 1. kioclient (Recommended for KDE)

KDE's I/O client - CLI tool that uses KIO workers for network-transparent file operations.

```bash
# Fetch HTTP content
kioclient --noninteractive cat "https://httpbin.org/get"

# Download to local file
kioclient --noninteractive copy "https://example.com/file.zip" "/tmp/file.zip"

# Get resource info (headers)
kioclient --noninteractive stat "https://example.com"

# List directory (for FTP, WebDAV, etc.)
kioclient --noninteractive ls "ftp://ftp.example.com/"
```

**Advantages:**
- Integrates with KDE's password manager (KWallet)
- Supports many protocols: http, https, ftp, sftp, smb, webdav, etc.
- Uses KDE's proxy settings
- User-Agent: `KIO/6.22 kioworker/6.22.0`

**Note:** kioclient is a CLI tool, not a D-Bus service. It spawns KIO workers internally.

---

### 2. Firefox D-Bus Interface

Firefox exposes a minimal D-Bus interface for remote control.

```bash
# Service name includes base64-encoded profile path
qdbus6 org.mozilla.firefox.* /org/mozilla/firefox/Remote

# Open URL in Firefox
qdbus6 org.mozilla.firefox.L2hvbWUv... /org/mozilla/firefox/Remote \
  org.mozilla.firefox.OpenURL "https://example.com"
```

**Limitation:** Can only open URLs in browser, cannot fetch content programmatically.

---

### 3. Freedesktop Portals

The XDG Desktop Portal provides some network-related functionality:

```bash
# Check if host is reachable
qdbus6 org.freedesktop.portal.Desktop /org/freedesktop/portal/desktop \
  org.freedesktop.portal.NetworkMonitor.CanReach "httpbin.org" 443
# Returns: true/false

# Get network status
qdbus6 org.freedesktop.portal.Desktop /org/freedesktop/portal/desktop \
  org.freedesktop.portal.NetworkMonitor.GetAvailable

# Lookup proxy settings for URL
qdbus6 org.freedesktop.portal.Desktop /org/freedesktop/portal/desktop \
  org.freedesktop.portal.ProxyResolver.Lookup "https://example.com"
# Returns: "direct://" or proxy URL

# Open URL in default browser
qdbus6 org.freedesktop.portal.Desktop /org/freedesktop/portal/desktop \
  org.freedesktop.portal.OpenURI.OpenURI "" "https://example.com" '{}'
```

**Limitation:** No content fetching, only network status and URL opening.

---

### 4. KIO D-Bus Services

KIO has supporting D-Bus services but no direct HTTP interface:

```bash
# Password server (stores HTTP auth credentials)
qdbus6 org.kde.kiod6 /modules/kpasswdserver

# Job view server (tracks ongoing transfers)
qdbus6 org.kde.plasmashell /JobViewServer
```

---

### 5. Browsers Not Installed

**Falkon** (KDE's browser) and **Konqueror** are available but not installed:
```bash
pacman -S falkon     # QtWebEngine browser
pacman -S konqueror  # KDE file manager + browser
```

These would expose standard KDE D-Bus interfaces but likely still no HTTP fetch API.

---

## Why No D-Bus HTTP Service?

1. **Security** - Exposing HTTP fetch over D-Bus would allow any app to make requests
2. **Complexity** - HTTP has many options (headers, auth, cookies, redirects, etc.)
3. **Existing tools** - curl/wget work well for CLI, libraries exist for apps
4. **Sandboxing** - Modern apps use portals for controlled external access

---

## Practical Solutions for InterAPP

### Option A: Use kioclient
```bash
# Simple fetch
content=$(kioclient --noninteractive cat "https://api.example.com/data")
echo "$content" | jq .
```

### Option B: Create a D-Bus HTTP Service

A custom service could wrap libcurl and expose fetch operations:

```python
# Hypothetical service (would need to be created)
# org.interapp.HttpClient /HttpClient
#   method Fetch(url: string, options: dict) -> (status: int, body: string)
#   method Post(url: string, data: string, options: dict) -> (status: int, body: string)
```

### Option C: Use PHP as Bridge

Since InterAPP aims to use PHP as coordinator:
```php
<?php
// PHP can make HTTP requests directly
$response = file_get_contents('https://api.example.com/data');

// And communicate results via D-Bus (with php-dbus extension)
$dbus = new Dbus(Dbus::BUS_SESSION);
// ...
```

---

## Related Services

| Service | Purpose |
|---------|---------|
| `org.kde.kiod6` | KIO daemon (auth, exec) |
| `org.kde.kioexecd6` | KIO executable handler |
| `org.freedesktop.NetworkManager` | Network configuration (system bus) |
| `org.freedesktop.portal.NetworkMonitor` | Network status for sandboxed apps |
| `org.kde.plasma.browser_integration` | Browser tab/history for KRunner |

---

## Conclusion

For the InterAPP project, the most practical approach is:

1. **Use kioclient** for simple HTTP operations (integrates with KDE ecosystem)
2. **Use curl/wget** for complex HTTP needs (more control)
3. **Build a custom D-Bus service** if D-Bus-native HTTP is essential
4. **Use PHP directly** since it's the planned coordinator and has excellent HTTP support
