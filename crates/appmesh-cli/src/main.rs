use std::collections::HashMap;
use std::io::{self, Read};
use std::process;

use clap::{Parser, Subcommand};

/// AppMesh CLI — desktop automation via KWin EIS
#[derive(Parser)]
#[command(name = "appmesh")]
struct Cli {
    #[command(subcommand)]
    command: Command,
}

#[derive(Subcommand)]
enum Command {
    /// Type text from stdin into the focused window
    Type {
        /// Inter-key delay in milliseconds
        #[arg(short = 'd', long = "delay", default_value = "5")]
        delay_ms: u64,
    },
    /// Send a key combo (e.g. ctrl+v, enter, alt+tab)
    Key {
        /// The key combo to send
        combo: String,

        /// Inter-key delay in milliseconds
        #[arg(short = 'd', long = "delay", default_value = "5")]
        delay_ms: u64,
    },
    /// Execute a command on a named port
    Port {
        /// Port name (clipboard, input, notify, screenshot, windows)
        port: String,

        /// Command to execute on the port
        command: String,

        /// Arguments as key=value pairs
        #[arg(trailing_var_arg = true)]
        args: Vec<String>,
    },
    /// List available ports and their commands
    Ports,
}

fn main() {
    let cli = Cli::parse();

    match cli.command {
        Command::Type { delay_ms } => {
            let delay_us = delay_ms * 1000;

            let mut handle = match appmesh_core::input::InputHandle::new() {
                Ok(h) => h,
                Err(e) => {
                    eprintln!("appmesh: failed to connect: {}", e);
                    process::exit(1);
                }
            };

            let mut input = String::new();
            if let Err(e) = io::stdin().read_to_string(&mut input) {
                eprintln!("appmesh: failed to read stdin: {}", e);
                process::exit(1);
            }

            if let Err(e) = handle.type_text(&input, delay_us) {
                eprintln!("appmesh: typing failed: {}", e);
                process::exit(1);
            }
        }
        Command::Key { combo, delay_ms } => {
            let delay_us = delay_ms * 1000;

            let mut handle = match appmesh_core::input::InputHandle::new() {
                Ok(h) => h,
                Err(e) => {
                    eprintln!("appmesh: failed to connect: {}", e);
                    process::exit(1);
                }
            };

            if let Err(e) = handle.send_key(&combo, delay_us) {
                eprintln!("appmesh: key combo failed: {}", e);
                process::exit(1);
            }
        }
        Command::Port { port, command, args } => {
            let port_obj = match appmesh_core::ffi::open_port(&port) {
                Ok(p) => p,
                Err(e) => {
                    eprintln!("appmesh: failed to open port '{}': {}", port, e);
                    process::exit(1);
                }
            };

            // Parse key=value args into HashMap
            let mut arg_map = HashMap::new();
            for arg in &args {
                if let Some((k, v)) = arg.split_once('=') {
                    arg_map.insert(k.to_string(), v.to_string());
                } else {
                    // Treat bare args as the first required param
                    // Use the command's first param name if available
                    let commands = port_obj.commands();
                    if let Some(cmd_def) = commands.iter().find(|c| c.name == command) {
                        if let Some(param) = cmd_def.params.first() {
                            arg_map.insert(param.name.clone(), arg.clone());
                        }
                    }
                }
            }

            match port_obj.execute(&command, &arg_map) {
                Ok(value) => {
                    let output = serde_json::to_string(&value).unwrap_or_default();
                    // Print without JSON wrapping for simple strings
                    if let appmesh_core::port::PortValue::String(s) = value {
                        println!("{}", s);
                    } else {
                        println!("{}", output);
                    }
                }
                Err(e) => {
                    eprintln!("appmesh: {} {} failed: {}", port, command, e.message);
                    process::exit(1);
                }
            }
        }
        Command::Ports => {
            println!("Available ports:\n");
            for &name in appmesh_core::ffi::PORT_NAMES {
                match appmesh_core::ffi::open_port(name) {
                    Ok(port) => {
                        println!("  {}", name);
                        for cmd in port.commands() {
                            let params: Vec<String> = cmd.params.iter().map(|p| {
                                if p.required {
                                    format!("<{}>", p.name)
                                } else {
                                    format!("[{}]", p.name)
                                }
                            }).collect();
                            println!("    {} {} — {}", cmd.name, params.join(" "), cmd.description);
                        }
                    }
                    Err(e) => {
                        println!("  {} (unavailable: {})", name, e);
                    }
                }
            }
        }
    }
}
