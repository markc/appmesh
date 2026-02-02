# Application Discovery Guidelines

## Process for New Applications

When exploring a new application for AppMesh integration:

### 1. Check D-Bus Exposure

```bash
# List all services
qdbus6 | grep -i appname

# Introspect discovered service
qdbus6 org.kde.AppName /

# Explore specific paths
qdbus6 org.kde.AppName /AppName
```

### 2. Document What You Find

Create a file in `docs/<appname>.md` with:
- Service name(s)
- Available methods
- Working examples
- Known limitations
- Secrets/authentication issues

### 3. Test Methods Interactively

```bash
# Simple method call
qdbus6 org.kde.Dolphin /MainApplication quit

# Method with arguments
qdbus6 org.kde.Spectacle /Spectacle captureActiveWindow
```

### 4. Note the Gaps

Common issues to document:
- Methods that exist but don't work
- Features that require KConfig, not D-Bus
- Authentication barriers (KWallet, Polkit)
- Methods that hang or crash

## Documentation Template

```markdown
# AppName D-Bus Interface

## Service

- Name: `org.kde.AppName`
- Session bus

## Useful Methods

### /Path methodName

**Arguments**: type1 arg1, type2 arg2
**Returns**: type
**Example**: `qdbus6 org.kde.AppName /Path methodName arg1`

## Limitations

- Feature X requires direct config file editing
- Secrets not accessible via D-Bus

## Examples

[Working examples with exact commands]
```

## Skill vs Tool Decision

### Create a Skill When:

- Operation is commonly used
- User would benefit from a simple invocation
- Wraps multiple related tools

### Create a Tool When:

- Capability is unique and frequently needed
- Generic `appmesh_dbus_call` is too cumbersome
- Strong typing improves usability

### Document Only When:

- Capability exists but is rarely used
- Generic tools work fine
- App's D-Bus API is unstable

## Integration Priority

1. **High**: Apps you use daily (Dolphin, Konsole, Spectacle)
2. **Medium**: Productivity apps (KMail, Okular, Kate)
3. **Low**: Specialized apps (Kdenlive, Ardour)
4. **Skip**: Apps with no meaningful D-Bus API
