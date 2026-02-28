use std::collections::HashMap;

use crate::port::*;

/// Screenshot port — Spectacle screen capture.
pub struct ScreenshotPort;

impl ScreenshotPort {
    pub fn new() -> Result<Self, Box<dyn std::error::Error>> {
        Ok(Self)
    }

    fn output_path() -> String {
        let uid = unsafe { libc::getuid() };
        let dir = format!("/run/user/{}/appmesh", uid);
        let _ = std::fs::create_dir_all(&dir);
        let ts = std::time::SystemTime::now()
            .duration_since(std::time::UNIX_EPOCH)
            .map(|d| d.as_millis())
            .unwrap_or(0);
        format!("{}/screenshot_{}.png", dir, ts)
    }
}

impl AppMeshPort for ScreenshotPort {
    fn name(&self) -> &str {
        "screenshot"
    }

    fn commands(&self) -> Vec<CommandDef> {
        vec![
            CommandDef {
                name: "take".into(),
                description: "Take a screenshot and return the file path".into(),
                params: vec![
                    ParamDef {
                        name: "mode".into(),
                        description: "fullscreen, activewindow, or region".into(),
                        required: false,
                    },
                ],
            },
        ]
    }

    fn execute(&self, cmd: &str, args: &HashMap<String, String>) -> PortResult {
        match cmd {
            "take" => {
                let mode = args.get("mode").map(|s| s.as_str()).unwrap_or("fullscreen");
                let flag = match mode {
                    "activewindow" => "-a",
                    "region" => "-r",
                    _ => "-f",
                };

                let path = Self::output_path();

                let output = std::process::Command::new("spectacle")
                    .args([flag, "-b", "-n", "-o", &path])
                    .output()
                    .map_err(|e| PortError {
                        code: -1,
                        message: format!("spectacle: {}", e),
                    })?;

                if !output.status.success() {
                    let stderr = String::from_utf8_lossy(&output.stderr);
                    return Err(PortError {
                        code: -1,
                        message: format!("spectacle failed: {}", stderr),
                    });
                }

                // Brief wait for file to be written
                std::thread::sleep(std::time::Duration::from_millis(300));

                if std::path::Path::new(&path).exists() {
                    Ok(PortValue::String(path))
                } else {
                    Ok(PortValue::String(format!("screenshot may be at: {}", path)))
                }
            }
            other => Err(PortError {
                code: -1,
                message: format!("unknown command: {}", other),
            }),
        }
    }
}
