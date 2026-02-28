use std::os::unix::net::UnixStream;

use crate::eis::EisConnection;

/// Opaque handle wrapping the D-Bus connection and EIS keyboard device.
///
/// The D-Bus connection must stay alive for the EIS socket to remain valid —
/// KWin invalidates EIS when the D-Bus peer disconnects.
pub struct InputHandle {
    eis: EisConnection,
    // Must keep alive — KWin invalidates EIS when D-Bus disconnects
    _dbus_conn: zbus::Connection,
}

impl InputHandle {
    /// Create a new InputHandle by connecting to KWin EIS via D-Bus.
    ///
    /// Uses a single-threaded tokio runtime for the async D-Bus call,
    /// then drops it. EIS itself is sync (poll-based).
    pub fn new() -> Result<Self, Box<dyn std::error::Error>> {
        let rt = tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?;

        let (stream, dbus_conn) = rt.block_on(Self::connect_kwin_eis())?;

        let eis = EisConnection::connect(stream, "appmesh", false)?;

        Ok(Self {
            eis,
            _dbus_conn: dbus_conn,
        })
    }

    /// Type text into the focused window.
    pub fn type_text(&mut self, text: &str, delay_us: u64) -> Result<(), Box<dyn std::error::Error>> {
        self.eis.type_text(text, delay_us)
    }

    /// Send a key combo (e.g. "ctrl+v", "enter").
    pub fn send_key(&mut self, combo: &str, delay_us: u64) -> Result<(), Box<dyn std::error::Error>> {
        self.eis.send_key_combo(combo, delay_us)
    }

    /// Call KWin's connectToEIS D-Bus method, returning the EIS Unix socket.
    async fn connect_kwin_eis() -> Result<(UnixStream, zbus::Connection), Box<dyn std::error::Error>> {
        let connection = zbus::Connection::session().await?;

        let proxy = zbus::Proxy::new(
            &connection,
            "org.kde.KWin",
            "/org/kde/KWin/EIS/RemoteDesktop",
            "org.kde.KWin.EIS.RemoteDesktop",
        )
        .await?;

        // CAP_ALL = 63 — KWin requires all capabilities to be requested
        let reply: zbus::Message = proxy.call_method("connectToEIS", &(63i32,)).await?;
        let body = reply.body();
        let (fd, _cookie): (zbus::zvariant::OwnedFd, i32) = body.deserialize()?;

        let owned_fd: std::os::fd::OwnedFd = fd.into();
        let stream = UnixStream::from(owned_fd);
        stream.set_nonblocking(true)?;
        Ok((stream, connection))
    }
}
