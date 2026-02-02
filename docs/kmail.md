# KMail / Akonadi Integration Notes

## D-Bus Interface

KMail exposes a comprehensive D-Bus interface for automation.

**Service:** `org.kde.kmail2`
**Path:** `/KMail`
**Interface:** `org.kde.kmail.kmail`

### Compose Email (Most Useful)

Open a new composer window with pre-filled content:

```bash
# Simple version: to, cc, bcc, subject, body, hidden
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.openComposer \
    "recipient@example.com" \
    "" \
    "" \
    "Email Subject" \
    "Email body text here" \
    false
```

**Parameters:**
- `to` - Recipient email (empty string for blank)
- `cc` - CC recipients
- `bcc` - BCC recipients
- `subject` - Email subject line
- `body` - Email body text
- `hidden` - If true, composer stays hidden (for automation)

**Extended version with attachments and identity:**
```bash
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.openComposer \
    "to@example.com" "" "" "Subject" "Body" \
    false \
    "" \
    "['/path/to/attachment.pdf']" \
    "[]" \
    "" "" "344442307" "false"
```

**Full parameter list:**
| # | Parameter | Description |
|---|-----------|-------------|
| 1 | to | Recipient email address |
| 2 | cc | CC recipients |
| 3 | bcc | BCC recipients |
| 4 | subject | Email subject line |
| 5 | body | Email body text |
| 6 | hidden | If "true", composer stays hidden |
| 7 | messageFile | Path to .eml file to use as template |
| 8 | attachmentPaths | JSON array of attachment paths |
| 9 | customHeaders | JSON array of custom headers |
| 10 | replyTo | Reply-To address |
| 11 | inReplyTo | Message-ID being replied to |
| 12 | identity | Identity uoid (from ~/.config/emailidentities) |
| 13 | htmlBody | If "true", body is HTML |

### Setting the "From" Identity

Identities are configured in `~/.config/emailidentities`. Each identity has a `uoid` (unique object ID).

**Find your identity uoid:**
```bash
grep -E '^\[Identity|^Email Address=|^uoid=' ~/.config/emailidentities
```

**Example output:**
```
[Identity #0]
Email Address=mc@motd.com
uoid=344442307
[Identity #1]
Email Address=markc@renta.net
uoid=987654321
```

**Open composer with specific identity:**
```bash
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.openComposer \
    "recipient@example.com" "" "" "Subject" "Body" \
    "false" "" "[]" "[]" "" "" "987654321" "false"
```

**Note:** To add a new identity, use KMail: Settings → Configure KMail → Identities → Add.

### Check Mail

```bash
# Check all accounts
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.checkMail

# Check specific account
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.checkAccount "account_name"
```

### List Accounts

```bash
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.accounts
```

### Folder Operations

```bash
# Select folder
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.selectFolder "folder_path"

# Show folder
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.showFolder "collection_id"
```

### Message Operations

```bash
# Show specific message
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.showMail 12345

# View message from file
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.viewMessage "/path/to/message.eml"

# Reply to message
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.replyMail 12345 false  # false = reply to sender only
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.replyMail 12345 true   # true = reply to all
```

### Window Control

```bash
# Open main reader window
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.openReader
```

### Network Control

```bash
# Pause/resume background jobs
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.pauseBackgroundJobs
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.resumeBackgroundJobs

# Stop/resume network
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.stopNetworkJobs
qdbus6 org.kde.kmail2 /KMail org.kde.kmail.kmail.resumeNetworkJobs
```

### DAWN Integration Example

```php
// Compose email via DAWN
Dawn::call('dawn_dbus_call', [
    'service' => 'org.kde.kmail2',
    'path' => '/KMail',
    'method' => 'org.kde.kmail.kmail.openComposer',
    'args' => ['', '', '', 'Subject', 'Body text', 'false']
]);
```

---

## Password Storage (Qt Keychain)

Akonadi IMAP resources store passwords via Qt Keychain using the **Secret Service API**.

### Key Format

The password key is the **config file name** (with `rc` suffix), not the email address.

| Resource | Config File | Password Key |
|----------|-------------|--------------|
| akonadi_imap_resource_0 | akonadi_imap_resource_0rc | `akonadi_imap_resource_0rc` |
| akonadi_imap_resource_1 | akonadi_imap_resource_1rc | `akonadi_imap_resource_1rc` |

### Programmatic Password Storage

```bash
secret-tool store --label="imap/akonadi_imap_resource_0rc" \
  xdg:schema org.qt.keychain \
  user akonadi_imap_resource_0rc \
  server imap \
  type plaintext <<< 'password_here'
```

### Verify Password

```bash
secret-tool lookup user akonadi_imap_resource_0rc server imap
```

## Creating IMAP Resource via D-Bus

### 1. Create the resource instance

```bash
qdbus6 org.freedesktop.Akonadi.Control /AgentManager \
  org.freedesktop.Akonadi.AgentManager.createAgentInstance akonadi_imap_resource
# Returns: akonadi_imap_resource_N
```

### 2. Write config file

Config location: `~/.config/akonadi_imap_resource_Nrc`

```ini
[network]
ImapServer=mail.example.com
ImapPort=993
UserName=user@example.com
Safety=SSL
Authentication=1
SubscriptionEnabled=false

[cache]
DisconnectedModeEnabled=true
IntervalCheckEnabled=true
IntervalCheckTime=5
AutomaticExpungeEnabled=true

[General]
name=user@example.com
```

### 3. Store password (see above)

### 4. Set resource name and bring online

```bash
qdbus6 org.freedesktop.Akonadi.Control /AgentManager \
  org.freedesktop.Akonadi.AgentManager.setAgentInstanceName akonadi_imap_resource_N "user@example.com"

qdbus6 org.freedesktop.Akonadi.Control /AgentManager \
  org.freedesktop.Akonadi.AgentManager.restartAgentInstance akonadi_imap_resource_N

qdbus6 org.freedesktop.Akonadi.Resource.akonadi_imap_resource_N / \
  org.freedesktop.Akonadi.Agent.Status.setOnline true
```

## SMTP Transport

Config location: `~/.config/mailtransports`

```ini
[General]
default-transport=1

[Transport 1]
id=1
name=user@example.com
host=smtp.example.com
port=465
user=user@example.com
auth=true
storepass=true
encryption=SSL
authtype=1
```

## Useful D-Bus Commands

```bash
# List all Akonadi agents
qdbus6 org.freedesktop.Akonadi.Control /AgentManager \
  org.freedesktop.Akonadi.AgentManager.agentInstances

# Check resource status
qdbus6 org.freedesktop.Akonadi.Resource.akonadi_imap_resource_N / \
  org.freedesktop.Akonadi.Agent.Status.statusMessage

# Open config dialog
qdbus6 org.freedesktop.Akonadi.Control /AgentManager \
  org.freedesktop.Akonadi.AgentManager.agentInstanceConfigure akonadi_imap_resource_N 0

# Sync resource
qdbus6 org.freedesktop.Akonadi.Resource.akonadi_imap_resource_N / \
  org.freedesktop.Akonadi.Resource.synchronize
```

## Config File Locations

| Purpose | Path |
|---------|------|
| IMAP accounts | `~/.config/akonadi_imap_resource_*rc` |
| SMTP transports | `~/.config/mailtransports` |
| Akonadi agents | `~/.config/akonadi/agentsrc` |
| KMail identity | `~/.config/emailidentities` |
