use std::collections::HashMap;
use std::ffi::{CStr, CString};
use std::os::raw::c_char;

use crate::input::InputHandle;
use crate::port::AppMeshPort;
use crate::ports::input::InputPort;
use crate::ports::clipboard::ClipboardPort;
use crate::ports::windows::WindowsPort;

/// Opaque handle type for C ABI.
pub type AppmeshHandle = *mut InputHandle;

/// Opaque port handle for C ABI.
pub type AppmeshPortHandle = *mut Box<dyn AppMeshPort>;

/// Initialize an AppMesh input handle by connecting to KWin EIS.
///
/// Returns an opaque handle on success, null on failure.
/// The handle must be freed with `appmesh_free()`.
#[no_mangle]
pub extern "C" fn appmesh_init() -> AppmeshHandle {
    match InputHandle::new() {
        Ok(handle) => Box::into_raw(Box::new(handle)),
        Err(e) => {
            eprintln!("appmesh_init failed: {}", e);
            std::ptr::null_mut()
        }
    }
}

/// Type text into the focused window.
///
/// Returns: 0 = success, -1 = error, -2 = null handle.
#[no_mangle]
pub extern "C" fn appmesh_type_text(
    handle: AppmeshHandle,
    text: *const c_char,
    delay_us: u64,
) -> i32 {
    if handle.is_null() || text.is_null() {
        return -2;
    }
    let handle = unsafe { &mut *handle };
    let text = match unsafe { CStr::from_ptr(text) }.to_str() {
        Ok(s) => s,
        Err(_) => return -1,
    };
    match handle.type_text(text, delay_us) {
        Ok(()) => 0,
        Err(e) => {
            eprintln!("appmesh_type_text failed: {}", e);
            -1
        }
    }
}

/// Send a key combo to the focused window (e.g. "ctrl+v", "enter").
///
/// Returns: 0 = success, -1 = error, -2 = null handle.
#[no_mangle]
pub extern "C" fn appmesh_send_key(
    handle: AppmeshHandle,
    combo: *const c_char,
    delay_us: u64,
) -> i32 {
    if handle.is_null() || combo.is_null() {
        return -2;
    }
    let handle = unsafe { &mut *handle };
    let combo = match unsafe { CStr::from_ptr(combo) }.to_str() {
        Ok(s) => s,
        Err(_) => return -1,
    };
    match handle.send_key(combo, delay_us) {
        Ok(()) => 0,
        Err(e) => {
            eprintln!("appmesh_send_key failed: {}", e);
            -1
        }
    }
}

/// Free an AppMesh handle. Safe to call with null.
#[no_mangle]
pub extern "C" fn appmesh_free(handle: AppmeshHandle) {
    if !handle.is_null() {
        unsafe {
            drop(Box::from_raw(handle));
        }
    }
}

// ============================================================================
// Port-level FFI — generic ARexx-style command dispatch
// ============================================================================

/// Open a port by name. Supported: "input", "clipboard", "windows".
///
/// Returns opaque port handle on success, null on failure.
#[no_mangle]
pub extern "C" fn appmesh_port_open(name: *const c_char) -> AppmeshPortHandle {
    if name.is_null() {
        return std::ptr::null_mut();
    }
    let name = match unsafe { CStr::from_ptr(name) }.to_str() {
        Ok(s) => s,
        Err(_) => return std::ptr::null_mut(),
    };

    let port: Box<dyn AppMeshPort> = match name {
        "input" => match InputPort::new() {
            Ok(p) => Box::new(p),
            Err(e) => {
                eprintln!("appmesh_port_open(input) failed: {}", e);
                return std::ptr::null_mut();
            }
        },
        "clipboard" => match ClipboardPort::new() {
            Ok(p) => Box::new(p),
            Err(e) => {
                eprintln!("appmesh_port_open(clipboard) failed: {}", e);
                return std::ptr::null_mut();
            }
        },
        "windows" => match WindowsPort::new() {
            Ok(p) => Box::new(p),
            Err(e) => {
                eprintln!("appmesh_port_open(windows) failed: {}", e);
                return std::ptr::null_mut();
            }
        },
        _ => {
            eprintln!("appmesh_port_open: unknown port '{}'", name);
            return std::ptr::null_mut();
        }
    };

    Box::into_raw(Box::new(port))
}

/// Execute a command on a port. Args and result are JSON strings.
///
/// Returns a JSON string (caller must free with `appmesh_string_free`),
/// or null on error.
#[no_mangle]
pub extern "C" fn appmesh_port_execute(
    port: AppmeshPortHandle,
    cmd: *const c_char,
    args_json: *const c_char,
) -> *mut c_char {
    if port.is_null() || cmd.is_null() {
        return std::ptr::null_mut();
    }

    let port = unsafe { &*port };
    let cmd = match unsafe { CStr::from_ptr(cmd) }.to_str() {
        Ok(s) => s,
        Err(_) => return std::ptr::null_mut(),
    };

    let args: HashMap<String, String> = if args_json.is_null() {
        HashMap::new()
    } else {
        match unsafe { CStr::from_ptr(args_json) }.to_str() {
            Ok(s) => serde_json::from_str(s).unwrap_or_default(),
            Err(_) => HashMap::new(),
        }
    };

    let result = port.execute(cmd, &args);
    let json = match result {
        Ok(value) => serde_json::json!({"ok": value}).to_string(),
        Err(e) => serde_json::json!({"error": {"code": e.code, "message": e.message}}).to_string(),
    };

    match CString::new(json) {
        Ok(cs) => cs.into_raw(),
        Err(_) => std::ptr::null_mut(),
    }
}

/// Free a port handle. Safe to call with null.
#[no_mangle]
pub extern "C" fn appmesh_port_free(port: AppmeshPortHandle) {
    if !port.is_null() {
        unsafe {
            drop(Box::from_raw(port));
        }
    }
}

/// Free a string returned by `appmesh_port_execute`. Safe to call with null.
#[no_mangle]
pub extern "C" fn appmesh_string_free(s: *mut c_char) {
    if !s.is_null() {
        unsafe {
            drop(CString::from_raw(s));
        }
    }
}
