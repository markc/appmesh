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

        /// Verbose debug output
        #[arg(short = 'v', long = "verbose")]
        verbose: bool,
    },
    /// Send a key combo (e.g. ctrl+v, enter, alt+tab)
    Key {
        /// The key combo to send
        combo: String,

        /// Inter-key delay in milliseconds
        #[arg(short = 'd', long = "delay", default_value = "5")]
        delay_ms: u64,
    },
}

fn main() {
    let cli = Cli::parse();

    match cli.command {
        Command::Type { delay_ms, verbose: _ } => {
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
    }
}
