<?php
/**
 * AppMesh Config Plugin - KDE/XDG Configuration Management
 *
 * Manages KDE Plasma configuration files and global themes.
 * Fills the gap where D-Bus cannot persist settings.
 *
 * Key directories:
 * - ~/.config/          - KConfig files (kdeglobals, kwinrc, etc.)
 * - ~/.local/share/plasma/look-and-feel/  - User global themes
 * - /usr/share/plasma/look-and-feel/      - System global themes
 *
 * Tools:
 * - lookandfeeltool    - Apply global themes (CLI)
 * - lookandfeelexplorer - Create themes (from plasma-sdk)
 * - kwriteconfig6      - Write individual config values
 * - kreadconfig6       - Read individual config values
 *
 * Plasma 6.6 (Feb 2026) adds: Save current visual config as global theme
 */

// ============================================================================
// Configuration Paths
// ============================================================================

const KCONFIG_DIR = '~/.config';
const PLASMA_THEMES_USER = '~/.local/share/plasma/look-and-feel';
const PLASMA_THEMES_SYSTEM = '/usr/share/plasma/look-and-feel';
const PLASMA_DESKTOP_THEME = '~/.local/share/plasma/desktoptheme';

/**
 * Expand ~ to home directory
 */
function config_expand_path(string $path): string
{
    if (str_starts_with($path, '~/')) {
        return getenv('HOME') . substr($path, 1);
    }
    return $path;
}

/**
 * Get list of KConfig files in ~/.config
 */
function config_list_kconfig_files(): array
{
    $configDir = config_expand_path(KCONFIG_DIR);
    $files = [];

    // Key KDE config files
    $keyFiles = [
        'kdeglobals',
        'kwinrc',
        'plasmarc',
        'plasma-org.kde.plasma.desktop-appletsrc',
        'kscreenlockerrc',
        'kcminputrc',
        'kglobalshortcutsrc',
        'dolphinrc',
        'konsolerc',
        'katerc',
    ];

    foreach ($keyFiles as $file) {
        $path = "{$configDir}/{$file}";
        if (file_exists($path)) {
            $files[] = [
                'name' => $file,
                'path' => $path,
                'size' => filesize($path),
                'modified' => date('Y-m-d H:i:s', filemtime($path)),
            ];
        }
    }

    return $files;
}

/**
 * Read a KConfig file and parse into sections
 */
function config_read_kconfig(string $file): array
{
    $path = config_expand_path($file);

    if (!file_exists($path)) {
        // Try prepending ~/.config/
        $path = config_expand_path(KCONFIG_DIR) . '/' . $file;
    }

    if (!file_exists($path)) {
        return ['error' => "File not found: {$file}"];
    }

    $content = file_get_contents($path);
    $lines = explode("\n", $content);
    $sections = [];
    $currentSection = 'General';

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (preg_match('/^\[(.+)\]$/', $line, $match)) {
            $currentSection = $match[1];
            if (!isset($sections[$currentSection])) {
                $sections[$currentSection] = [];
            }
        } elseif (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $sections[$currentSection][trim($key)] = trim($value);
        }
    }

    return $sections;
}

/**
 * Use kreadconfig6 to read a specific value
 */
function config_read_value(string $file, string $group, string $key): string
{
    $cmd = sprintf(
        'kreadconfig6 --file %s --group %s --key %s',
        escapeshellarg($file),
        escapeshellarg($group),
        escapeshellarg($key)
    );

    $result = appmesh_exec($cmd);
    return trim($result['output']);
}

/**
 * Use kwriteconfig6 to write a specific value
 */
function config_write_value(string $file, string $group, string $key, string $value): string
{
    $cmd = sprintf(
        'kwriteconfig6 --file %s --group %s --key %s %s',
        escapeshellarg($file),
        escapeshellarg($group),
        escapeshellarg($key),
        escapeshellarg($value)
    );

    $result = appmesh_exec($cmd);

    if ($result['success'] && trim($result['output']) === '') {
        return "Set {$file}[{$group}]{$key} = {$value}";
    }

    AppMeshLogger::warning("kwriteconfig6 issue", [
        'file' => $file,
        'group' => $group,
        'key' => $key,
        'exitCode' => $result['exitCode'],
    ]);

    return "Error: {$result['output']}";
}

/**
 * List installed global themes
 */
function config_list_themes(): array
{
    $themes = [];

    // User themes
    $userDir = config_expand_path(PLASMA_THEMES_USER);
    if (is_dir($userDir)) {
        foreach (scandir($userDir) as $item) {
            if ($item[0] === '.') continue;
            $path = "{$userDir}/{$item}";
            if (is_dir($path)) {
                $metadata = "{$path}/metadata.json";
                $name = $item;

                if (file_exists($metadata)) {
                    $meta = json_decode(file_get_contents($metadata), true);
                    $name = $meta['KPlugin']['Name'] ?? $item;
                }

                $themes[] = [
                    'id' => $item,
                    'name' => $name,
                    'location' => 'user',
                    'path' => $path,
                ];
            }
        }
    }

    // System themes
    if (is_dir(PLASMA_THEMES_SYSTEM)) {
        foreach (scandir(PLASMA_THEMES_SYSTEM) as $item) {
            if ($item[0] === '.') continue;
            $path = PLASMA_THEMES_SYSTEM . "/{$item}";
            if (is_dir($path)) {
                $metadata = "{$path}/metadata.json";
                $name = $item;

                if (file_exists($metadata)) {
                    $meta = json_decode(file_get_contents($metadata), true);
                    $name = $meta['KPlugin']['Name'] ?? $item;
                }

                $themes[] = [
                    'id' => $item,
                    'name' => $name,
                    'location' => 'system',
                    'path' => $path,
                ];
            }
        }
    }

    return $themes;
}

/**
 * Get current global theme
 */
function config_current_theme(): string
{
    return config_read_value('kdeglobals', 'KDE', 'LookAndFeelPackage');
}

// ============================================================================
// Tool Definitions
// ============================================================================

return [
    // -------------------------------------------------------------------------
    // KConfig File Tools
    // -------------------------------------------------------------------------
    'appmesh_config_list' => new Tool(
        description: 'List KDE configuration files in ~/.config',
        inputSchema: schema(),
        handler: function (array $args): string {
            $files = config_list_kconfig_files();

            if (empty($files)) {
                return "No KDE config files found in ~/.config";
            }

            $output = "KDE Configuration Files:\n\n";

            foreach ($files as $file) {
                $output .= sprintf(
                    "%-45s %8s  %s\n",
                    $file['name'],
                    number_format($file['size']) . 'B',
                    $file['modified']
                );
            }

            return $output;
        }
    ),

    'appmesh_config_read' => new Tool(
        description: 'Read a KConfig file and show its sections and values',
        inputSchema: schema(
            [
                'file' => prop('string', 'Config file name (e.g., kdeglobals, kwinrc) or full path'),
                'section' => prop('string', 'Optional: only show this section'),
            ],
            ['file']
        ),
        handler: function (array $args): string {
            $file = $args['file'];
            $section = $args['section'] ?? null;

            $config = config_read_kconfig($file);

            if (isset($config['error'])) {
                return "Error: {$config['error']}";
            }

            if ($section) {
                if (!isset($config[$section])) {
                    return "Section [{$section}] not found in {$file}";
                }

                $output = "[{$section}]\n";
                foreach ($config[$section] as $key => $value) {
                    $output .= "{$key}={$value}\n";
                }
                return $output;
            }

            $output = "Configuration: {$file}\n\n";

            foreach ($config as $sectionName => $values) {
                $output .= "[{$sectionName}]\n";
                foreach ($values as $key => $value) {
                    $output .= "  {$key} = {$value}\n";
                }
                $output .= "\n";
            }

            return $output;
        }
    ),

    'appmesh_config_get' => new Tool(
        description: 'Get a specific config value using kreadconfig6',
        inputSchema: schema(
            [
                'file' => prop('string', 'Config file name (e.g., kdeglobals)'),
                'group' => prop('string', 'Section/group name'),
                'key' => prop('string', 'Key name'),
            ],
            ['file', 'group', 'key']
        ),
        handler: function (array $args): string {
            $value = config_read_value($args['file'], $args['group'], $args['key']);
            return $value ?: '(empty or not set)';
        }
    ),

    'appmesh_config_set' => new Tool(
        description: 'Set a specific config value using kwriteconfig6',
        inputSchema: schema(
            [
                'file' => prop('string', 'Config file name (e.g., kdeglobals)'),
                'group' => prop('string', 'Section/group name'),
                'key' => prop('string', 'Key name'),
                'value' => prop('string', 'Value to set'),
            ],
            ['file', 'group', 'key', 'value']
        ),
        handler: function (array $args): string {
            return config_write_value(
                $args['file'],
                $args['group'],
                $args['key'],
                $args['value']
            );
        }
    ),

    // -------------------------------------------------------------------------
    // Global Theme Tools
    // -------------------------------------------------------------------------
    'appmesh_theme_list' => new Tool(
        description: 'List installed Plasma global themes',
        inputSchema: schema(),
        handler: function (array $args): string {
            $themes = config_list_themes();
            $current = config_current_theme();

            if (empty($themes)) {
                return "No global themes found";
            }

            $output = "Plasma Global Themes:\n\n";
            $output .= sprintf("Current: %s\n\n", $current ?: '(default)');

            $output .= "User themes (~/.local/share/plasma/look-and-feel/):\n";
            foreach ($themes as $theme) {
                if ($theme['location'] === 'user') {
                    $marker = ($theme['id'] === $current) ? ' *' : '';
                    $output .= "  {$theme['id']}{$marker}\n";
                    if ($theme['name'] !== $theme['id']) {
                        $output .= "    → {$theme['name']}\n";
                    }
                }
            }

            $output .= "\nSystem themes (/usr/share/plasma/look-and-feel/):\n";
            foreach ($themes as $theme) {
                if ($theme['location'] === 'system') {
                    $marker = ($theme['id'] === $current) ? ' *' : '';
                    $output .= "  {$theme['id']}{$marker}\n";
                }
            }

            return $output;
        }
    ),

    'appmesh_theme_apply' => new Tool(
        description: 'Apply a Plasma global theme using lookandfeeltool',
        inputSchema: schema(
            [
                'theme' => prop('string', 'Theme ID to apply (from theme_list)'),
            ],
            ['theme']
        ),
        handler: function (array $args): string {
            if ($error = appmesh_validate($args, ['theme'])) {
                return "Error: {$error}";
            }

            $theme = $args['theme'];

            // Verify theme exists
            $themes = config_list_themes();
            $found = false;

            foreach ($themes as $t) {
                if ($t['id'] === $theme) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return "Error: Theme '{$theme}' not found. Use appmesh_theme_list to see available themes.";
            }

            $result = appmesh_exec('lookandfeeltool --apply ' . escapeshellarg($theme));

            if ($result['success'] && trim($result['output']) === '') {
                return "Applied theme: {$theme}\n\nNote: Some changes may require logout/login to take full effect.";
            }

            return "Result: {$result['output']}";
        }
    ),

    'appmesh_theme_current' => new Tool(
        description: 'Get the currently active global theme',
        inputSchema: schema(),
        handler: function (array $args): string {
            $current = config_current_theme();
            return $current ?: '(default/none set)';
        }
    ),

    // -------------------------------------------------------------------------
    // Backup/Restore Tools
    // -------------------------------------------------------------------------
    'appmesh_config_backup' => new Tool(
        description: 'Backup KDE configuration files to a timestamped archive',
        inputSchema: schema(
            [
                'name' => prop('string', 'Backup name (default: kde-backup)'),
                'include_themes' => prop('boolean', 'Include user themes (default: false)'),
            ]
        ),
        handler: function (array $args): string {
            $name = $args['name'] ?? 'kde-backup';
            $includeThemes = $args['include_themes'] ?? false;

            $timestamp = date('Y-m-d_H-i-s');
            $backupDir = config_expand_path('~/.local/share/appmesh/backups');

            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $archiveName = "{$name}_{$timestamp}.tar.gz";
            $archivePath = "{$backupDir}/{$archiveName}";

            // Files to backup
            $configDir = config_expand_path(KCONFIG_DIR);
            $files = [
                "{$configDir}/kdeglobals",
                "{$configDir}/kwinrc",
                "{$configDir}/plasmarc",
                "{$configDir}/plasma-org.kde.plasma.desktop-appletsrc",
                "{$configDir}/kscreenlockerrc",
                "{$configDir}/kcminputrc",
                "{$configDir}/kglobalshortcutsrc",
                "{$configDir}/dolphinrc",
                "{$configDir}/konsolerc",
            ];

            // Filter to existing files
            $existingFiles = array_filter($files, 'file_exists');

            if (empty($existingFiles)) {
                return "Error: No config files found to backup";
            }

            // Build tar command
            $fileArgs = implode(' ', array_map('escapeshellarg', $existingFiles));

            if ($includeThemes) {
                $themesDir = config_expand_path(PLASMA_THEMES_USER);
                if (is_dir($themesDir)) {
                    $fileArgs .= ' ' . escapeshellarg($themesDir);
                }
            }

            $cmd = "tar -czf " . escapeshellarg($archivePath) . " {$fileArgs} 2>&1";
            $output = shell_exec($cmd);

            if (file_exists($archivePath)) {
                $size = filesize($archivePath);
                return "Backup created: {$archivePath}\nSize: " . number_format($size) . " bytes\nFiles: " . count($existingFiles);
            }

            return "Error creating backup: {$output}";
        }
    ),

    'appmesh_config_restore' => new Tool(
        description: 'List available backups or restore from a backup',
        inputSchema: schema(
            [
                'action' => prop('string', 'Action: list or restore'),
                'backup' => prop('string', 'Backup filename to restore (required for restore)'),
            ],
            ['action']
        ),
        handler: function (array $args): string {
            $action = $args['action'];
            $backupDir = config_expand_path('~/.local/share/appmesh/backups');

            if ($action === 'list') {
                if (!is_dir($backupDir)) {
                    return "No backups found. Use appmesh_config_backup to create one.";
                }

                $backups = glob("{$backupDir}/*.tar.gz");

                if (empty($backups)) {
                    return "No backups found in {$backupDir}";
                }

                $output = "Available Backups:\n\n";

                foreach ($backups as $backup) {
                    $name = basename($backup);
                    $size = filesize($backup);
                    $date = date('Y-m-d H:i:s', filemtime($backup));
                    $output .= sprintf("%-50s %10s  %s\n", $name, number_format($size) . 'B', $date);
                }

                return $output;
            }

            if ($action === 'restore') {
                $backup = $args['backup'] ?? null;

                if (!$backup) {
                    return "Error: Specify backup filename to restore";
                }

                $backupPath = "{$backupDir}/{$backup}";

                if (!file_exists($backupPath)) {
                    return "Error: Backup not found: {$backup}";
                }

                // Extract to root (files have full paths)
                $cmd = "tar -xzf " . escapeshellarg($backupPath) . " -C / 2>&1";
                $output = shell_exec($cmd);

                if ($output === null || $output === '') {
                    return "Restored from: {$backup}\n\nNote: Logout and login for changes to take effect.";
                }

                return "Restore output: {$output}";
            }

            return "Unknown action. Use: list or restore";
        }
    ),

    // -------------------------------------------------------------------------
    // Plasma 6.6 Theme Export (preparation)
    // -------------------------------------------------------------------------
    'appmesh_theme_export' => new Tool(
        description: 'Export current visual settings as a new global theme (Plasma 6.6+ or manual)',
        inputSchema: schema(
            [
                'name' => prop('string', 'Theme name/ID'),
                'display_name' => prop('string', 'Human-readable display name'),
                'author' => prop('string', 'Author name (default: current user)'),
            ],
            ['name']
        ),
        handler: function (array $args): string {
            $themeId = preg_replace('/[^a-zA-Z0-9._-]/', '', $args['name']);
            $displayName = $args['display_name'] ?? $args['name'];
            $author = $args['author'] ?? getenv('USER');

            $themePath = config_expand_path(PLASMA_THEMES_USER) . "/{$themeId}";

            // Check if lookandfeelexplorer is available (plasma-sdk)
            $explorer = trim(shell_exec('which lookandfeelexplorer 2>/dev/null') ?? '');

            if ($explorer) {
                // Use the official tool
                return <<<EOT
Plasma SDK detected. To create a theme from current settings:

1. Run: lookandfeelexplorer
2. Configure theme metadata
3. Export to: {$themePath}

Or wait for Plasma 6.6 (Feb 17, 2026) which adds:
  System Settings → Appearance → Global Theme → "Save As..."

This will capture: colors, window decorations, icons, cursors,
splash screen, and other visual settings in one package.
EOT;
            }

            // Manual theme creation
            if (!is_dir($themePath)) {
                mkdir($themePath, 0755, true);
                mkdir("{$themePath}/contents", 0755, true);
                mkdir("{$themePath}/contents/defaults", 0755, true);
            }

            // Create metadata.json
            $metadata = [
                'KPlugin' => [
                    'Authors' => [['Name' => $author]],
                    'Description' => "Custom theme created by AppMesh",
                    'Id' => $themeId,
                    'Name' => $displayName,
                    'Version' => '1.0',
                    'License' => 'CC0-1.0',
                    'Category' => '',
                    'EnabledByDefault' => true,
                ],
                'X-Plasma-APIVersion' => 2,
            ];

            file_put_contents(
                "{$themePath}/metadata.json",
                json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            // Create defaults file with current settings
            $defaults = [];
            $defaults['kdeglobals'] = [
                'KDE' => [
                    'widgetStyle' => config_read_value('kdeglobals', 'KDE', 'widgetStyle'),
                    'ColorScheme' => config_read_value('kdeglobals', 'General', 'ColorScheme'),
                ],
            ];

            $defaultsContent = '';
            foreach ($defaults as $file => $sections) {
                $defaultsContent .= "[{$file}]\n";
                foreach ($sections as $section => $keys) {
                    foreach ($keys as $key => $value) {
                        if ($value) {
                            $defaultsContent .= "{$section}/{$key}={$value}\n";
                        }
                    }
                }
            }

            file_put_contents("{$themePath}/contents/defaults", $defaultsContent);

            return <<<EOT
Theme skeleton created: {$themePath}

Files created:
  metadata.json     - Theme metadata
  contents/defaults - Default settings

To complete the theme manually:
1. Copy color scheme to contents/colors/
2. Copy wallpaper to contents/splash/images/
3. Add icon/cursor theme references to defaults

For full theme export, install plasma-sdk:
  paru -S plasma-sdk
  lookandfeelexplorer

Or use Plasma 6.6's built-in "Save As..." feature (Feb 2026).
EOT;
        }
    ),

    // -------------------------------------------------------------------------
    // Plasma 6.6 Info
    // -------------------------------------------------------------------------
    'appmesh_plasma_info' => new Tool(
        description: 'Show Plasma version and upcoming 6.6 features',
        inputSchema: schema(),
        handler: function (array $args): string {
            $result = appmesh_exec('plasmashell --version', false);
            $version = $result['success'] ? trim($result['output']) : '';

            $info = "Plasma Desktop Information\n\n";
            $info .= "Installed: " . ($version ?: 'plasmashell not found') . "\n\n";

            $info .= <<<EOT
Plasma 6.6 (Release: February 17, 2026)
========================================

New Global Theme Features:
- Save current visual configuration as new global theme
- Captures: colors, window decorations, icons, cursors, splash
- One-click theme creation from System Settings
- Full desktop appearance snapshot

Location: System Settings → Appearance → Global Theme → "Save As..."

This enables:
- Easy backup of your custom desktop look
- Sharing themes with others
- Quick switching between configurations
- CachyOS desktop standardization

Current theme tools available now:
- appmesh_theme_list    - List installed themes
- appmesh_theme_apply   - Apply a theme
- appmesh_theme_export  - Create theme skeleton
- appmesh_config_backup - Backup all configs
EOT;

            return $info;
        }
    ),
];
