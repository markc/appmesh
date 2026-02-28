use std::collections::HashMap;
use std::sync::Mutex;

use crate::input::InputHandle;
use crate::port::*;

/// Input port — keyboard injection via KWin EIS.
pub struct InputPort {
    handle: Mutex<InputHandle>,
}

impl InputPort {
    pub fn new() -> Result<Self, Box<dyn std::error::Error>> {
        let handle = InputHandle::new()?;
        Ok(Self {
            handle: Mutex::new(handle),
        })
    }
}

impl AppMeshPort for InputPort {
    fn name(&self) -> &str {
        "input"
    }

    fn commands(&self) -> Vec<CommandDef> {
        vec![
            CommandDef {
                name: "type_text".into(),
                description: "Type text into the focused window".into(),
                params: vec![
                    ParamDef { name: "text".into(), description: "Text to type".into(), required: true },
                    ParamDef { name: "delay_us".into(), description: "Inter-key delay in microseconds".into(), required: false },
                ],
            },
            CommandDef {
                name: "send_key".into(),
                description: "Send a key combo (e.g. ctrl+v, enter)".into(),
                params: vec![
                    ParamDef { name: "combo".into(), description: "Key combo string".into(), required: true },
                    ParamDef { name: "delay_us".into(), description: "Inter-key delay in microseconds".into(), required: false },
                ],
            },
        ]
    }

    fn execute(&self, cmd: &str, args: &HashMap<String, String>) -> PortResult {
        let mut handle = self.handle.lock().map_err(|e| PortError {
            code: -1,
            message: format!("lock poisoned: {}", e),
        })?;

        let delay_us: u64 = args
            .get("delay_us")
            .and_then(|v| v.parse().ok())
            .unwrap_or(5000);

        match cmd {
            "type_text" => {
                let text = args.get("text").ok_or_else(|| PortError {
                    code: -1,
                    message: "missing 'text' argument".into(),
                })?;
                handle.type_text(text, delay_us).map_err(|e| PortError {
                    code: -1,
                    message: e.to_string(),
                })?;
                Ok(PortValue::String(format!("typed {} characters", text.len())))
            }
            "send_key" => {
                let combo = args.get("combo").ok_or_else(|| PortError {
                    code: -1,
                    message: "missing 'combo' argument".into(),
                })?;
                handle.send_key(combo, delay_us).map_err(|e| PortError {
                    code: -1,
                    message: e.to_string(),
                })?;
                Ok(PortValue::String(format!("sent key combo: {}", combo)))
            }
            other => Err(PortError {
                code: -1,
                message: format!("unknown command: {}", other),
            }),
        }
    }
}
