# Runbook: Add New Protocol Plugin

## Purpose

Add a new protocol plugin to AppMesh for desktop automation.

## Prerequisites

- Understanding of the target protocol
- Test application that speaks the protocol
- PHP 8.4+ available

## Procedure

### 1. Create Plugin File

Create `server/plugins/<protocol>.php`:

```php
<?php

declare(strict_types=1);

// Plugin: <Protocol Name>
// Tools for <description>

return [
    'appmesh_<protocol>_<action>' => new Tool(
        description: 'What this tool does',
        inputSchema: schema(
            [
                'param1' => prop('string', 'Parameter description'),
                'param2' => prop('integer', 'Optional param'),
            ],
            ['param1']  // required parameters
        ),
        handler: function (array $args): string {
            $param1 = $args['param1'];
            $param2 = $args['param2'] ?? null;

            // Implementation
            $result = doSomething($param1, $param2);

            return json_encode($result);
        }
    ),
];
```

### 2. Test Plugin Loading

Restart Claude Code or reload MCP:
```bash
# Claude Code will automatically load new plugins
# from server/plugins/*.php
```

### 3. Verify Tool Registration

Check that tools appear in Claude Code's available tools.

### 4. Create Skill Documentation

Create `.claude/skills/<protocol>.md`:

```markdown
# <Protocol> Skill

<skill>
name: <protocol>
description: <what it does>
user-invocable: true
arguments: <action> [options]
</skill>

## Actions
- action1 - Description
- action2 - Description

## Instructions
1. For action1: Use appmesh_<protocol>_action1
2. ...
```

### 5. Add Protocol Guidelines

Update `.claude/guidelines/protocols.md` with:
- Port conventions
- Connection patterns
- Common gotchas

### 6. Document Applications

If protocol is app-specific, create `docs/<app>.md`:
- How to enable protocol in app
- Available commands/methods
- Working examples

## Verification

- [ ] Plugin loads without errors
- [ ] Tools appear in Claude Code
- [ ] Basic operations work
- [ ] Skill can be invoked
- [ ] Documentation is complete

## Rollback

Delete the plugin file - AppMesh loads plugins dynamically.

## Example: Adding MIDI Plugin

```php
// server/plugins/midi.php
return [
    'appmesh_midi_list_devices' => new Tool(
        description: 'List MIDI devices via PipeWire',
        inputSchema: schema([], []),
        handler: function (array $args): string {
            $output = shell_exec('pw-link -o | grep -i midi');
            return $output ?: 'No MIDI devices found';
        }
    ),

    'appmesh_midi_connect' => new Tool(
        description: 'Connect MIDI ports',
        inputSchema: schema(
            [
                'output' => prop('string', 'Output port name'),
                'input' => prop('string', 'Input port name'),
            ],
            ['output', 'input']
        ),
        handler: function (array $args): string {
            $out = escapeshellarg($args['output']);
            $in = escapeshellarg($args['input']);
            shell_exec("pw-link {$out} {$in}");
            return "Connected {$args['output']} -> {$args['input']}";
        }
    ),
];
```
