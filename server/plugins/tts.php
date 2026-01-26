<?php
/**
 * AppMesh TTS Plugin - Tutorial Video Generation
 *
 * Generate coding tutorials with AI voice using Google Gemini TTS.
 * Enables creating video tutorials for AppMesh and other projects.
 *
 * Requirements: ffmpeg, wl-screenrec (Wayland)
 * Configuration: Set GEMINI_API_KEY in server/.env (copy from .env.example)
 * Temp files: /tmp/appmesh-tts-*
 */

// ============================================================================
// Pronunciation Dictionary
// ============================================================================

const PRONUNCIATION_MAP = [
    'CachyOS' => 'Kay-shee OS',
    'cachyos' => 'kay-shee OS',
    'AppMesh' => 'App Mesh',
    'appmesh' => 'app mesh',
    'ARexx' => 'Ay-Rex',
    'arexx' => 'ay-rex',
    'D-Bus' => 'Dee-Bus',
    'd-bus' => 'dee-bus',
    'dbus' => 'dee-bus',
    'KDE' => 'Kay Dee Ee',
    'GNOME' => 'Guh-nome',
    'CLI' => 'command line',
    'API' => 'A P I',
    'MCP' => 'M C P',
    'OSC' => 'O S C',
    'MIDI' => 'middy',
    'stdin' => 'standard in',
    'stdout' => 'standard out',
    'stderr' => 'standard error',
    'sudo' => 'sue-doo',
    'nginx' => 'engine-X',
    'kubectl' => 'cube-control',
];

function appmesh_tts_pronounce(string $text): string {
    return str_replace(
        array_keys(PRONUNCIATION_MAP),
        array_values(PRONUNCIATION_MAP),
        $text
    );
}

// ============================================================================
// Helpers
// ============================================================================

function appmesh_tts_output_dir(): string {
    $dir = appmesh_env('APPMESH_TUTORIAL_DIR', getenv('HOME') . '/Videos/appmesh-tutorials');
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

function gemini_request(string $model, array $payload): array {
    $apiKey = appmesh_env('GEMINI_API_KEY');
    if (!$apiKey) return ['error' => 'No API key. Set GEMINI_API_KEY in server/.env (see .env.example)'];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 120,
    ]);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) return ['error' => $error];
    if ($code !== 200) return ['error' => "HTTP {$code}"];

    return json_decode($response, true) ?? ['error' => 'Invalid JSON'];
}

function gemini_text(string $prompt): string {
    $result = gemini_request('gemini-2.0-flash', [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 4000],
    ]);

    return $result['error'] ?? $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Error: No response';
}

function gemini_tts(string $text, string $voice, string $style, string $outPath): string {
    $text = appmesh_tts_pronounce($text);
    $prompt = $style ? "Style: {$style}\n\n{$text}" : $text;

    $result = gemini_request('gemini-2.5-flash-preview-tts', [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'responseModalities' => ['AUDIO'],
            'speechConfig' => [
                'voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voice]],
                'languageCode' => 'en-US',
            ],
        ],
    ]);

    if (isset($result['error'])) return "Error: {$result['error']}";

    $audio = $result['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;
    if (!$audio) return 'Error: No audio data';

    // Convert PCM to WAV (temp file in /tmp)
    $raw = '/tmp/appmesh-tts-' . uniqid() . '.raw';
    file_put_contents($raw, base64_decode($audio));
    exec("ffmpeg -y -f s16le -ar 24000 -ac 1 -i " . escapeshellarg($raw) . " " . escapeshellarg($outPath) . " 2>&1", $_, $code);
    @unlink($raw);

    return $code === 0 ? "Audio: {$outPath}" : 'Error: FFmpeg failed';
}

// ============================================================================
// Tool Definitions
// ============================================================================

return [
    'appmesh_tts' => new Tool(
        description: 'Convert text to speech using Gemini TTS. Returns path to WAV file.',
        inputSchema: schema([
            'text' => prop('string', 'Text to speak (or path to .txt file)'),
            'voice' => prop('string', 'Voice: Kore, Charon, Puck, Fenrir, Zephyr, etc. Default: Kore'),
            'style' => prop('string', 'Delivery style, e.g. "mid-Atlantic accent, professional"'),
        ], ['text']),
        handler: function(array $a): string {
            $text = $a['text'];
            if (str_ends_with($text, '.txt') && file_exists($text)) {
                $text = file_get_contents($text);
            }
            $out = appmesh_tts_output_dir() . '/speech_' . time() . '.wav';
            return gemini_tts($text, $a['voice'] ?? 'Kore', $a['style'] ?? '', $out);
        }
    ),

    'appmesh_tutorial_script' => new Tool(
        description: 'Generate a tutorial script for a coding video using AI',
        inputSchema: schema([
            'topic' => prop('string', 'What the tutorial covers'),
            'duration' => prop('string', 'Target length, e.g. "3 minutes". Default: 3 minutes'),
            'audience' => prop('string', 'Target audience. Default: developers familiar with Linux'),
        ], ['topic']),
        handler: function(array $a): string {
            $topic = $a['topic'];
            $duration = $a['duration'] ?? '3 minutes';
            $audience = $a['audience'] ?? 'developers familiar with Linux';

            $script = gemini_text(<<<PROMPT
                Write a tutorial script for a coding video about: {$topic}

                Requirements:
                - Duration: {$duration} | Audience: {$audience}
                - Include [PAUSE] markers and [ACTION] cues like [SHOW TERMINAL]
                - Start with a hook, end with summary
                - American English spelling
                - Plain text for TTS, no timestamps
                PROMPT);

            if (str_starts_with($script, 'Error:')) return $script;

            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', substr($topic, 0, 30)));
            $path = appmesh_tts_output_dir() . "/script_{$slug}_" . time() . '.txt';
            file_put_contents($path, $script);

            return "Script: {$path}\n\n{$script}";
        }
    ),

    'appmesh_tutorial_full' => new Tool(
        description: 'Generate complete tutorial: script + audio. Returns paths to both files.',
        inputSchema: schema([
            'topic' => prop('string', 'Tutorial topic'),
            'voice' => prop('string', 'TTS voice. Default: Kore'),
            'style' => prop('string', 'Voice style. Default: Neutral mid-Atlantic, professional'),
        ], ['topic']),
        handler: function(array $a): string {
            $dir = appmesh_tts_output_dir();
            $topic = $a['topic'];
            $voice = $a['voice'] ?? 'Kore';
            $style = $a['style'] ?? 'Neutral mid-Atlantic accent, clear and professional, like a calm documentary narrator';
            $ts = time();
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', substr($topic, 0, 30)));

            // Generate script
            $script = gemini_text(<<<PROMPT
                Write a 3-minute tutorial script about: {$topic}
                Include [PAUSE] markers and [ACTION] cues. American English. Plain text for TTS.
                PROMPT);

            if (str_starts_with($script, 'Error:')) return $script;

            $scriptPath = "{$dir}/tutorial_{$slug}_{$ts}.txt";
            file_put_contents($scriptPath, $script);

            // Clean for TTS and generate audio
            $ttsText = str_replace('[PAUSE]', '...', preg_replace('/\[[A-Z][^\]]*\]/', '', $script));
            $audioPath = "{$dir}/tutorial_{$slug}_{$ts}.wav";
            $audioResult = gemini_tts($ttsText, $voice, $style, $audioPath);

            return "Script: {$scriptPath}\n{$audioResult}\n\nNext: Use appmesh_screen_record to capture demo, then appmesh_video_combine";
        }
    ),

    'appmesh_screen_record' => new Tool(
        description: 'Control screen recording for tutorial videos',
        inputSchema: schema([
            'action' => prop('string', 'start, stop, or status'),
        ], ['action']),
        handler: function(array $a): string {
            $script = __DIR__ . '/../scripts/screen-record.sh';
            $action = $a['action'];
            $uid = posix_getuid();
            $runtimeDir = "/run/user/{$uid}";

            // Use wrapper script for reliable background process management
            // Check for Wayland via multiple methods since MCP server may not have XDG vars
            $isWayland = getenv('XDG_SESSION_TYPE') === 'wayland'
                || getenv('WAYLAND_DISPLAY')
                || file_exists("{$runtimeDir}/wayland-0");
            if (file_exists($script) && $isWayland) {
                $out = appmesh_tts_output_dir() . '/recording_' . time() . '.mp4';

                // Ensure display environment is set for the subprocess
                $envPrefix = "WAYLAND_DISPLAY=wayland-0 XDG_RUNTIME_DIR={$runtimeDir} DBUS_SESSION_BUS_ADDRESS=unix:path={$runtimeDir}/bus";

                $cmd = match($action) {
                    'start' => "{$envPrefix} " . escapeshellarg($script) . " start " . escapeshellarg($out),
                    'stop' => escapeshellarg($script) . " stop",
                    'status' => escapeshellarg($script) . " status",
                    default => null,
                };
                return $cmd ? trim(shell_exec($cmd) ?? 'Error') : 'Error: Use start, stop, or status';
            }

            // Fallback for X11 or if script not found
            $pidFile = '/tmp/appmesh-recording.pid';

            return match($action) {
                'start' => (function() use ($pidFile) {
                    if (file_exists($pidFile)) return 'Error: Recording in progress. Use stop first.';
                    $out = appmesh_tts_output_dir() . '/recording_' . time() . '.mp4';
                    $size = trim(shell_exec("xdpyinfo 2>/dev/null | grep dimensions | awk '{print \$2}'") ?? '1920x1080');
                    $cmd = "ffmpeg -f x11grab -video_size {$size} -framerate 30 -i :0 -c:v libx264 -preset ultrafast " . escapeshellarg($out) . " </dev/null >/dev/null 2>&1 & echo \$!";
                    $p = trim(shell_exec($cmd) ?? '');
                    if (!$p || !is_numeric($p)) return 'Error: Failed to start recording';
                    file_put_contents($pidFile, "{$p}\n{$out}");
                    return "Recording started (PID: {$p})\nOutput: {$out}";
                })(),

                'stop' => (function() use ($pidFile) {
                    if (!file_exists($pidFile)) return 'No recording in progress';
                    [$p, $out] = explode("\n", file_get_contents($pidFile));
                    exec("kill -INT " . intval($p) . " 2>/dev/null");
                    sleep(2);
                    exec("kill -9 " . intval($p) . " 2>/dev/null");
                    unlink($pidFile);
                    return "Recording stopped: {$out}";
                })(),

                'status' => file_exists($pidFile)
                    ? "Recording: " . explode("\n", file_get_contents($pidFile))[1]
                    : 'No recording in progress',

                default => 'Error: Use start, stop, or status',
            };
        }
    ),

    'appmesh_video_combine' => new Tool(
        description: 'Combine screen recording with audio narration into final tutorial video',
        inputSchema: schema([
            'video' => prop('string', 'Path to video file'),
            'audio' => prop('string', 'Path to audio file'),
        ], ['video', 'audio']),
        handler: function(array $a): string {
            $dir = appmesh_tts_output_dir();
            $v = $a['video'];
            $au = $a['audio'];

            // Resolve relative paths in output dir
            if (!str_starts_with($v, '/')) $v = file_exists("{$dir}/{$v}") ? "{$dir}/{$v}" : $v;
            if (!str_starts_with($au, '/')) $au = file_exists("{$dir}/{$au}") ? "{$dir}/{$au}" : $au;

            if (!file_exists($v)) return "Error: Video not found: {$v}";
            if (!file_exists($au)) return "Error: Audio not found: {$au}";

            $out = "{$dir}/final_" . time() . '.mp4';
            exec("ffmpeg -y -i " . escapeshellarg($v) . " -i " . escapeshellarg($au) . " -c:v copy -c:a aac -map 0:v:0 -map 1:a:0 -shortest " . escapeshellarg($out) . " 2>&1", $_, $code);

            return $code === 0 ? "Final video: {$out}" : 'Error: FFmpeg combine failed';
        }
    ),

    'appmesh_tts_voices' => new Tool(
        description: 'List available Gemini TTS voices',
        inputSchema: schema(),
        handler: fn() => "Voices: Kore (clear female), Charon (deep male), Puck (energetic), Fenrir (authoritative), Zephyr (soft), Aoede, Leda, Orus, Perseus, Rigel\n\nTest at: https://ai.google.dev/gemini-api/docs/speech-generation"
    ),
];
