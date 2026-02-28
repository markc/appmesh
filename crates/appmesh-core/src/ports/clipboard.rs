use std::collections::HashMap;

use crate::port::*;

/// Clipboard port — Klipper get/set via D-Bus.
pub struct ClipboardPort {
    rt: tokio::runtime::Runtime,
    connection: zbus::Connection,
}

impl ClipboardPort {
    pub fn new() -> Result<Self, Box<dyn std::error::Error>> {
        let rt = tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?;
        let connection = rt.block_on(zbus::Connection::session())?;
        Ok(Self { rt, connection })
    }

    fn call_klipper(&self, method: &str, args: Option<&str>) -> Result<String, PortError> {
        self.rt.block_on(async {
            let proxy = zbus::Proxy::new(
                &self.connection,
                "org.kde.klipper",
                "/klipper",
                "org.kde.klipper.klipper",
            )
            .await
            .map_err(|e| PortError { code: -1, message: e.to_string() })?;

            let reply: zbus::Message = if let Some(text) = args {
                proxy.call_method(method, &(text,)).await
            } else {
                proxy.call_method(method, &()).await
            }
            .map_err(|e| PortError { code: -1, message: e.to_string() })?;

            let body = reply.body();
            match body.deserialize::<String>() {
                Ok(s) => Ok(s),
                Err(_) => Ok(String::new()),
            }
        })
    }
}

// Safety: ClipboardPort is only used from one thread at a time via Mutex or single-threaded FFI
unsafe impl Send for ClipboardPort {}

impl AppMeshPort for ClipboardPort {
    fn name(&self) -> &str {
        "clipboard"
    }

    fn commands(&self) -> Vec<CommandDef> {
        vec![
            CommandDef {
                name: "get".into(),
                description: "Get current clipboard contents from Klipper".into(),
                params: vec![],
            },
            CommandDef {
                name: "set".into(),
                description: "Set clipboard contents in Klipper".into(),
                params: vec![
                    ParamDef { name: "text".into(), description: "Text to copy to clipboard".into(), required: true },
                ],
            },
        ]
    }

    fn execute(&self, cmd: &str, args: &HashMap<String, String>) -> PortResult {
        match cmd {
            "get" => {
                let contents = self.call_klipper("getClipboardContents", None)?;
                Ok(PortValue::String(contents))
            }
            "set" => {
                let text = args.get("text").ok_or_else(|| PortError {
                    code: -1,
                    message: "missing 'text' argument".into(),
                })?;
                self.call_klipper("setClipboardContents", Some(text))?;
                Ok(PortValue::String(format!("clipboard set ({} chars)", text.len())))
            }
            other => Err(PortError {
                code: -1,
                message: format!("unknown command: {}", other),
            }),
        }
    }
}
