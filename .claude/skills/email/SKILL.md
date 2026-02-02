---
name: Email Compose
description: This skill should be used when the user asks to "compose an email", "send an email", "draft an email", "email this to", "open KMail composer", or wants to send content via email. Provides KMail D-Bus integration for composing emails with proper formatting.
version: 0.1.0
---

# Email Compose Skill

Compose and send emails via KMail D-Bus integration.

## When to Use

Activate this skill when the user:
- Asks to compose, draft, or send an email
- Wants to email content to someone
- Says "email this to [recipient]"
- Needs to open KMail composer with pre-filled content

## Workflow

### Step 1: Gather Information

Collect from user or context:
- **Recipient**: Email address (required)
- **Subject**: Email subject line (required)
- **Body**: The message content (required)

### Step 2: Format the Body

Apply 72-character word wrap for plain text email:
- Split into paragraphs (double newline separated)
- Wrap each paragraph at 72 characters on word boundaries
- Use plain text only (no markdown, no HTML)
- Escape double quotes in the text

### Step 3: Open KMail Composer

Use the simple 6-parameter D-Bus method to avoid attachment errors:

```
Tool: appmesh_dbus_call
Service: org.kde.kmail2
Path: /KMail
Method: org.kde.kmail.kmail.openComposer
Args: [to, cc, bcc, subject, body, hidden]
```

Parameters:
- `to`: Recipient email address
- `cc`: Empty string ""
- `bcc`: Empty string ""
- `subject`: Email subject
- `body`: Word-wrapped body text with \n for newlines
- `hidden`: "false" to show composer for review

### Step 4: Confirm

Inform the user that the KMail composer is open and ready for review and send.

## Important Notes

- KMail must be running for D-Bus to work
- Uses the default identity configured in KMail
- The simple 6-parameter method avoids attachment-related errors
- Setting hidden to "false" shows the composer for user review before sending

## Example D-Bus Call

```json
{
  "service": "org.kde.kmail2",
  "path": "/KMail",
  "method": "org.kde.kmail.kmail.openComposer",
  "args": ["recipient@example.com", "", "", "Subject Line", "Body text\nwrapped at\n72 chars.", "false"]
}
```

## Word Wrap Implementation

To wrap text at 72 characters:

1. Split text into paragraphs (on double newlines)
2. For each paragraph:
   - Split into words
   - Build lines by adding words until line exceeds 72 chars
   - Start new line when limit reached
3. Join lines with single newlines within paragraphs
4. Join paragraphs with double newlines
