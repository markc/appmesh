use std::collections::HashMap;

use crate::port::*;

/// Notify port — desktop notifications via freedesktop D-Bus interface.
pub struct NotifyPort {
    rt: tokio::runtime::Runtime,
    connection: zbus::Connection,
}

impl NotifyPort {
    pub fn new() -> Result<Self, Box<dyn std::error::Error>> {
        let rt = tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?;
        let connection = rt.block_on(zbus::Connection::session())?;
        Ok(Self { rt, connection })
    }

    fn send_notification(
        &self,
        title: &str,
        body: &str,
        icon: &str,
        timeout_ms: i32,
    ) -> Result<u32, PortError> {
        self.rt.block_on(async {
            let proxy = zbus::Proxy::new(
                &self.connection,
                "org.freedesktop.Notifications",
                "/org/freedesktop/Notifications",
                "org.freedesktop.Notifications",
            )
            .await
            .map_err(|e| PortError { code: -1, message: e.to_string() })?;

            // Notify(app_name, replaces_id, icon, summary, body, actions, hints, timeout)
            let actions: Vec<&str> = vec![];
            let hints: HashMap<&str, zbus::zvariant::Value> = HashMap::new();

            let reply: zbus::Message = proxy
                .call_method("Notify", &("AppMesh", 0u32, icon, title, body, actions, hints, timeout_ms))
                .await
                .map_err(|e| PortError { code: -1, message: e.to_string() })?;

            let body = reply.body();
            let notification_id: u32 = body
                .deserialize()
                .map_err(|e| PortError { code: -1, message: e.to_string() })?;

            Ok(notification_id)
        })
    }
}

// Safety: NotifyPort is only used from one thread at a time via single-threaded FFI
unsafe impl Send for NotifyPort {}

impl AppMeshPort for NotifyPort {
    fn name(&self) -> &str {
        "notify"
    }

    fn commands(&self) -> Vec<CommandDef> {
        vec![
            CommandDef {
                name: "send".into(),
                description: "Send a desktop notification".into(),
                params: vec![
                    ParamDef { name: "title".into(), description: "Notification title".into(), required: true },
                    ParamDef { name: "body".into(), description: "Notification body text".into(), required: false },
                    ParamDef { name: "icon".into(), description: "Icon name (e.g. dialog-information)".into(), required: false },
                    ParamDef { name: "timeout".into(), description: "Timeout in ms (-1=server default, 0=never)".into(), required: false },
                ],
            },
        ]
    }

    fn execute(&self, cmd: &str, args: &HashMap<String, String>) -> PortResult {
        match cmd {
            "send" => {
                let title = args.get("title").ok_or_else(|| PortError {
                    code: -1,
                    message: "missing 'title' argument".into(),
                })?;
                let body = args.get("body").map(|s| s.as_str()).unwrap_or("");
                let icon = args.get("icon").map(|s| s.as_str()).unwrap_or("dialog-information");
                let timeout_ms: i32 = args
                    .get("timeout")
                    .and_then(|v| v.parse().ok())
                    .unwrap_or(-1);

                let id = self.send_notification(title, body, icon, timeout_ms)?;
                Ok(PortValue::String(format!("notification sent (id: {})", id)))
            }
            other => Err(PortError {
                code: -1,
                message: format!("unknown command: {}", other),
            }),
        }
    }
}
