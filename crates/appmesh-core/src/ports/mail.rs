use std::collections::HashMap;
use std::sync::Mutex;

use jmap_client::client::Client;
use jmap_client::core::query;
use jmap_client::core::response::EmailGetResponse;
use jmap_client::email;
use jmap_client::mailbox;

use crate::port::*;

/// JMAP mail port — read email via any JMAP server (Stalwart, Fastmail, etc.).
pub struct MailPort {
    rt: tokio::runtime::Runtime,
    client: Mutex<Option<Client>>,
}

impl MailPort {
    pub fn new() -> Result<Self, Box<dyn std::error::Error>> {
        let rt = tokio::runtime::Builder::new_current_thread()
            .enable_all()
            .build()?;

        // Try connecting from env vars, but don't fail if missing
        let client = match (
            std::env::var("JMAP_URL"),
            std::env::var("JMAP_USER"),
            std::env::var("JMAP_PASS"),
        ) {
            (Ok(url), Ok(user), Ok(pass)) => {
                match rt.block_on(Self::connect_inner(&url, &user, &pass)) {
                    Ok(c) => Some(c),
                    Err(e) => {
                        eprintln!("mail port: auto-connect failed: {}", e);
                        None
                    }
                }
            }
            _ => None,
        };

        Ok(Self {
            rt,
            client: Mutex::new(client),
        })
    }

    async fn connect_inner(
        url: &str,
        user: &str,
        pass: &str,
    ) -> Result<Client, Box<dyn std::error::Error>> {
        // Extract hostname for follow_redirects (Stalwart 307s /.well-known/jmap → /jmap/session)
        let host = url
            .strip_prefix("https://")
            .or_else(|| url.strip_prefix("http://"))
            .unwrap_or(url)
            .split('/')
            .next()
            .unwrap_or("")
            .split(':')
            .next()
            .unwrap_or("")
            .to_string();
        let client = Client::new()
            .credentials((user, pass))
            .follow_redirects([host])
            .connect(url)
            .await?;
        Ok(client)
    }

    fn require_client(&self) -> Result<std::sync::MutexGuard<'_, Option<Client>>, PortError> {
        let guard = self.client.lock().map_err(|e| PortError {
            code: -1,
            message: format!("lock poisoned: {}", e),
        })?;
        if guard.is_none() {
            return Err(PortError {
                code: -1,
                message: "not connected — call 'connect' first or set JMAP_URL/JMAP_USER/JMAP_PASS env vars".into(),
            });
        }
        Ok(guard)
    }

    fn cmd_connect(&self, args: &HashMap<String, String>) -> PortResult {
        let url = args
            .get("url")
            .cloned()
            .or_else(|| std::env::var("JMAP_URL").ok())
            .ok_or_else(|| PortError {
                code: -1,
                message: "missing 'url' argument and JMAP_URL not set".into(),
            })?;
        let user = args
            .get("user")
            .cloned()
            .or_else(|| std::env::var("JMAP_USER").ok())
            .ok_or_else(|| PortError {
                code: -1,
                message: "missing 'user' argument and JMAP_USER not set".into(),
            })?;
        let pass = args
            .get("pass")
            .cloned()
            .or_else(|| std::env::var("JMAP_PASS").ok())
            .ok_or_else(|| PortError {
                code: -1,
                message: "missing 'pass' argument and JMAP_PASS not set".into(),
            })?;

        let client = self
            .rt
            .block_on(Self::connect_inner(&url, &user, &pass))
            .map_err(|e| PortError {
                code: -1,
                message: format!("connect failed: {}", e),
            })?;

        let account_id = client.default_account_id().to_string();

        let mut guard = self.client.lock().map_err(|e| PortError {
            code: -1,
            message: format!("lock poisoned: {}", e),
        })?;
        *guard = Some(client);

        Ok(PortValue::String(format!(
            "connected to {} as {} (account: {})",
            url, user, account_id
        )))
    }

    fn cmd_status(&self) -> PortResult {
        let guard = self.client.lock().map_err(|e| PortError {
            code: -1,
            message: format!("lock poisoned: {}", e),
        })?;

        match guard.as_ref() {
            Some(client) => {
                let url = client.session_url();
                let account_id = client.default_account_id();
                Ok(PortValue::String(format!(
                    "connected — session: {}, account: {}",
                    url, account_id
                )))
            }
            None => Ok(PortValue::String("disconnected".into())),
        }
    }

    fn cmd_mailboxes(&self) -> PortResult {
        let guard = self.require_client()?;
        let client = guard.as_ref().unwrap();

        let response = self
            .rt
            .block_on(client.mailbox_query(
                None::<mailbox::query::Filter>,
                None::<Vec<query::Comparator<mailbox::query::Comparator>>>,
            ))
            .map_err(|e| PortError {
                code: -1,
                message: format!("mailbox_query failed: {}", e),
            })?;

        let mut mailboxes = Vec::new();
        for id in response.ids() {
            if let Ok(Some(mb)) = self
                .rt
                .block_on(client.mailbox_get(id, None::<Vec<mailbox::Property>>))
            {
                let mut map = HashMap::new();
                map.insert("id".into(), PortValue::String(mb.id().unwrap_or("").into()));
                map.insert(
                    "name".into(),
                    PortValue::String(mb.name().unwrap_or("").into()),
                );
                map.insert(
                    "role".into(),
                    PortValue::String(format!("{:?}", mb.role())),
                );
                map.insert("total".into(), PortValue::Int(mb.total_emails() as i64));
                map.insert("unread".into(), PortValue::Int(mb.unread_emails() as i64));
                mailboxes.push(PortValue::Map(map));
            }
        }

        Ok(PortValue::List(mailboxes))
    }

    fn cmd_query(&self, args: &HashMap<String, String>) -> PortResult {
        let guard = self.require_client()?;
        let client = guard.as_ref().unwrap();

        let limit: usize = args
            .get("limit")
            .and_then(|s| s.parse().ok())
            .unwrap_or(20);

        let mailbox_name = args.get("mailbox").map(|s| s.as_str()).unwrap_or("Inbox");

        // Find the mailbox ID by name or role
        let mailbox_id = self.find_mailbox_id(client, mailbox_name)?;

        let filter = email::query::Filter::in_mailbox(&mailbox_id);
        let sort = vec![email::query::Comparator::received_at()];

        let response = self
            .rt
            .block_on(client.email_query(Some(filter), Some(sort)))
            .map_err(|e| PortError {
                code: -1,
                message: format!("email_query failed: {}", e),
            })?;

        let ids = response.ids();
        let count = ids.len().min(limit);
        let mut emails = Vec::new();

        let properties = [
            email::Property::Id,
            email::Property::Subject,
            email::Property::From,
            email::Property::ReceivedAt,
            email::Property::Preview,
        ];

        for id in &ids[..count] {
            if let Ok(Some(msg)) = self
                .rt
                .block_on(client.email_get(id, Some(properties.clone())))
            {
                emails.push(self.email_to_summary(&msg));
            }
        }

        Ok(PortValue::List(emails))
    }

    fn cmd_read(&self, args: &HashMap<String, String>) -> PortResult {
        let id = args.get("id").ok_or_else(|| PortError {
            code: -1,
            message: "missing 'id' argument".into(),
        })?;

        let guard = self.require_client()?;
        let client = guard.as_ref().unwrap();

        // Use the builder API to set fetchTextBodyValues=true
        let msg = self
            .rt
            .block_on(async {
                let mut request = client.build();
                let get_request = request.get_email().ids([id.as_str()]);
                get_request.properties([
                    email::Property::Id,
                    email::Property::Subject,
                    email::Property::From,
                    email::Property::To,
                    email::Property::ReceivedAt,
                    email::Property::Preview,
                    email::Property::TextBody,
                    email::Property::BodyValues,
                    email::Property::Keywords,
                ]);
                get_request.arguments().fetch_text_body_values(true);
                request
                    .send_single::<EmailGetResponse>()
                    .await
                    .map(|mut r| r.take_list().pop())
            })
            .map_err(|e| PortError {
                code: -1,
                message: format!("email_get failed: {}", e),
            })?
            .ok_or_else(|| PortError {
                code: -1,
                message: format!("email not found: {}", id),
            })?;

        let mut map = HashMap::new();
        map.insert("id".into(), PortValue::String(msg.id().unwrap_or("").into()));
        map.insert(
            "subject".into(),
            PortValue::String(msg.subject().unwrap_or("").into()),
        );
        map.insert("from".into(), self.addresses_to_value(msg.from()));
        map.insert("to".into(), self.addresses_to_value(msg.to()));
        map.insert(
            "date".into(),
            PortValue::String(
                msg.received_at()
                    .map(|ts| ts.to_string())
                    .unwrap_or_default(),
            ),
        );
        map.insert(
            "preview".into(),
            PortValue::String(msg.preview().unwrap_or("").into()),
        );

        // Extract body text from text_body parts + body_values
        let body = self.extract_body_text(&msg);
        map.insert("body".into(), PortValue::String(body));

        let keywords: Vec<PortValue> = msg
            .keywords()
            .into_iter()
            .map(|k| PortValue::String(k.to_string()))
            .collect();
        map.insert("keywords".into(), PortValue::List(keywords));

        Ok(PortValue::Map(map))
    }

    fn cmd_mark_read(&self, args: &HashMap<String, String>) -> PortResult {
        let id = args.get("id").ok_or_else(|| PortError {
            code: -1,
            message: "missing 'id' argument".into(),
        })?;

        let guard = self.require_client()?;
        let client = guard.as_ref().unwrap();

        self.rt
            .block_on(client.email_set_keyword(id, "$seen", true))
            .map_err(|e| PortError {
                code: -1,
                message: format!("email_set_keyword failed: {}", e),
            })?;

        Ok(PortValue::String(format!("marked {} as read", id)))
    }

    // --- Helpers ---

    fn find_mailbox_id(&self, client: &Client, name: &str) -> Result<String, PortError> {
        let response = self
            .rt
            .block_on(client.mailbox_query(
                None::<mailbox::query::Filter>,
                None::<Vec<query::Comparator<mailbox::query::Comparator>>>,
            ))
            .map_err(|e| PortError {
                code: -1,
                message: format!("mailbox_query failed: {}", e),
            })?;

        let name_lower = name.to_lowercase();

        for id in response.ids() {
            if let Ok(Some(mb)) = self
                .rt
                .block_on(client.mailbox_get(id, None::<Vec<mailbox::Property>>))
            {
                let mb_name = mb.name().unwrap_or("");
                let mb_role = format!("{:?}", mb.role());

                if mb_name.to_lowercase() == name_lower
                    || mb_role.to_lowercase() == name_lower
                    || id == name
                {
                    return Ok(id.to_string());
                }
            }
        }

        Err(PortError {
            code: -1,
            message: format!("mailbox not found: {}", name),
        })
    }

    fn email_to_summary(&self, msg: &email::Email<jmap_client::Get>) -> PortValue {
        let mut map = HashMap::new();
        map.insert("id".into(), PortValue::String(msg.id().unwrap_or("").into()));
        map.insert(
            "subject".into(),
            PortValue::String(msg.subject().unwrap_or("").into()),
        );
        map.insert("from".into(), self.addresses_to_value(msg.from()));
        map.insert(
            "date".into(),
            PortValue::String(
                msg.received_at()
                    .map(|ts| ts.to_string())
                    .unwrap_or_default(),
            ),
        );
        map.insert(
            "preview".into(),
            PortValue::String(msg.preview().unwrap_or("").into()),
        );
        PortValue::Map(map)
    }

    fn addresses_to_value(&self, addrs: Option<&[email::EmailAddress]>) -> PortValue {
        match addrs {
            Some(list) => {
                let formatted: Vec<String> = list
                    .iter()
                    .map(|a| {
                        let name = a.name().unwrap_or("");
                        let addr = a.email();
                        if name.is_empty() {
                            addr.to_string()
                        } else {
                            format!("{} <{}>", name, addr)
                        }
                    })
                    .collect();
                PortValue::String(formatted.join(", "))
            }
            None => PortValue::String(String::new()),
        }
    }

    fn extract_body_text(&self, msg: &email::Email<jmap_client::Get>) -> String {
        if let Some(parts) = msg.text_body() {
            for part in parts {
                if let Some(part_id) = part.part_id() {
                    if let Some(body_value) = msg.body_value(part_id) {
                        return body_value.value().to_string();
                    }
                }
            }
        }
        // Fallback to preview
        msg.preview().unwrap_or("").to_string()
    }
}

// Safety: MailPort is only used from one thread at a time via Mutex or single-threaded FFI
unsafe impl Send for MailPort {}

impl AppMeshPort for MailPort {
    fn name(&self) -> &str {
        "mail"
    }

    fn commands(&self) -> Vec<CommandDef> {
        vec![
            CommandDef {
                name: "connect".into(),
                description: "Connect to JMAP mail server".into(),
                params: vec![
                    ParamDef {
                        name: "url".into(),
                        description: "JMAP server URL (falls back to JMAP_URL env)".into(),
                        required: false,
                    },
                    ParamDef {
                        name: "user".into(),
                        description: "Email/username (falls back to JMAP_USER env)".into(),
                        required: false,
                    },
                    ParamDef {
                        name: "pass".into(),
                        description: "Password (falls back to JMAP_PASS env)".into(),
                        required: false,
                    },
                ],
            },
            CommandDef {
                name: "status".into(),
                description: "Check mail connection status".into(),
                params: vec![],
            },
            CommandDef {
                name: "mailboxes".into(),
                description: "List all mailboxes with counts".into(),
                params: vec![],
            },
            CommandDef {
                name: "query".into(),
                description: "Query emails in a mailbox".into(),
                params: vec![
                    ParamDef {
                        name: "mailbox".into(),
                        description: "Mailbox name or ID (default: Inbox)".into(),
                        required: false,
                    },
                    ParamDef {
                        name: "limit".into(),
                        description: "Max results (default: 20)".into(),
                        required: false,
                    },
                ],
            },
            CommandDef {
                name: "read".into(),
                description: "Read a full email by ID".into(),
                params: vec![ParamDef {
                    name: "id".into(),
                    description: "Email ID".into(),
                    required: true,
                }],
            },
            CommandDef {
                name: "mark_read".into(),
                description: "Mark an email as read".into(),
                params: vec![ParamDef {
                    name: "id".into(),
                    description: "Email ID".into(),
                    required: true,
                }],
            },
        ]
    }

    fn execute(&self, cmd: &str, args: &HashMap<String, String>) -> PortResult {
        match cmd {
            "connect" => self.cmd_connect(args),
            "status" => self.cmd_status(),
            "mailboxes" => self.cmd_mailboxes(),
            "query" => self.cmd_query(args),
            "read" => self.cmd_read(args),
            "mark_read" => self.cmd_mark_read(args),
            other => Err(PortError {
                code: -1,
                message: format!("unknown command: {}", other),
            }),
        }
    }
}
