# KDE Configuration Management

<skill>
name: config
description: Manage KDE Plasma configuration files, global themes, and desktop snapshots
user-invocable: true
arguments: <action> [options]
</skill>

## Actions

### Configuration Files
- `list` - List KDE config files in ~/.config
- `read <file> [section]` - Read a config file
- `get <file> <group> <key>` - Get specific value
- `set <file> <group> <key> <value>` - Set specific value

### Global Themes
- `themes` - List installed global themes
- `current` - Show current theme
- `apply <theme-id>` - Apply a global theme
- `export <name>` - Create theme from current settings

### Backup/Restore
- `backup [name]` - Backup KDE configuration
- `restore list` - List available backups
- `restore <backup-file>` - Restore from backup

### System Info
- `plasma` - Show Plasma version and 6.6 features

## Examples

```bash
# Configuration files
/config list                           # List config files
/config read kdeglobals               # Read kdeglobals
/config read kwinrc Compositing       # Read specific section
/config get kdeglobals General ColorScheme
/config set kdeglobals KDE SingleClick true

# Themes
/config themes                         # List themes
/config current                        # Current theme
/config apply org.kde.breeze.desktop  # Apply Breeze
/config export "MyTheme"              # Export current

# Backup
/config backup "before-changes"       # Create backup
/config restore list                   # List backups
/config restore kde-backup_2026-02-01.tar.gz

# Info
/config plasma                         # Version + 6.6 info
```

## Instructions

When the user invokes this skill:

1. **For `list`**: Use `appmesh_config_list`
2. **For `read`**: Use `appmesh_config_read` with file and optional section
3. **For `get`**: Use `appmesh_config_get` with file, group, key
4. **For `set`**: Use `appmesh_config_set` with file, group, key, value
5. **For `themes`**: Use `appmesh_theme_list`
6. **For `current`**: Use `appmesh_theme_current`
7. **For `apply`**: Use `appmesh_theme_apply` with theme ID
8. **For `export`**: Use `appmesh_theme_export` with name
9. **For `backup`**: Use `appmesh_config_backup`
10. **For `restore`**: Use `appmesh_config_restore`
11. **For `plasma`**: Use `appmesh_plasma_info`

## Key Configuration Files

| File | Purpose |
|------|---------|
| `kdeglobals` | Global KDE settings, colors, fonts |
| `kwinrc` | Window manager settings |
| `plasmarc` | Plasma shell settings |
| `plasma-org.kde.plasma.desktop-appletsrc` | Panel/widget layout |
| `kscreenlockerrc` | Lock screen settings |
| `kcminputrc` | Mouse/keyboard settings |
| `kglobalshortcutsrc` | Global keyboard shortcuts |

## Theme Locations

- **User**: `~/.local/share/plasma/look-and-feel/`
- **System**: `/usr/share/plasma/look-and-feel/`

## Plasma 6.6 (February 17, 2026)

New feature: **Save As Global Theme**
- System Settings → Appearance → Global Theme → "Save As..."
- Captures entire visual configuration in one package
- Colors, decorations, icons, cursors, splash screen
- Perfect for CachyOS desktop standardization

## Backup Location

Backups stored in: `~/.local/share/appmesh/backups/`
