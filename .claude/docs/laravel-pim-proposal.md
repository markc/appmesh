# Laravel PIM: Akonadi Replacement Proposal

## Overview

Two related but distinct projects to escape Akonadi and Python-based PIM solutions:

1. **Laravel DAV Server** - Simple CalDAV/CardDAV server replacing Nextcloud for DAV-only users
2. **Laravel PIM Daemon** - Full Akonadi replacement for KDE desktop integration

Both leverage PHP's maturity, Laravel's elegance, and the battle-tested sabre/dav library.

---

## Project 1: Laravel DAV Server

### Purpose

A lightweight CalDAV/CardDAV server for customers who use Nextcloud solely for calendar and contact sync. Eliminates the overhead of full Nextcloud installation.

### Target Users

- Small businesses needing shared calendars/contacts
- Individuals wanting self-hosted DAV without Nextcloud bloat
- Developers wanting a simple, hackable DAV server

### Why Not Existing Solutions?

| Server | Language | Issue |
|--------|----------|-------|
| Radicale | Python | Anti-Python stance |
| Baïkal | PHP | Unmaintained, old Sabre version |
| Nextcloud | PHP | Massive overkill for DAV-only |
| Cyrus | C | Complex, enterprise-focused |

### Architecture

```
laravel-dav/
├── app/
│   ├── Http/Controllers/
│   │   └── DavController.php      # Route to Sabre
│   ├── Models/
│   │   ├── User.php
│   │   ├── Calendar.php
│   │   ├── CalendarEvent.php
│   │   ├── AddressBook.php
│   │   └── Contact.php
│   └── Services/
│       └── SabreDavServer.php     # Sabre integration
├── database/migrations/
│   ├── create_calendars_table.php
│   ├── create_calendar_events_table.php
│   ├── create_address_books_table.php
│   └── create_contacts_table.php
├── routes/
│   ├── web.php                    # Admin UI
│   └── dav.php                    # DAV endpoints
└── config/
    └── dav.php                    # DAV configuration
```

### Key Dependencies

```json
{
    "require": {
        "php": "^8.2",
        "laravel/framework": "^11.0",
        "sabre/dav": "^4.6",
        "sabre/vobject": "^4.5"
    }
}
```

### DAV Endpoints

```
https://dav.example.com/
├── .well-known/caldav              → /dav/calendars/
├── .well-known/carddav             → /dav/addressbooks/
├── dav/
│   ├── principals/{user}/          # User principal
│   ├── calendars/{user}/           # User's calendars
│   │   └── {calendar-id}/          # Individual calendar
│   │       └── {event-id}.ics      # Calendar event
│   └── addressbooks/{user}/        # User's address books
│       └── {addressbook-id}/       # Individual address book
│           └── {contact-id}.vcf    # Contact vCard
```

### Features

**Core:**
- CalDAV (RFC 4791) - Calendar sync
- CardDAV (RFC 6352) - Contact sync
- HTTP Basic Auth + App passwords
- Multi-user with sharing

**Nice to have:**
- Web admin UI for user management
- Calendar/contact web viewer
- Import/export (ICS, VCF)
- Webhooks for integrations

### Client Compatibility

| Client | Platform | Protocol |
|--------|----------|----------|
| Thunderbird | Desktop | CalDAV/CardDAV |
| DAVx5 | Android | CalDAV/CardDAV |
| iOS Calendar/Contacts | iOS | CalDAV/CardDAV |
| GNOME Calendar/Contacts | Linux | CalDAV/CardDAV |
| Evolution | Linux | CalDAV/CardDAV |

### Implementation Notes

Sabre/dav handles the hard parts (RFC compliance, sync tokens, ETags). Laravel provides:
- Authentication (Sanctum for API tokens)
- Database abstraction (Eloquent)
- Queue for background jobs (reminders, sync)
- Admin UI (Blade or Livewire)

### Minimal Proof of Concept

```php
// routes/dav.php
Route::any('/dav/{path}', [DavController::class, 'handle'])
    ->where('path', '.*');

// app/Http/Controllers/DavController.php
class DavController extends Controller
{
    public function handle(Request $request)
    {
        $server = new \Sabre\DAV\Server([
            new \Sabre\CalDAV\CalendarRoot(
                new LaravelCalDAVBackend(),
                new LaravelPrincipalBackend()
            ),
            new \Sabre\CardDAV\AddressBookRoot(
                new LaravelPrincipalBackend(),
                new LaravelCardDAVBackend()
            ),
        ]);

        $server->addPlugin(new \Sabre\CalDAV\Plugin());
        $server->addPlugin(new \Sabre\CardDAV\Plugin());
        $server->addPlugin(new \Sabre\DAV\Auth\Plugin(
            new LaravelAuthBackend()
        ));

        $server->start();
    }
}
```

---

## Project 2: Laravel PIM Daemon (Full Akonadi Replacement)

### Purpose

Replace Akonadi entirely as the PIM backend for KDE (or any) desktop. Provide local caching, sync, and API access for email, calendars, contacts, and tasks.

### Why Replace Akonadi?

| Problem | Akonadi | Laravel PIM |
|---------|---------|-------------|
| Complexity | Massive C++ codebase | Clean PHP |
| Database | Requires MariaDB | SQLite (single file) |
| Debugging | Near impossible | Standard PHP debugging |
| Stability | Frequent corruption | Simple, recoverable |
| Dependencies | Qt, KDE Frameworks | Just PHP + extensions |

### Architecture

```
laravel-pim/
├── app/
│   ├── Console/Commands/
│   │   └── PimDaemon.php          # Main daemon process
│   ├── Services/
│   │   ├── Sync/
│   │   │   ├── ImapSync.php       # IMAP email sync
│   │   │   ├── CalDavSync.php     # CalDAV calendar sync
│   │   │   └── CardDavSync.php    # CardDAV contact sync
│   │   ├── Cache/
│   │   │   └── LocalCache.php     # SQLite cache manager
│   │   └── Api/
│   │       ├── RestApi.php        # REST endpoints
│   │       └── DbusAdapter.php    # D-Bus exposure (optional)
│   ├── Models/
│   │   ├── Account.php            # Email/DAV accounts
│   │   ├── Folder.php             # IMAP folders
│   │   ├── Email.php              # Cached emails
│   │   ├── Calendar.php
│   │   ├── Event.php
│   │   ├── AddressBook.php
│   │   └── Contact.php
│   └── Events/
│       ├── EmailReceived.php
│       ├── CalendarUpdated.php
│       └── ContactChanged.php
├── database/
│   └── pim.sqlite                 # Single-file database
├── config/
│   └── pim.php                    # Accounts, sync intervals
└── storage/
    └── attachments/               # Email attachment cache
```

### Key Dependencies

```json
{
    "require": {
        "php": "^8.2",
        "laravel/framework": "^11.0",
        "webklex/php-imap": "^5.3",
        "sabre/dav": "^4.6",
        "sabre/vobject": "^4.5",
        "react/event-loop": "^1.5",
        "react/socket": "^1.15"
    }
}
```

### Daemon Operation

```bash
# Run as systemd user service
php artisan pim:daemon

# Or manually
php artisan pim:sync --all
php artisan pim:serve --port=8765
```

### API Design

#### REST API (Primary)

```
GET    /api/accounts                    # List accounts
POST   /api/accounts                    # Add account
DELETE /api/accounts/{id}               # Remove account

GET    /api/emails                      # List emails (paginated)
GET    /api/emails/{id}                 # Get email with body
POST   /api/emails                      # Send email (queue)
DELETE /api/emails/{id}                 # Delete email

GET    /api/calendars                   # List calendars
GET    /api/calendars/{id}/events       # List events
POST   /api/calendars/{id}/events       # Create event
PUT    /api/events/{id}                 # Update event
DELETE /api/events/{id}                 # Delete event

GET    /api/addressbooks                # List address books
GET    /api/addressbooks/{id}/contacts  # List contacts
POST   /api/addressbooks/{id}/contacts  # Create contact
PUT    /api/contacts/{id}               # Update contact
DELETE /api/contacts/{id}               # Delete contact

POST   /api/sync                        # Trigger full sync
GET    /api/sync/status                 # Sync status
```

#### D-Bus Interface (Optional, for KDE integration)

```
Service: org.laravel.Pim
Path: /org/laravel/Pim

Methods:
  GetEmails(folder: string, limit: int) → array
  GetEmail(id: int) → dict
  SendEmail(to: string, subject: string, body: string) → bool
  GetCalendars() → array
  GetEvents(calendar_id: int, start: string, end: string) → array
  GetContacts(addressbook_id: int) → array
  Sync() → bool

Signals:
  EmailReceived(account_id: int, folder: string, email_id: int)
  CalendarUpdated(calendar_id: int)
  ContactChanged(contact_id: int)
```

D-Bus exposure via shell:
```php
// Simple D-Bus adapter using qdbus6
public function exposeViaDbus(string $method, array $args): void
{
    // Register with session bus
    Process::run("gdbus call --session ...");
}
```

Or via Unix socket + small C/Python bridge that translates to D-Bus.

### Sync Strategy

#### IMAP

```php
class ImapSync
{
    public function syncFolder(Account $account, string $folder): void
    {
        $client = new Client([
            'host' => $account->imap_host,
            'port' => $account->imap_port,
            'encryption' => 'ssl',
            'username' => $account->username,
            'password' => $account->password,
        ]);

        $remote = $client->getFolder($folder);
        $localUids = Email::where('folder', $folder)->pluck('uid');
        $remoteUids = $remote->query()->all()->get()->pluck('uid');

        // Download new
        $newUids = $remoteUids->diff($localUids);
        foreach ($remote->query()->whereUid($newUids)->get() as $msg) {
            Email::createFromImap($msg);
            event(new EmailReceived($account, $folder, $msg->uid));
        }

        // Remove deleted
        $deletedUids = $localUids->diff($remoteUids);
        Email::whereIn('uid', $deletedUids)->delete();
    }

    public function watchWithIdle(Account $account): void
    {
        // IMAP IDLE for push notifications
        $client->getFolder('INBOX')->idle(function ($message) {
            Email::createFromImap($message);
            event(new EmailReceived(...));
        });
    }
}
```

#### CalDAV/CardDAV

```php
class CalDavSync
{
    public function sync(Account $account, Calendar $calendar): void
    {
        $client = new \Sabre\DAV\Client([
            'baseUri' => $account->caldav_url,
            'userName' => $account->username,
            'password' => $account->password,
        ]);

        // Use sync-token for efficient delta sync
        $syncToken = $calendar->sync_token;
        $changes = $client->sync($calendar->remote_path, $syncToken);

        foreach ($changes['added'] as $href) {
            $ics = $client->request('GET', $href);
            Event::createFromIcs($calendar, $ics);
        }

        foreach ($changes['deleted'] as $href) {
            Event::where('remote_href', $href)->delete();
        }

        $calendar->update(['sync_token' => $changes['syncToken']]);
    }
}
```

### Desktop Integration Options

#### Option A: Thunderbird + REST

Thunderbird handles email natively. Laravel PIM provides REST API for custom UIs or Plasma widgets.

#### Option B: Custom Qt/GTK Client

Build a lightweight PIM client that talks to Laravel PIM via REST:
- Qt6 + QNetworkAccessManager
- GTK4 + libsoup
- Electron (if you must)

#### Option C: KDE Integration via D-Bus

Expose D-Bus interface so KDE apps (or modified versions) can use Laravel PIM instead of Akonadi.

### Advantages Over Akonadi

1. **Single SQLite file** - No MariaDB server, easy backup (`cp pim.sqlite pim.sqlite.bak`)
2. **PHP debugging** - Xdebug, Laravel Telescope, readable stack traces
3. **Clean API** - REST-first, optional D-Bus
4. **Portable** - Runs on any PHP 8.2+ system
5. **Recoverable** - If corrupted, delete SQLite and resync (minutes, not hours)
6. **Extensible** - Add new sync sources easily (Google, Microsoft Graph, etc.)

### Migration Path

1. **Phase 1:** CalDAV/CardDAV only (calendars + contacts)
2. **Phase 2:** Add IMAP sync for email caching
3. **Phase 3:** D-Bus interface for KDE integration
4. **Phase 4:** Custom lightweight PIM client

---

## Comparison

| Feature | Laravel DAV Server | Laravel PIM Daemon |
|---------|-------------------|-------------------|
| Purpose | Server (replaces Nextcloud) | Desktop cache (replaces Akonadi) |
| Runs on | VPS / home server | Local desktop |
| Database | MySQL/PostgreSQL | SQLite |
| Email | No | Yes (IMAP cache) |
| CalDAV | Server | Client + cache |
| CardDAV | Server | Client + cache |
| Clients | Thunderbird, DAVx5, etc. | REST API, D-Bus |
| Complexity | Medium | High |
| Timeline | 1-2 weeks MVP | 1-2 months MVP |

---

## Recommendations

### For Customers Needing DAV Only

**Build Laravel DAV Server first.** It's simpler, immediately useful, and replaces Nextcloud overhead for DAV-only users. Sabre/dav does the heavy lifting.

### For Escaping Akonadi

**Short term:** Use Thunderbird (email + CalDAV/CardDAV native). No local daemon needed.

**Long term:** Build Laravel PIM Daemon if:
- You need offline email access with custom UI
- You want D-Bus integration for KDE widgets
- You want a unified REST API for all PIM data

### Development Priority

1. **Laravel DAV Server** - High value, low effort
2. **Laravel PIM CalDAV/CardDAV sync** - Medium value, medium effort
3. **Laravel PIM IMAP sync** - Lower priority (Thunderbird exists)
4. **D-Bus integration** - Only if KDE integration is required

---

## Related Projects

- [sabre/dav](https://sabre.io/) - The PHP DAV library (excellent)
- [Baïkal](https://github.com/sabre-io/Baikal) - Abandoned sabre/dav frontend
- [Radicale](https://radicale.org/) - Python CalDAV/CardDAV (avoid if anti-Python)
- [Stalwart](https://stalw.art/) - Rust mail server with JMAP (watch this space)

---

## Notes

- Anti-Python stance noted: Radicale is out despite being functional
- sabre/dav is mature, RFC-compliant, actively maintained
- Laravel 11 + PHP 8.2+ provides modern async capabilities via ReactPHP
- SQLite is sufficient for single-user desktop PIM (millions of records OK)
- IMAP IDLE support requires persistent connection (ReactPHP event loop)
