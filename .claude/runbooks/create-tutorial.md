# Runbook: Create Tutorial Video

## Purpose

Create a narrated tutorial video using AppMesh's TTS and screen recording capabilities.

## Prerequisites

- Gemini API key configured
- `ffmpeg` installed
- Topic prepared

## Procedure

### 1. Generate Script

```bash
/tts script "Your tutorial topic"
```

Review the generated script in the output. Edit if needed.

### 2. Practice the Demo

Run through the steps you'll demonstrate to ensure smooth recording.

### 3. Start Screen Recording

```bash
/tts record start
```

This starts ffmpeg recording the full screen.

### 4. Perform the Demo

Execute the steps from your script. Take your time - you can always speed up later.

### 5. Stop Recording

```bash
/tts record stop
```

Note the path to the recorded video file.

### 6. Generate Narration

Option A - From the script file:
```bash
/tts speak /path/to/script.txt --voice=Kore
```

Option B - Full tutorial generation:
```bash
/tts tutorial "topic" --voice=Puck
```

### 7. Combine Video and Audio

```bash
/tts combine /path/to/video.mp4 /path/to/narration.wav
```

Output is saved to the same directory with `_final` suffix.

### 8. Review and Adjust

- Check audio/video sync
- Trim start/end if needed
- Add intro/outro if desired

## Voice Selection

| Voice | Best For |
|-------|----------|
| Kore | Technical tutorials, professional |
| Puck | Friendly, casual walkthroughs |
| Charon | Deep, authoritative presentations |
| Zephyr | Light, energetic demos |

Run `/tts voices` to see all options.

## Tips

- Keep demos under 5 minutes for best results
- Use a consistent desktop theme
- Close unnecessary windows
- Disable notifications during recording

## Troubleshooting

### Recording won't start
- Check ffmpeg is installed: `which ffmpeg`
- Ensure /tmp is writable

### Audio too fast/slow
- TTS generates at natural pace
- Use `ffmpeg -i video.mp4 -filter:a "atempo=0.9"` to adjust

### Poor video quality
- Recording uses `-crf 23` by default
- Edit `tts.php` to adjust quality settings

## Output Locations

- Scripts: `/tmp/appmesh_tutorial_*.md`
- Audio: `/tmp/appmesh_tts_*.wav`
- Video: `/tmp/appmesh_recording_*.mp4`
- Final: `/tmp/appmesh_recording_*_final.mp4`
