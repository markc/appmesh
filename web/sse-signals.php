<?php
declare(strict_types=1);
/**
 * AppMesh SSE Signal Stream - D-Bus Signals via Server-Sent Events
 *
 * Pure PHP SSE implementation - NO LIBRARIES REQUIRED.
 * Works with HTMX sse extension for real-time D-Bus signal streaming.
 *
 * This script runs as a long-lived connection, streaming D-Bus signals
 * to the browser as they occur. No polling!
 *
 * Requirements:
 * - PECL dbus extension (for signal subscription)
 * - PHP with output buffering control
 *
 * Usage:
 *   Browser connects to: /sse/signals
 *   HTMX: <div hx-ext="sse" sse-connect="/sse/signals" sse-swap="clipboard">
 *
 * For development without PECL dbus, this will simulate events.
 */

// Disable output buffering - critical for SSE
if (ob_get_level()) ob_end_clean();

// Prevent nginx/apache buffering
if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}

// SSE Headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx: disable buffering

// Prevent PHP timeout (or set to a reasonable limit)
set_time_limit(0);
ignore_user_abort(false);

/**
 * Send an SSE event to the client
 */
function sendEvent(string $event, string $data, ?string $id = null): void
{
    if ($id !== null) {
        echo "id: {$id}\n";
    }
    echo "event: {$event}\n";

    // SSE requires each line of data to be prefixed with "data: "
    // For HTML, we need to send it as a single line or encode newlines
    $singleLine = str_replace(["\r\n", "\r", "\n"], '', $data);
    echo "data: {$singleLine}\n";
    echo "\n"; // End of event (blank line)

    flush();
}

/**
 * Send a keepalive comment (prevents connection timeout)
 */
function sendKeepalive(): void
{
    echo ": keepalive " . date('H:i:s') . "\n\n";
    flush();
}

// Check if PECL dbus is available
$hasDbusExtension = extension_loaded('dbus');

if ($hasDbusExtension) {
    // ============================================
    // PRODUCTION MODE: Real D-Bus signal listening
    // ============================================

    $dbus = new Dbus(Dbus::BUS_SESSION);

    // Subscribe to signals we want to stream
    $dbus->addWatch(Dbus::BUS_SESSION, 'org.kde.klipper', '/klipper',
                    'org.kde.klipper.klipper', 'clipboardHistoryUpdated');

    $dbus->addWatch(Dbus::BUS_SESSION, 'org.freedesktop.Notifications',
                    '/org/freedesktop/Notifications',
                    'org.freedesktop.Notifications', 'NotificationClosed');

    $dbus->addWatch(Dbus::BUS_SESSION, 'org.kde.ActivityManager',
                    '/ActivityManager/Activities',
                    'org.kde.ActivityManager.Activities', 'CurrentActivityChanged');

    // Send initial connection event
    sendEvent('connected', '<div class="sse-status connected">Connected to D-Bus signal stream</div>');

    $lastKeepalive = time();

    // Main event loop
    while (!connection_aborted()) {
        // Wait for D-Bus signals (100ms timeout)
        $signal = $dbus->waitLoop(100);

        if ($signal instanceof DbusSignal) {
            $timestamp = date('H:i:s');

            if ($signal->matches('org.kde.klipper.klipper', 'clipboardHistoryUpdated')) {
                // Get clipboard content
                $proxy = $dbus->createProxy('org.kde.klipper', '/klipper', 'org.kde.klipper.klipper');
                $content = (string)$proxy->getClipboardContents();
                $preview = htmlspecialchars(substr($content, 0, 100));

                $html = "<div class=\"signal clipboard\"><span class=\"time\">{$timestamp}</span> Clipboard: {$preview}</div>";
                sendEvent('clipboard', $html);
            }

            elseif ($signal->matches('org.freedesktop.Notifications', 'NotificationClosed')) {
                $data = $signal->getData();
                $id = $data[0] ?? '?';

                $html = "<div class=\"signal notification\"><span class=\"time\">{$timestamp}</span> Notification #{$id} closed</div>";
                sendEvent('notification', $html);
            }

            elseif ($signal->matches('org.kde.ActivityManager.Activities', 'CurrentActivityChanged')) {
                $data = $signal->getData();
                $activityId = $data[0] ?? 'unknown';

                $html = "<div class=\"signal activity\"><span class=\"time\">{$timestamp}</span> Activity: {$activityId}</div>";
                sendEvent('activity', $html);
            }
        }

        // Send keepalive every 15 seconds
        if (time() - $lastKeepalive >= 15) {
            sendKeepalive();
            $lastKeepalive = time();
        }
    }

} else {
    // ============================================
    // DEVELOPMENT MODE: Simulate D-Bus signals
    // (Use this to test without PECL dbus)
    // ============================================

    sendEvent('connected', '<div class="sse-status connected">Connected (simulation mode - install PECL dbus for real signals)</div>');

    $events = [
        ['clipboard', 'Clipboard changed: "Hello World"'],
        ['notification', 'Notification: New message received'],
        ['activity', 'Activity switched to: Default'],
        ['clipboard', 'Clipboard changed: "Some code snippet..."'],
        ['notification', 'Notification closed'],
    ];

    $index = 0;
    $lastKeepalive = time();

    while (!connection_aborted()) {
        // Simulate random events every 2-5 seconds
        usleep(rand(2000000, 5000000)); // 2-5 seconds

        if (connection_aborted()) break;

        $event = $events[$index % count($events)];
        $timestamp = date('H:i:s');
        $html = "<div class=\"signal {$event[0]}\"><span class=\"time\">{$timestamp}</span> {$event[1]}</div>";

        sendEvent($event[0], $html);
        $index++;

        // Keepalive
        if (time() - $lastKeepalive >= 15) {
            sendKeepalive();
            $lastKeepalive = time();
        }
    }
}
