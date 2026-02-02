# Text-to-Speech and Tutorials

<skill>
name: tts
description: Generate speech audio and tutorial content using Gemini TTS
user-invocable: true
arguments: <action> [text-or-topic] [options]
</skill>

## Actions

- `speak <text>` - Convert text to speech
- `voices` - List available voices
- `script <topic>` - Generate tutorial script
- `tutorial <topic>` - Generate full tutorial (script + audio)
- `record start|stop|status` - Control screen recording
- `combine <video> <audio>` - Merge video and audio

## Examples

```bash
/tts speak "Hello world"                    # Basic TTS
/tts speak "Welcome" --voice=Puck           # Custom voice
/tts voices                                  # List voices
/tts script "Setting up Docker on Linux"   # Generate script
/tts tutorial "Using ripgrep"               # Full tutorial
/tts record start                           # Start recording
/tts record stop                            # Stop recording
/tts combine video.mp4 narration.wav        # Combine
```

## Instructions

When the user invokes this skill:

1. **For `speak`**: Use `appmesh_tts` with text and optional voice/style
2. **For `voices`**: Use `appmesh_tts_voices` to list options
3. **For `script`**: Use `appmesh_tutorial_script` with topic
4. **For `tutorial`**: Use `appmesh_tutorial_full` with topic
5. **For `record`**: Use `appmesh_screen_record` with action
6. **For `combine`**: Use `appmesh_video_combine` with video and audio paths

## Available Voices

Run `/tts voices` to see current list. Common options:
- Kore (default) - Clear, professional
- Puck - Friendly, casual
- Charon - Deep, authoritative
- Zephyr - Light, energetic

## Tutorial Workflow

1. `/tts script "topic"` - Generate and review script
2. `/tts record start` - Begin screen recording
3. Perform the demo
4. `/tts record stop` - End recording
5. `/tts speak script.txt` - Generate narration
6. `/tts combine video.mp4 narration.wav` - Merge

## Requirements

- Gemini API key configured
- `ffmpeg` for video combining
- Screen recorder available (uses Spectacle/ffmpeg)
