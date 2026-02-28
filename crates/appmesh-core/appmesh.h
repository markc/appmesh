/* AppMesh C ABI — PHP FFI header */

/* === Input handle (direct keyboard injection) === */

typedef void *appmesh_handle_t;

/* Initialize input handle (connects to KWin EIS via D-Bus).
   Returns opaque handle on success, NULL on failure. */
appmesh_handle_t appmesh_init(void);

/* Type text into focused window.
   Returns: 0=ok, -1=error, -2=null handle */
int appmesh_type_text(appmesh_handle_t h, const char *text, uint64_t delay_us);

/* Send key combo (e.g. "ctrl+v", "enter").
   Returns: 0=ok, -1=error, -2=null handle */
int appmesh_send_key(appmesh_handle_t h, const char *combo, uint64_t delay_us);

/* Free handle. Safe to call with NULL. */
void appmesh_free(appmesh_handle_t h);

/* === Port API (generic ARexx-style command dispatch) === */

typedef void *appmesh_port_t;

/* Open a port by name (e.g. "input"). Returns NULL on failure. */
appmesh_port_t appmesh_port_open(const char *name);

/* Execute command on port. args_json and result are JSON strings.
   Returns JSON string (caller must free with appmesh_string_free), or NULL. */
char *appmesh_port_execute(appmesh_port_t port, const char *cmd, const char *args_json);

/* Free a port handle. Safe to call with NULL. */
void appmesh_port_free(appmesh_port_t port);

/* Free a string returned by appmesh_port_execute. Safe to call with NULL. */
void appmesh_string_free(char *s);
