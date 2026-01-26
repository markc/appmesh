#!/usr/bin/env -S bash --norc --noprofile
# Screen recording wrapper for AppMesh
# Usage: screen-record.sh start <output.mp4> | stop | status
# Requires: gpu-screen-recorder (paru -S gpu-screen-recorder)

PIDFILE="/tmp/appmesh-recording.pid"

case "$1" in
    start)
        if [ -f "$PIDFILE" ]; then
            echo "Error: Recording in progress"
            exit 1
        fi
        OUTPUT="${2:-/tmp/recording.mp4}"

        # Ensure display environment is set (needed when called from MCP server)
        export WAYLAND_DISPLAY="${WAYLAND_DISPLAY:-wayland-0}"
        export XDG_RUNTIME_DIR="${XDG_RUNTIME_DIR:-/run/user/$(id -u)}"
        export DBUS_SESSION_BUS_ADDRESS="${DBUS_SESSION_BUS_ADDRESS:-unix:path=/run/user/$(id -u)/bus}"

        # Daemonize: close FDs and create new session to survive parent termination
        (
            exec 0</dev/null
            exec 1>/dev/null
            exec 2>/dev/null
            setsid gpu-screen-recorder -w screen -f 30 -o "$OUTPUT" &
        ) &
        sleep 2
        # Find the actual gpu-screen-recorder PID
        PID=$(pgrep -n gpu-screen-rec)
        if [ -n "$PID" ] && ps -p "$PID" > /dev/null 2>&1; then
            echo "$PID" > "$PIDFILE"
            echo "$OUTPUT" >> "$PIDFILE"
            echo "Recording started (PID: $PID)"
            echo "Output: $OUTPUT"
        else
            echo "Error: Failed to start gpu-screen-recorder"
            exit 1
        fi
        ;;
    stop)
        if [ ! -f "$PIDFILE" ]; then
            echo "No recording in progress"
            exit 0
        fi
        PID=$(head -1 "$PIDFILE")
        OUTPUT=$(tail -1 "$PIDFILE")
        kill -INT "$PID" 2>/dev/null
        sleep 2
        kill -9 "$PID" 2>/dev/null
        rm -f "$PIDFILE"
        echo "Recording stopped: $OUTPUT"
        ;;
    status)
        if [ -f "$PIDFILE" ]; then
            echo "Recording: $(tail -1 $PIDFILE)"
        else
            echo "No recording in progress"
        fi
        ;;
    *)
        echo "Usage: $0 {start <output>|stop|status}"
        exit 1
        ;;
esac
