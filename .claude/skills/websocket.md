# WebSocket Gateway

<skill>
name: ws
description: Bidirectional WebSocket gateway for real-time browser/tool communication
user-invocable: true
arguments: <action> [options]
</skill>

## Actions

- `status` - Check if gateway is running
- `start [port]` - Start gateway server (default: 8765)
- `stop` - Stop gateway server
- `broadcast <message>` - Send to all connected clients
- `clients` - Count connected clients
- `info` - Integration examples and documentation

## Examples

```bash
/ws status                           # Check server status
/ws start                            # Start on default port 8765
/ws start 9000                       # Start on custom port
/ws stop                             # Stop server
/ws broadcast "Hello clients!"       # Send to all
/ws clients                          # Count connections
/ws info                             # Integration docs
```

## Instructions

When the user invokes this skill:

1. **For `status`**: Use `appmesh_ws_status`
2. **For `start`**: Use `appmesh_ws_start` with optional port
3. **For `stop`**: Use `appmesh_ws_stop`
4. **For `broadcast`**: Use `appmesh_ws_broadcast` with message
5. **For `clients`**: Use `appmesh_ws_clients`
6. **For `info`**: Use `appmesh_ws_info`

## Browser Client Example

```javascript
const ws = new WebSocket('ws://localhost:8765');

ws.onopen = () => {
    console.log('Connected to AppMesh');
};

ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    console.log('Received:', data);
};

ws.onclose = () => console.log('Disconnected');

// Send message to server
ws.send(JSON.stringify({ action: 'hello' }));
```

## Use Cases

### Real-Time Dashboard

Stream D-Bus events to a web dashboard:
1. `/ws start`
2. Open dashboard in browser
3. Events broadcast automatically

### Remote Control

Control desktop from phone/tablet:
1. `/ws start`
2. Open web app on device
3. Send commands via WebSocket

### Tool Integration

Connect external tools to AppMesh:
1. `/ws start`
2. Tool connects via WebSocket
3. Bidirectional communication

## Structured Messages

Use `type` parameter for structured messages:

```bash
/ws broadcast "data here" --type=clipboard_change
```

Produces:
```json
{
  "type": "clipboard_change",
  "data": "data here",
  "timestamp": 1234567890
}
```

## Logs

Server log: `/tmp/appmesh-ws-PORT.log`
