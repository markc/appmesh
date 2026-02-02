---
name: Thunderbird Email
description: This skill should be used when the user asks to "use tb to send", "use thunderbird to email", "tb email", "send via thunderbird", or specifically mentions Thunderbird for composing emails. Uses Thunderbird's command-line compose feature.
version: 0.1.0
---

# Thunderbird Email Skill

Compose emails via Thunderbird's command-line interface.

## When to Use

Activate this skill when the user:
- Says "use tb to send an email to..."
- Says "tb email" or "thunderbird email"
- Specifically asks to use Thunderbird for composing
- Wants to send via their daily email driver (not KMail)

For KMail D-Bus composition, use the email skill instead (default).

## Workflow

### Step 1: Gather Information

Collect from user or context:
- **Recipient**: Email address (required)
- **Subject**: Email subject line (required)
- **Body**: The message content (required)
- **From**: Optional sender identity

### Step 2: Format the Body

For short messages:
- Word-wrap at 72 characters
- Replace newlines with URL-encoded `%0A` for command line
- Or keep simple single-line messages as-is

For long messages:
- Write body to a temp file
- Use `message=` parameter to reference the file

### Step 3: Compose via Command Line

Use Bash to invoke Thunderbird:

```bash
thunderbird -compose "to='recipient@example.com',subject='Subject Line',body='Message text',format=text" &
```

Parameters:
- `to` - Recipient email address
- `cc` - CC recipients (optional)
- `bcc` - BCC recipients (optional)
- `subject` - Email subject
- `body` - Short message body
- `message` - Path to file containing body (for long text)
- `attachment` - Path to file attachment
- `from` - Sender identity
- `format` - `text` or `html`

### Step 4: Confirm

Inform the user that the Thunderbird composer is open and ready for review.

## Handling Long Messages

For messages with multiple paragraphs or special characters:

1. Write the body to a temp file:
```bash
cat > /tmp/email-body.txt << 'EOF'
Your message here.

Multiple paragraphs work fine.

No escaping needed in the file.
EOF
```

2. Use the message parameter:
```bash
thunderbird -compose "to='recipient@example.com',subject='Subject',message='/tmp/email-body.txt',format=text" &
```

## Example Commands

Simple message:
```bash
thunderbird -compose "to='mark@example.com',subject='Quick note',body='Just a short message.',format=text" &
```

With from identity:
```bash
thunderbird -compose "to='mark@example.com',from='markc@renta.net',subject='Hello',body='Message here',format=text" &
```

With attachment:
```bash
thunderbird -compose "to='mark@example.com',subject='Document',body='See attached.',attachment='/path/to/file.pdf',format=text" &
```

From file:
```bash
thunderbird -compose "to='mark@example.com',subject='Long message',message='/tmp/body.txt',format=text" &
```

## Important Notes

- Thunderbird must be running (or will start)
- The `&` backgrounds the command so Claude doesn't wait
- Use `format=text` for plain text emails
- Single quotes inside values need escaping or use file method
- URL encoding: newline = `%0A`, space = `%20`

## Comparison with KMail

| Feature | KMail (D-Bus) | Thunderbird (CLI) |
|---------|---------------|-------------------|
| Trigger | "compose email" | "tb email" / "use tb" |
| Method | D-Bus appmesh tool | Bash command |
| Newlines | `\n` in JSON | File or URL encode |
| Long text | Direct in args | Better via file |
| Attachments | Problematic | Works well |
