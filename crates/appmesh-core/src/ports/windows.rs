use std::collections::HashMap;

use crate::port::*;

/// Windows port — KWin window management via D-Bus scripting.
pub struct WindowsPort {
    rt: tokio::runtime::Runtime,
    connection: zbus::Connection,
}

impl WindowsPort {
    pub fn new() -> Result<Self, Box<dyn std::error::Error>> {
        let rt = tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?;
        let connection = rt.block_on(zbus::Connection::session())?;
        Ok(Self { rt, connection })
    }

    /// Run a KWin script and return its load ID.
    fn run_kwin_script(&self, script: &str) -> Result<i32, PortError> {
        self.rt.block_on(async {
            // Write script to temp file (unique name to avoid KWin script caching)
            let tmp = format!("/tmp/appmesh_kwin_{}_{}.js", std::process::id(),
                std::time::SystemTime::now()
                    .duration_since(std::time::UNIX_EPOCH)
                    .map(|d| d.as_nanos())
                    .unwrap_or(0));
            std::fs::write(&tmp, script)
                .map_err(|e| PortError { code: -1, message: e.to_string() })?;

            let proxy = zbus::Proxy::new(
                &self.connection,
                "org.kde.KWin",
                "/Scripting",
                "org.kde.kwin.Scripting",
            )
            .await
            .map_err(|e| PortError { code: -1, message: e.to_string() })?;

            // loadScript returns script ID
            let reply: zbus::Message = proxy.call_method("loadScript", &(&tmp,)).await
                .map_err(|e| PortError { code: -1, message: e.to_string() })?;
            let body = reply.body();
            let script_id: i32 = body.deserialize()
                .map_err(|e| PortError { code: -1, message: e.to_string() })?;

            // Run the script
            let script_path = format!("/Scripting/Script{}", script_id);
            let script_obj_path = zbus::zvariant::ObjectPath::try_from(script_path.as_str())
                .map_err(|e| PortError { code: -1, message: format!("invalid path: {}", e) })?;
            let script_proxy = zbus::Proxy::new(
                &self.connection,
                "org.kde.KWin",
                script_obj_path,
                "org.kde.kwin.Script",
            )
            .await
            .map_err(|e| PortError { code: -1, message: e.to_string() })?;

            let _: () = script_proxy.call("run", &()).await
                .map_err(|e| PortError { code: -1, message: e.to_string() })?;

            // Brief pause for script execution
            tokio::time::sleep(std::time::Duration::from_millis(100)).await;

            let _: () = script_proxy.call("stop", &()).await
                .map_err(|e| PortError { code: -1, message: e.to_string() })?;

            let _ = std::fs::remove_file(&tmp);

            Ok(script_id)
        })
    }

    /// List windows via KWin script that stashes results in Klipper clipboard.
    /// Uses qdbus6 subprocess for script lifecycle (proven reliable).
    fn list_windows(&self) -> Result<String, PortError> {
        // Save current clipboard so we can restore it
        let saved_clipboard = self.rt.block_on(async {
            let proxy = zbus::Proxy::new(
                &self.connection,
                "org.kde.klipper",
                "/klipper",
                "org.kde.klipper.klipper",
            ).await.ok()?;
            let reply: zbus::Message = proxy.call_method("getClipboardContents", &()).await.ok()?;
            reply.body().deserialize::<String>().ok()
        });

        let output = std::process::Command::new("sh")
            .args(["-c", r#"
                SCRIPT=$(mktemp /tmp/appmesh_kwin_XXXXXX.js)
                cat > "$SCRIPT" << 'JSEOF'
const clients = workspace.windowList();
let lines = [];
for (const c of clients) {
    if (c.caption && c.caption.length > 0) {
        lines.push([c.internalId.toString(), c.resourceClass, c.active ? '*' : ' ', c.caption].join('\t'));
    }
}
callDBus("org.kde.klipper", "/klipper", "org.kde.klipper.klipper", "setClipboardContents", lines.join('\n'));
JSEOF
                ID=$(qdbus6 org.kde.KWin /Scripting org.kde.kwin.Scripting.loadScript "$SCRIPT")
                if echo "$ID" | grep -qE '^[0-9]+$'; then
                    qdbus6 org.kde.KWin /Scripting/Script$ID org.kde.kwin.Script.run
                    sleep 0.15
                    qdbus6 org.kde.KWin /Scripting/Script$ID org.kde.kwin.Script.stop
                fi
                rm -f "$SCRIPT"
                qdbus6 org.kde.klipper /klipper org.kde.klipper.klipper.getClipboardContents
            "#])
            .output()
            .map_err(|e| PortError { code: -1, message: format!("script failed: {}", e) })?;

        let result = if output.status.success() {
            String::from_utf8_lossy(&output.stdout).trim().to_string()
        } else {
            let stderr = String::from_utf8_lossy(&output.stderr);
            return Err(PortError { code: -1, message: format!("window list failed: {}", stderr) });
        };

        // Restore clipboard
        if let Some(saved) = saved_clipboard {
            let _ = self.rt.block_on(async {
                let proxy = zbus::Proxy::new(
                    &self.connection,
                    "org.kde.klipper",
                    "/klipper",
                    "org.kde.klipper.klipper",
                ).await.ok()?;
                let _: zbus::Message = proxy.call_method("setClipboardContents", &(&saved,)).await.ok()?;
                Some(())
            });
        }

        Ok(result)
    }
}

// Safety: WindowsPort is only used from one thread at a time via single-threaded FFI
unsafe impl Send for WindowsPort {}

impl AppMeshPort for WindowsPort {
    fn name(&self) -> &str {
        "windows"
    }

    fn commands(&self) -> Vec<CommandDef> {
        vec![
            CommandDef {
                name: "list".into(),
                description: "List all open windows with IDs, titles, and apps".into(),
                params: vec![],
            },
            CommandDef {
                name: "activate".into(),
                description: "Activate (focus) a window by its ID".into(),
                params: vec![
                    ParamDef { name: "id".into(), description: "Window ID (UUID or numeric)".into(), required: true },
                ],
            },
        ]
    }

    fn execute(&self, cmd: &str, args: &HashMap<String, String>) -> PortResult {
        match cmd {
            "list" => {
                let output = self.list_windows()?;
                Ok(PortValue::String(output))
            }
            "activate" => {
                let id = args.get("id").ok_or_else(|| PortError {
                    code: -1,
                    message: "missing 'id' argument".into(),
                })?;

                // Try numeric ID with wmctrl first
                if id.chars().all(|c| c.is_ascii_digit() || c == 'x' || c == 'X') {
                    let output = std::process::Command::new("wmctrl")
                        .args(["-i", "-a", id])
                        .output();
                    if let Ok(out) = output {
                        if out.status.success() {
                            return Ok(PortValue::String(format!("activated window: {}", id)));
                        }
                    }
                }

                // Fall back to KWin script for UUID-style IDs
                let escaped_id = serde_json::to_string(id).unwrap_or_default();
                let script = format!(
                    r#"workspace.windowList().find(w => w.internalId.toString() === {})?.activate();"#,
                    escaped_id
                );
                self.run_kwin_script(&script)?;
                Ok(PortValue::String(format!("activated window: {}", id)))
            }
            other => Err(PortError {
                code: -1,
                message: format!("unknown command: {}", other),
            }),
        }
    }
}
