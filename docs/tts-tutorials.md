# TTS Tutorial Generation

Generate AI-voiced tutorials using the AppMesh TTS plugin and Google Gemini.

## Recommended Voice Settings

**Voice:** `Charon` (deep male, professional)
**Style:** `Neutral mid-Atlantic accent, clear and professional, like a calm documentary narrator`

This combination produces clear, neutral narration suitable for technical tutorials.

## Quick Start

```bash
# Set up API key
cp server/.env.example server/.env
# Edit server/.env and add your GEMINI_API_KEY from https://aistudio.google.com/apikey
```

## Available Tools

| Tool | Purpose |
|------|---------|
| `appmesh_tts` | Convert text to speech |
| `appmesh_tutorial_script` | Generate tutorial script with AI |
| `appmesh_tutorial_full` | Generate script + audio together |
| `appmesh_screen_record` | Start/stop desktop recording |
| `appmesh_video_combine` | Merge video + audio into final MP4 |
| `appmesh_tts_voices` | List available voices |

## Example Usage

### Simple TTS
```php
AppMesh::call('appmesh_tts', [
    'text' => 'Your narration text here',
    'voice' => 'Charon',
    'style' => 'Neutral mid-Atlantic accent, clear and professional, like a calm documentary narrator'
]);
```

### Full Tutorial Workflow
```bash
# 1. Generate script and audio
appmesh_tutorial_full topic="Getting started with AppMesh"

# 2. Record your screen demo
appmesh_screen_record action="start"
# ... perform demo ...
appmesh_screen_record action="stop"

# 3. Combine into final video
appmesh_video_combine video="recording_*.mp4" audio="tutorial_*.wav"
```

## Available Voices

| Voice | Character |
|-------|-----------|
| **Charon** | Deep male, professional (recommended) |
| Kore | Clear female |
| Puck | Energetic |
| Fenrir | Authoritative |
| Zephyr | Soft |
| Aoede, Leda, Orus, Perseus, Rigel | Additional options |

Test voices at: https://ai.google.dev/gemini-api/docs/speech-generation

## Output Location

All generated files are saved to `~/Videos/appmesh-tutorials/`:
- `script_*.txt` - Generated scripts
- `speech_*.wav` - TTS audio
- `tutorial_*.wav` - Full tutorial audio
- `recording_*.mp4` - Screen recordings
- `final_*.mp4` - Combined videos

Override with `APPMESH_TUTORIAL_DIR` environment variable.

## Pronunciation Dictionary

The plugin includes a pronunciation map for technical terms that TTS commonly mispronounces. Words are automatically substituted before synthesis.

**Built-in corrections:**
| Word | Pronounced As |
|------|---------------|
| CachyOS | Kay-shee OS |
| AppMesh | App Mesh |
| ARexx | Ay-Rex |
| KDE | Kay Dee Ee |
| nginx | engine-X |
| kubectl | cube-control |
| sudo | sue-doo |

**Add custom pronunciations** in `server/plugins/tts.php`:
```php
const PRONUNCIATION_MAP = [
    'CachyOS' => 'Kay-shee OS',
    'YourTerm' => 'phonetic spelling',
    // ...
];
```

## Style Prompt Tips

The style prompt significantly affects delivery. Examples:

| Style | Result |
|-------|--------|
| `Neutral mid-Atlantic accent, clear and professional` | Best for tutorials |
| `Calm documentary narrator` | Measured, informative |
| `Enthusiastic and engaging` | More energy, emphasis |
| `Slow and deliberate, pausing between sentences` | Easier to follow |

Avoid: Australian/British accents for international audience (unless intended).
