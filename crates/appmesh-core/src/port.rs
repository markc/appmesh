use std::collections::HashMap;

use serde::{Deserialize, Serialize};

/// Definition of a command exposed by a port.
#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct CommandDef {
    pub name: String,
    pub description: String,
    pub params: Vec<ParamDef>,
}

/// Definition of a command parameter.
#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct ParamDef {
    pub name: String,
    pub description: String,
    pub required: bool,
}

/// Result value from a port command.
#[derive(Debug, Clone, Serialize, Deserialize)]
#[serde(untagged)]
pub enum PortValue {
    String(String),
    Int(i64),
    Bool(bool),
    List(Vec<PortValue>),
    Map(HashMap<String, PortValue>),
    Null,
}

/// Error from a port command.
#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct PortError {
    pub code: i32,
    pub message: String,
}

impl std::fmt::Display for PortError {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        write!(f, "port error {}: {}", self.code, self.message)
    }
}

impl std::error::Error for PortError {}

/// Result type for port commands.
pub type PortResult = Result<PortValue, PortError>;

/// The ARexx-style port trait — every scriptable subsystem implements this.
pub trait AppMeshPort: Send {
    /// Port name (e.g. "input", "windows", "clipboard").
    fn name(&self) -> &str;

    /// Commands this port exposes.
    fn commands(&self) -> Vec<CommandDef>;

    /// Execute a command with the given arguments.
    fn execute(&self, cmd: &str, args: &HashMap<String, String>) -> PortResult;
}
