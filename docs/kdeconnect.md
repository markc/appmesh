# KDE Connect Integration Notes

KDE Connect bridges your phone and desktop via D-Bus - messaging, file sharing, SMS, clipboard sync, and more.

## D-Bus Services

- `org.kde.kdeconnect` - Main service
- `org.kde.kdeconnect.daemon` - Daemon interface

## Device Discovery

### List Paired & Reachable Devices

```bash
# Get device IDs
qdbus6 org.kde.kdeconnect /modules/kdeconnect \
  org.kde.kdeconnect.daemon.devices true true

# Get device names (with --literal for map output)
qdbus6 --literal org.kde.kdeconnect /modules/kdeconnect \
  org.kde.kdeconnect.daemon.deviceNames true true

# Get device ID by name
qdbus6 org.kde.kdeconnect /modules/kdeconnect \
  org.kde.kdeconnect.daemon.deviceIdByName "Pixel 9 Pro XL"
```

### Device Path Pattern

```
/modules/kdeconnect/devices/<DEVICE_ID>/<plugin>
```

## Battery Status

```bash
DEVICE_ID="your-device-id"

# Get charge percentage
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/battery \
  org.kde.kdeconnect.device.battery.charge

# Check if charging
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/battery \
  org.kde.kdeconnect.device.battery.isCharging
```

## Ping

```bash
# Simple ping
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/ping \
  org.kde.kdeconnect.device.ping.sendPing

# Ping with custom message
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/ping \
  org.kde.kdeconnect.device.ping.sendPing "Hello from the terminal!"
```

## Share Content to Phone

```bash
# Share URL (opens on phone)
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/share \
  org.kde.kdeconnect.device.share.shareUrl "https://example.com"

# Share text
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/share \
  org.kde.kdeconnect.device.share.shareText "Some text to share"

# Share file (sends to phone)
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/share \
  org.kde.kdeconnect.device.share.openFile "/path/to/file.pdf"

# Share multiple URLs
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/share \
  org.kde.kdeconnect.device.share.shareUrls "['https://url1.com', 'https://url2.com']"
```

## Find My Phone

```bash
# Make phone ring
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/findmyphone \
  org.kde.kdeconnect.device.findmyphone.ring
```

## Clipboard

```bash
# Send current desktop clipboard to phone
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/clipboard \
  org.kde.kdeconnect.device.clipboard.sendClipboard
```

## SMS

### QUIRK: qdbus6 Can't Send SMS

`qdbus6` fails to marshal `QVariantList` for SMS. Use `gdbus` instead!

```bash
# WORKS - Send SMS with gdbus
gdbus call --session \
  --dest org.kde.kdeconnect \
  --object-path /modules/kdeconnect/devices/$DEVICE_ID/sms \
  --method org.kde.kdeconnect.device.sms.sendSms \
  "[<'+61400000000'>]" \
  "Your message here" \
  "[]"
```

Note the GVariant syntax: `[<'value'>]` for array of variants.

### WORKING: Use replyToConversation

The `sendSms` method is unreliable. Use `replyToConversation` instead with the thread ID:

```bash
gdbus call --session \
  --dest org.kde.kdeconnect \
  --object-path /modules/kdeconnect/devices/$DEVICE_ID \
  --method org.kde.kdeconnect.device.conversations.replyToConversation \
  <THREAD_ID> \
  "Your message here" \
  "[]"
```

**Finding the thread ID**: Parse `activeConversations` output - the thread ID is in the conversation data structure.

### Other SMS Operations (qdbus6 works)

```bash
# Launch SMS app
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/sms \
  org.kde.kdeconnect.device.sms.launchApp

# Request all conversations
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/sms \
  org.kde.kdeconnect.device.sms.requestAllConversations

# Get active conversations
qdbus6 --literal org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID \
  org.kde.kdeconnect.device.conversations.activeConversations
```

## Phone Storage (SFTP)

```bash
# Check if mounted
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/sftp \
  org.kde.kdeconnect.device.sftp.isMounted

# Mount and wait
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/sftp \
  org.kde.kdeconnect.device.sftp.mountAndWait

# Get mount point
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/sftp \
  org.kde.kdeconnect.device.sftp.mountPoint

# Open in file manager
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/sftp \
  org.kde.kdeconnect.device.sftp.startBrowsing

# Unmount
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/sftp \
  org.kde.kdeconnect.device.sftp.unmount
```

### KIO Access

Phone storage is also accessible via KIO:
```bash
kioclient5 ls "kdeconnect://<DEVICE_ID>/"
```

## Remote Input

```bash
# Move cursor (relative x,y)
# Note: Requires proper D-Bus tuple format
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/remotecontrol \
  org.kde.kdeconnect.device.remotecontrol.moveCursor "(100, 50)"
```

## Notifications

```bash
# List notification paths
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/notifications

# Get notification interface
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/notifications/<ID>
```

## Send Notification to Phone

From the daemon (not device):
```bash
qdbus6 org.kde.kdeconnect /modules/kdeconnect \
  org.kde.kdeconnect.daemon.sendSimpleNotification \
  "my-event-id" "Title" "Notification body text" "dialog-information"
```

## Available Plugins

Per device at `/modules/kdeconnect/devices/<ID>/`:

| Plugin | Description |
|--------|-------------|
| `battery` | Phone battery status |
| `clipboard` | Clipboard sync |
| `connectivity_report` | Network connectivity |
| `contacts` | Contact sync |
| `findmyphone` | Ring phone |
| `findthisdevice` | Ring this PC |
| `mprisremote` | Media player control |
| `notifications` | Phone notifications |
| `ping` | Send ping messages |
| `remotecontrol` | Remote mouse/keyboard |
| `remotekeyboard` | Keyboard input |
| `sftp` | Phone storage access |
| `share` | Share URLs/files |
| `sms` | SMS messaging |
| `telephony` | Call notifications |

## InterAPP Examples

### Screenshot to Phone

```bash
#!/bin/bash
DEVICE_ID="11d3f3d2f0f94c3bb6adabe1d31fad6b"
OUTFILE="/tmp/screenshot-$(date +%Y%m%d_%H%M%S).png"

# Take screenshot
spectacle -b -f -n -o "$OUTFILE"

# Send to phone
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/share \
  org.kde.kdeconnect.device.share.openFile "$OUTFILE"

echo "Screenshot sent to phone"
```

### Low Battery Alert Script

```bash
#!/bin/bash
DEVICE_ID="your-device-id"

CHARGE=$(qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/battery \
  org.kde.kdeconnect.device.battery.charge)

if [ "$CHARGE" -lt 20 ]; then
  notify-send "Phone Battery Low" "Phone is at ${CHARGE}%"
fi
```

### Quick Share from Clipboard

```bash
#!/bin/bash
DEVICE_ID="your-device-id"

# Get clipboard content
CLIP=$(xclip -selection clipboard -o)

# Share to phone
qdbus6 org.kde.kdeconnect /modules/kdeconnect/devices/$DEVICE_ID/share \
  org.kde.kdeconnect.device.share.shareText "$CLIP"
```

## CLI Tool

KDE Connect also has a CLI:

```bash
kdeconnect-cli --list-devices
kdeconnect-cli --device=<ID> --ping
kdeconnect-cli --device=<ID> --ping-msg "Hello"
kdeconnect-cli --device=<ID> --share /path/to/file
kdeconnect-cli --device=<ID> --share-text "Some text"
kdeconnect-cli --device=<ID> --ring
```
