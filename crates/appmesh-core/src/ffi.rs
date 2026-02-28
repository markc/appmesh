use std::collections::HashMap;
use std::ffi::{CStr, CString};
use std::os::raw::c_char;

use crate::input::InputHandle;
use crate::port::AppMeshPort;
use crate::ports::clipboard::ClipboardPort;
use crate::ports::input::InputPort;
use crate::ports::notify::NotifyPort;
use crate::ports::screenshot::ScreenshotPort;
use crate::ports::windows::WindowsPort;

/// All available port names.
pub const PORT_NAMES: &[&str] = &["clipboard", "input", "notify", "screenshot", "windows"];

/// Open a port by name. Returns the port or an error message.
pub fn open_port(name: &str) -> Result<Box<dyn AppMeshPort>, String> {
    match name {
        "clipboard" => ClipboardPort::new().map(|p| Box::new(p) as Box<dyn AppMeshPort>),
        "input" => InputPort::new().map(|p| Box::new(p) as Box<dyn AppMeshPort>),
        "notify" => NotifyPort::new().map(|p| Box::new(p) as Box<dyn AppMeshPort>),
        "screenshot" => ScreenshotPort::new().map(|p| Box::new(p) as Box<dyn AppMeshPort>),
        "windows" => WindowsPort::new().map(|p| Box::new(p) as Box<dyn AppMeshPort>),
        _ => return Err(format!("unknown port: {}", name)),
    }
    .map_err(|e| e.to_string())
}

// ============================================================================
// Input handle FFI (direct keyboard injection)
// ============================================================================

pub type AppmeshHandle = *mut InputHandle;
pub type AppmeshPortHandle = *mut Box<dyn AppMeshPort>;

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

#[no_mangle]
pub extern "C" fn appmesh_free(handle: AppmeshHandle) {
    if !handle.is_null() {
        unsafe { drop(Box::from_raw(handle)); }
    }
}

// ============================================================================
// Port-level FFI — generic ARexx-style command dispatch
// ============================================================================

#[no_mangle]
pub extern "C" fn appmesh_port_open(name: *const c_char) -> AppmeshPortHandle {
    if name.is_null() {
        return std::ptr::null_mut();
    }
    let name = match unsafe { CStr::from_ptr(name) }.to_str() {
        Ok(s) => s,
        Err(_) => return std::ptr::null_mut(),
    };

    match open_port(name) {
        Ok(port) => Box::into_raw(Box::new(port)),
        Err(e) => {
            eprintln!("appmesh_port_open({}) failed: {}", name, e);
            std::ptr::null_mut()
        }
    }
}

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

#[no_mangle]
pub extern "C" fn appmesh_port_free(port: AppmeshPortHandle) {
    if !port.is_null() {
        unsafe { drop(Box::from_raw(port)); }
    }
}

#[no_mangle]
pub extern "C" fn appmesh_string_free(s: *mut c_char) {
    if !s.is_null() {
        unsafe { drop(CString::from_raw(s)); }
    }
}
