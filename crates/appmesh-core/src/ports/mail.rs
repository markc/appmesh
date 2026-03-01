use std::collections::HashMap;
use std::sync::Mutex;

use jmap_client::client::Client;
use jmap_client::core::query;
use jmap_client::core::response::{EmailGetResponse, EmailSetResponse, IdentityGetResponse};
use jmap_client::core::set::SetObject;
use jmap_client::email;
use jmap_client::email::EmailBodyPart;
use jmap_client::mailbox;

use crate::port::*;

/// JMAP mail port — full email via any JMAP server (Stalwart, Fastmail, etc.).
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

    fn port_err(msg: impl std::fmt::Display) -> PortError {
        PortError {
            code: -1,
            message: msg.to_string(),
        }
    }

    // --- Phase 1: Read-only commands ---

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
            .map_err(|e| Self::port_err(format!("connect failed: {}", e)))?;

        let account_id = client.default_account_id().to_string();

        // Persist credentials so subsequent stateless port opens auto-connect
        std::env::set_var("JMAP_URL", &url);
        std::env::set_var("JMAP_USER", &user);
        std::env::set_var("JMAP_PASS", &pass);

        let mut guard = self.client.lock().map_err(|e| Self::port_err(e))?;
        *guard = Some(client);

        Ok(PortValue::String(format!(
            "connected to {} as {} (account: {})",
            url, user, account_id
        )))
    }

    fn cmd_status(&self) -> PortResult {
        let guard = self.client.lock().map_err(|e| Self::port_err(e))?;

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
            .map_err(|e| Self::port_err(format!("mailbox_query failed: {}", e)))?;

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
            .map_err(|e| Self::port_err(format!("email_query failed: {}", e)))?;

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
        let id = args.get("id").ok_or_else(|| Self::port_err("missing 'id' argument"))?;

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
            .map_err(|e| Self::port_err(format!("email_get failed: {}", e)))?
            .ok_or_else(|| Self::port_err(format!("email not found: {}", id)))?;

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
        let id = args.get("id").ok_or_else(|| Self::port_err("missing 'id' argument"))?;

        let guard = self.require_client()?;
        let client = guard.as_ref().unwrap();

        self.rt
            .block_on(client.email_set_keyword(id, "$seen", true))
            .map_err(|e| Self::port_err(format!("email_set_keyword failed: {}", e)))?;

        Ok(PortValue::String(format!("marked {} as read", id)))
    }

    // --- Phase 2: Send & Compose ---

    fn cmd_identities(&self) -> PortResult {
        let guard = self.require_client()?;
        let client = guard.as_ref().unwrap();

        let identities = self
            .rt
            .block_on(async {
                let mut request = client.build();
                request.get_identity();
                request
                    .send_single::<IdentityGetResponse>()
                    .await
                    .map(|mut r| r.take_list())
            })
            .map_err(|e| Self::port_err(format!("get_identity failed: {}", e)))?;

        let mut list = Vec::new();
        for ident in &identities {
            let mut map = HashMap::new();
            map.insert("id".into(), PortValue::String(ident.id().unwrap_or("").into()));
            map.insert("name".into(), PortValue::String(ident.name().unwrap_or("").into()));
            map.insert("email".into(), PortValue::String(ident.email().unwrap_or("").into()));
            let reply_to = ident
                .reply_to()
                .and_then(|addrs| addrs.first())
                .map(|a| a.email().to_string())
                .unwrap_or_default();
            map.insert("reply_to".into(), PortValue::String(reply_to));
            list.push(PortValue::Map(map));
        }

        Ok(PortValue::List(list))
    }

    fn cmd_send(&self, args: &HashMap<String, String>) -> PortResult {
        let to = args.get("to").ok_or_else(|| Self::port_err("missing 'to' argument"))?;
        let subject = args
            .get("subject")
            .ok_or_else(|| Self::port_err("missing 'subject' argument"))?;
        let body = args
            .get("body")
            .ok_or_else(|| Self::port_err("missing 'body' argument"))?;

        let guard = self.require_client()?;
        let client = guard.as_ref().unwrap();

        // Get first identity
        let (identity_id, from_email) = self.find_first_identity(client)?;

        let from = args.get("from").map(|s| s.as_str()).unwrap_or(&from_email);

        // Find Sent mailbox
        let sent_id = self.find_mailbox_id(client, "Sent")?;

        // Create email via Email/set with structured properties
        let email_id = self.create_email(client, from, to, subject, body, &sent_id, None, None)?;

        // Submit for delivery
        let submission = self
            .rt
            .block_on(client.email_submission_create(&email_id, &identity_id))
            .map_err(|e| Self::port_err(format!("email_submission_create failed: {}", e)))?;

        let sub_id = submission.id().unwrap_or("unknown");
        Ok(PortValue::String(format!(
            "sent to {} (submission: {})",
            to, sub_id
        )))
    }

    fn cmd_reply(&self, args: &HashMap<String, String>) -> PortResult {
        let id = args.get("id").ok_or_else(|| Self::port_err("missing 'id' argument"))?;
        let body = args
            .get("body")
            .ok_or_else(|| Self::port_err("missing 'body' argument"))?;

        let guard = self.require_client()?;
        let client = guard.as_ref().unwrap();

        // Fetch original email headers
        let original = self
            .rt
            .block_on(async {
                let mut request = client.build();
                let get_request = request.get_email().ids([id.as_str()]);
                get_request.properties([
                    email::Property::Id,
                    email::Property::Subject,
                    email::Property::From,
                    email::Property::To,
                    email::Property::MessageId,
                    email::Property::InReplyTo,
                    email::Property::References,
                ]);
                request
                    .send_single::<EmailGetResponse>()
                    .await
                    .map(|mut r| r.take_list().pop())
            })
            .map_err(|e| Self::port_err(format!("email_get failed: {}", e)))?
            .ok_or_else(|| Self::port_err(format!("email not found: {}", id)))?;

        // Get identity for From header
        let (identity_id, from_email) = self.find_first_identity(client)?;

        // Build reply headers
        let orig_from = original
            .from()
            .and_then(|f| f.first())
            .map(|a| a.email().to_string())
            .unwrap_or_default();
        let orig_subject = original.subject().unwrap_or("").to_string();
        let reply_subject = if orig_subject.to_lowercase().starts_with("re:") {
            orig_subject
        } else {
            format!("Re: {}", orig_subject)
        };

        // Find Sent mailbox
        let sent_id = self.find_mailbox_id(client, "Sent")?;

        // Build reply In-Reply-To and References lists (without angle brackets for JMAP)
        let in_reply_to_ids: Option<Vec<String>> = original
            .message_id()
            .and_then(|ids| ids.first())
            .map(|id| vec![id.to_string()]);

        let reference_ids: Option<Vec<String>> = {
            let mut refs: Vec<String> = original
                .references()
                .unwrap_or(&[])
                .iter()
                .map(|r| r.to_string())
                .collect();
            if let Some(msg_id) = original.message_id().and_then(|ids| ids.first()) {
                refs.push(msg_id.to_string());
            }
            if refs.is_empty() { None } else { Some(refs) }
        };

        // Create reply via Email/set
        let email_id = self.create_email(
            client,
            &from_email,
            &orig_from,
            &reply_subject,
            body,
            &sent_id,
            in_reply_to_ids.as_deref(),
            reference_ids.as_deref(),
        )?;

        // Submit for delivery
        let submission = self
            .rt
            .block_on(client.email_submission_create(&email_id, &identity_id))
            .map_err(|e| Self::port_err(format!("email_submission_create failed: {}", e)))?;

        let sub_id = submission.id().unwrap_or("unknown");
        Ok(PortValue::String(format!(
            "replied to {} (submission: {})",
            id, sub_id
        )))
    }

    // --- Phase 3: Mail Management ---

    fn cmd_move(&self, args: &HashMap<String, String>) -> PortResult {
        let id = args.get("id").ok_or_else(|| Self::port_err("missing 'id' argument"))?;
        let mailbox = args
            .get("mailbox")
            .ok_or_else(|| Self::port_err("missing 'mailbox' argument"))?;

        let guard = self.require_client()?;
        let client = guard.as_ref().unwrap();

        let mailbox_id = self.find_mailbox_id(client, mailbox)?;

        self.rt
            .block_on(client.email_set_mailboxes(id, [&mailbox_id]))
            .map_err(|e| Self::port_err(format!("email_set_mailboxes failed: {}", e)))?;

        Ok(PortValue::String(format!("moved {} to {}", id, mailbox)))
    }

    fn cmd_delete(&self, args: &HashMap<String, String>) -> PortResult {
        let id = args.get("id").ok_or_else(|| Self::port_err("missing 'id' argument"))?;
        let permanent = args
            .get("permanent")
            .map(|s| s == "true" || s == "1")
            .unwrap_or(false);

        let guard = self.require_client()?;
        let client = guard.as_ref().unwrap();

        if permanent {
            self.rt
                .block_on(client.email_destroy(id))
                .map_err(|e| Self::port_err(format!("email_destroy failed: {}", e)))?;
            Ok(PortValue::String(format!("deleted {}", id)))
        } else {
            let trash_id = self.find_mailbox_id(client, "Trash")?;
            self.rt
                .block_on(client.email_set_mailboxes(id, [&trash_id]))
                .map_err(|e| Self::port_err(format!("email_set_mailboxes failed: {}", e)))?;
            Ok(PortValue::String(format!("moved {} to Trash", id)))
        }
    }

    fn cmd_flag(&self, args: &HashMap<String, String>) -> PortResult {
        let id = args.get("id").ok_or_else(|| Self::port_err("missing 'id' argument"))?;

        let guard = self.require_client()?;
        let client = guard.as_ref().unwrap();

        self.rt
            .block_on(client.email_set_keyword(id, "$flagged", true))
            .map_err(|e| Self::port_err(format!("email_set_keyword failed: {}", e)))?;

        Ok(PortValue::String(format!("flagged {}", id)))
    }

    fn cmd_unflag(&self, args: &HashMap<String, String>) -> PortResult {
        let id = args.get("id").ok_or_else(|| Self::port_err("missing 'id' argument"))?;

        let guard = self.require_client()?;
        let client = guard.as_ref().unwrap();

        self.rt
            .block_on(client.email_set_keyword(id, "$flagged", false))
            .map_err(|e| Self::port_err(format!("email_set_keyword failed: {}", e)))?;

        Ok(PortValue::String(format!("unflagged {}", id)))
    }

    fn cmd_mark_unread(&self, args: &HashMap<String, String>) -> PortResult {
        let id = args.get("id").ok_or_else(|| Self::port_err("missing 'id' argument"))?;

        let guard = self.require_client()?;
        let client = guard.as_ref().unwrap();

        self.rt
            .block_on(client.email_set_keyword(id, "$seen", false))
            .map_err(|e| Self::port_err(format!("email_set_keyword failed: {}", e)))?;

        Ok(PortValue::String(format!("marked {} as unread", id)))
    }

    fn cmd_search(&self, args: &HashMap<String, String>) -> PortResult {
        let text = args
            .get("text")
            .ok_or_else(|| Self::port_err("missing 'text' argument"))?;
        let limit: usize = args
            .get("limit")
            .and_then(|s| s.parse().ok())
            .unwrap_or(20);

        let guard = self.require_client()?;
        let client = guard.as_ref().unwrap();

        let filter = email::query::Filter::text(text);
        let sort = vec![email::query::Comparator::received_at()];

        let response = self
            .rt
            .block_on(client.email_query(Some(filter), Some(sort)))
            .map_err(|e| Self::port_err(format!("email_query failed: {}", e)))?;

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

    fn cmd_attachment_list(&self, args: &HashMap<String, String>) -> PortResult {
        let id = args.get("id").ok_or_else(|| Self::port_err("missing 'id' argument"))?;

        let guard = self.require_client()?;
        let client = guard.as_ref().unwrap();

        let msg = self
            .rt
            .block_on(client.email_get(
                id,
                Some([email::Property::Id, email::Property::Attachments]),
            ))
            .map_err(|e| Self::port_err(format!("email_get failed: {}", e)))?
            .ok_or_else(|| Self::port_err(format!("email not found: {}", id)))?;

        let mut list = Vec::new();
        if let Some(attachments) = msg.attachments() {
            for att in attachments {
                let mut map = HashMap::new();
                map.insert(
                    "name".into(),
                    PortValue::String(att.name().unwrap_or("unnamed").into()),
                );
                map.insert(
                    "type".into(),
                    PortValue::String(att.content_type().unwrap_or("application/octet-stream").into()),
                );
                map.insert("size".into(), PortValue::Int(att.size() as i64));
                map.insert(
                    "blob_id".into(),
                    PortValue::String(att.blob_id().unwrap_or("").into()),
                );
                list.push(PortValue::Map(map));
            }
        }

        Ok(PortValue::List(list))
    }

    fn cmd_attachment_download(&self, args: &HashMap<String, String>) -> PortResult {
        let blob_id = args
            .get("id")
            .ok_or_else(|| Self::port_err("missing 'id' argument (blob_id)"))?;
        let name = args
            .get("name")
            .map(|s| s.as_str())
            .unwrap_or("attachment");

        let guard = self.require_client()?;
        let client = guard.as_ref().unwrap();

        let bytes = self
            .rt
            .block_on(client.download(blob_id))
            .map_err(|e| Self::port_err(format!("download failed: {}", e)))?;

        // Write to temp directory
        let dir = std::path::Path::new("/tmp/appmesh-mail");
        std::fs::create_dir_all(dir)
            .map_err(|e| Self::port_err(format!("mkdir failed: {}", e)))?;

        // Sanitize filename
        let safe_name: String = name
            .chars()
            .map(|c| if c.is_alphanumeric() || c == '.' || c == '-' || c == '_' { c } else { '_' })
            .collect();
        let path = dir.join(&safe_name);

        std::fs::write(&path, &bytes)
            .map_err(|e| Self::port_err(format!("write failed: {}", e)))?;

        Ok(PortValue::String(format!(
            "saved to {} ({} bytes)",
            path.display(),
            bytes.len()
        )))
    }

    // --- Helpers ---

    fn find_first_identity(&self, client: &Client) -> Result<(String, String), PortError> {
        let identities = self
            .rt
            .block_on(async {
                let mut request = client.build();
                request.get_identity();
                request
                    .send_single::<IdentityGetResponse>()
                    .await
                    .map(|mut r| r.take_list())
            })
            .map_err(|e| Self::port_err(format!("get_identity failed: {}", e)))?;

        let ident = identities
            .first()
            .ok_or_else(|| Self::port_err("no identities configured on server"))?;

        Ok((
            ident.id().unwrap_or("").to_string(),
            ident.email().unwrap_or("").to_string(),
        ))
    }

    /// Create an email via Email/set with structured JMAP properties.
    /// Returns the created email ID.
    fn create_email(
        &self,
        client: &Client,
        from: &str,
        to: &str,
        subject: &str,
        body: &str,
        mailbox_id: &str,
        in_reply_to: Option<&[String]>,
        references: Option<&[String]>,
    ) -> Result<String, PortError> {
        let email_id = self
            .rt
            .block_on(async {
                let mut request = client.build();
                let set_req = request.set_email();
                let create = set_req.create();
                create
                    .mailbox_ids([mailbox_id])
                    .from([from])
                    .to([to])
                    .subject(subject)
                    .text_body(
                        EmailBodyPart::new()
                            .part_id("1")
                            .content_type("text/plain"),
                    )
                    .body_value("1".to_string(), body);
                if let Some(ids) = in_reply_to {
                    create.in_reply_to(ids.iter().map(|s| s.as_str()));
                }
                if let Some(ids) = references {
                    create.references(ids.iter().map(|s| s.as_str()));
                }
                let id = create.create_id().unwrap();
                request
                    .send_single::<EmailSetResponse>()
                    .await
                    .and_then(|mut r| r.created(&id))
                    .map(|e| e.id().unwrap_or("unknown").to_string())
            })
            .map_err(|e| Self::port_err(format!("email_set create failed: {}", e)))?;

        Ok(email_id)
    }

    fn find_mailbox_id(&self, client: &Client, name: &str) -> Result<String, PortError> {
        let response = self
            .rt
            .block_on(client.mailbox_query(
                None::<mailbox::query::Filter>,
                None::<Vec<query::Comparator<mailbox::query::Comparator>>>,
            ))
            .map_err(|e| Self::port_err(format!("mailbox_query failed: {}", e)))?;

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

        Err(Self::port_err(format!("mailbox not found: {}", name)))
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
            // Phase 1: Read-only
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
            // Phase 2: Send & Compose
            CommandDef {
                name: "identities".into(),
                description: "List sender identities".into(),
                params: vec![],
            },
            CommandDef {
                name: "send".into(),
                description: "Send an email".into(),
                params: vec![
                    ParamDef {
                        name: "to".into(),
                        description: "Recipient email address".into(),
                        required: true,
                    },
                    ParamDef {
                        name: "subject".into(),
                        description: "Email subject".into(),
                        required: true,
                    },
                    ParamDef {
                        name: "body".into(),
                        description: "Email body text".into(),
                        required: true,
                    },
                    ParamDef {
                        name: "from".into(),
                        description: "From address (default: first identity)".into(),
                        required: false,
                    },
                ],
            },
            CommandDef {
                name: "reply".into(),
                description: "Reply to an email".into(),
                params: vec![
                    ParamDef {
                        name: "id".into(),
                        description: "Email ID to reply to".into(),
                        required: true,
                    },
                    ParamDef {
                        name: "body".into(),
                        description: "Reply body text".into(),
                        required: true,
                    },
                ],
            },
            // Phase 3: Mail Management
            CommandDef {
                name: "move".into(),
                description: "Move email to another mailbox".into(),
                params: vec![
                    ParamDef {
                        name: "id".into(),
                        description: "Email ID".into(),
                        required: true,
                    },
                    ParamDef {
                        name: "mailbox".into(),
                        description: "Target mailbox name or ID".into(),
                        required: true,
                    },
                ],
            },
            CommandDef {
                name: "delete".into(),
                description: "Delete email (move to Trash, or permanent)".into(),
                params: vec![
                    ParamDef {
                        name: "id".into(),
                        description: "Email ID".into(),
                        required: true,
                    },
                    ParamDef {
                        name: "permanent".into(),
                        description: "Permanently delete (default: false)".into(),
                        required: false,
                    },
                ],
            },
            CommandDef {
                name: "flag".into(),
                description: "Flag an email".into(),
                params: vec![ParamDef {
                    name: "id".into(),
                    description: "Email ID".into(),
                    required: true,
                }],
            },
            CommandDef {
                name: "unflag".into(),
                description: "Unflag an email".into(),
                params: vec![ParamDef {
                    name: "id".into(),
                    description: "Email ID".into(),
                    required: true,
                }],
            },
            CommandDef {
                name: "mark_unread".into(),
                description: "Mark an email as unread".into(),
                params: vec![ParamDef {
                    name: "id".into(),
                    description: "Email ID".into(),
                    required: true,
                }],
            },
            CommandDef {
                name: "search".into(),
                description: "Full-text search across all mailboxes".into(),
                params: vec![
                    ParamDef {
                        name: "text".into(),
                        description: "Search text".into(),
                        required: true,
                    },
                    ParamDef {
                        name: "limit".into(),
                        description: "Max results (default: 20)".into(),
                        required: false,
                    },
                ],
            },
            CommandDef {
                name: "attachment_list".into(),
                description: "List attachments on an email".into(),
                params: vec![ParamDef {
                    name: "id".into(),
                    description: "Email ID".into(),
                    required: true,
                }],
            },
            CommandDef {
                name: "attachment_download".into(),
                description: "Download attachment by blob ID".into(),
                params: vec![
                    ParamDef {
                        name: "id".into(),
                        description: "Blob ID".into(),
                        required: true,
                    },
                    ParamDef {
                        name: "name".into(),
                        description: "Filename to save as".into(),
                        required: false,
                    },
                ],
            },
        ]
    }

    fn execute(&self, cmd: &str, args: &HashMap<String, String>) -> PortResult {
        match cmd {
            // Phase 1
            "connect" => self.cmd_connect(args),
            "status" => self.cmd_status(),
            "mailboxes" => self.cmd_mailboxes(),
            "query" => self.cmd_query(args),
            "read" => self.cmd_read(args),
            "mark_read" => self.cmd_mark_read(args),
            // Phase 2
            "identities" => self.cmd_identities(),
            "send" => self.cmd_send(args),
            "reply" => self.cmd_reply(args),
            // Phase 3
            "move" => self.cmd_move(args),
            "delete" => self.cmd_delete(args),
            "flag" => self.cmd_flag(args),
            "unflag" => self.cmd_unflag(args),
            "mark_unread" => self.cmd_mark_unread(args),
            "search" => self.cmd_search(args),
            "attachment_list" => self.cmd_attachment_list(args),
            "attachment_download" => self.cmd_attachment_download(args),
            other => Err(PortError {
                code: -1,
                message: format!("unknown command: {}", other),
            }),
        }
    }
}
