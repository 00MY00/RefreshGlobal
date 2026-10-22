# RefreshGlobal — "All mailboxes" for FreeScout

> **Repository**: https://github.com/00MY00/RefreshGlobal

[Version française](README.md)

## 1. Overview

RefreshGlobal is a [FreeScout](https://freescout.net) module that adds an **"All mailboxes"** page: the tickets of
every mailbox you can access, in one list, with a mailbox filter. Tickets stay in their mailbox: a click opens the
normal ticket page, so replies are sent from the right mailbox address. When the
[Refresh](https://github.com/altmenorg/freescout-refresh) module is installed, the page looks exactly like it;
otherwise it uses FreeScout's standard look.

![All mailboxes list with Refresh](docs/screenshots/liste-cartes.png)

## 2. Features

- **"All mailboxes" page** (`/refresh-global/tickets`), in FreeScout's menu and in Refresh's left bar.
- **Mailbox filter** (several at once), **status**, **assignee** (me, unassigned, an agent).
- **Search**: subject, customer name, e-mail, ticket number (`#123`).
- **Mailbox above each ticket**: name and address of the mailbox (badge in card view, pill on phones, column in
  table view); can be hidden in the settings.
- **Refresh's "My dashboard" over all mailboxes**: Refresh alone shows the first mailbox only; the module shows the
  same dashboard (same look) with the figures and tickets of all the user's mailboxes. Tiles lead to "All mailboxes"
  filtered on the matching Refresh view.
- **Deleting a ticket**: back to "All mailboxes" with the last filters (or the next ticket of that list); trash
  (default) or permanent deletion.
- With Refresh: **"All mailboxes" phone tab**, option to replace Refresh's "Tickets" entry.
- **Updates** from FreeScout's **Modules** page, or safely with the [UpdateGuard](https://github.com/00MY00/UpdateGuard)
  module (recommended: SHA-256, backup, health check, automatic rollback).
- **Counters** per mailbox and per status (grouped queries).
- **CSV export** of the filtered list (UTF-8 with BOM for Excel, capped number of rows).
- **Saved views, private, shared or for everyone** (see “Saved and shared views” below).
- Filters live in the URL: a filtered list can be shared and the Back button works.
- **Access rights enforced**: users only see, count and export tickets of their own mailboxes (and only their own
  tickets with the "see only assigned conversations" permission).
- **Compatibility diagnostic**: `php artisan refreshglobal:check` and an admin page, with explicit `RG-xxx` messages
  after a FreeScout or Refresh update.
- **"Fetch e-mails" button** (circular arrow) when the [SyncNow](https://github.com/rabsym/freescout-syncnow) module
  is installed: fetches the IMAP mailboxes of the list right away, with SyncNow's rights and cooldown.
- **Automatic folders** when the [Workflow Packs](https://github.com/00MY00/WorkflowPacks) module (1.14.0 or newer)
  is installed: "Automatic folders" section in the views panel (tree of the folders of all your mailboxes, with
  their number of tickets; a click filters the list), a folder badge on each ticket and an "Overview" page (tickets
  and open tickets per folder and per mailbox, last filing, automatic clean-up).
- **Address to send from** on "Send an e-mail" / "New ticket": every address of your mailboxes and their aliases;
  another mailbox opens its form with what you typed; suggested address from the list or the last one used.
- **Spam left on the mail server** (default) and **origin tags** of the e-mails in English (`inbox`, `spam`, `trash`,
  `sent`, `folder:<name>`, `classified_spam`…): shown, filterable, recalculable, readable by other modules and copied
  into the Tags module when installed ([docs/api-tags.md](docs/api-tags.md)).
- **Details / raw text** of each message (headers, Message-ID, mail server folder, tags).
- **Move to a folder of the mail server** (ticket, selection bar, swipe to the left on touch screens), with “Undo”;
  the ticket itself does not change, its tags follow the folder.
- **Remote images** (tracking pixels): always show, restricted (“Show images” button) or blocked, finely set per
  origin tag of the e-mail.
- Languages: English, French, German, Spanish, Italian, Dutch, Brazilian Portuguese.
- **Language**: the module follows FreeScout's language, like Refresh (which has no language choice of its own): the
  user's profile language, otherwise the default one. A **language switch** at the bottom of the views panel (and
  of the phone drawer) changes that profile language: FreeScout, Refresh and the module switch together.

### Interoperability with Refresh (FreeScout Interop)

RefreshGlobal and Refresh are independent modules: each works without the other. Together they can cooperate through
a declared contract (`RefreshGlobal/interop.json`, [docs/INTEROPERABILITY.md](docs/INTEROPERABILITY.md)): RefreshGlobal
declares its intents (“All mailboxes” entry, “Move” action, origin of a ticket…) and Refresh renders them, without
RefreshGlobal touching its page. With a Refresh that does not know the contract yet, RefreshGlobal uses its tested
fallbacks (*legacy* mode, as before). The current mode is shown in **RefreshGlobal diagnostics › Interoperability**.

Without Refresh, RefreshGlobal keeps everything: “All mailboxes”, saved and shared views, filters, and the dashboard of
all mailboxes (computed by the module, shown above FreeScout's dashboard). For other modules it also offers a
**search** over all mailboxes (`GET /refresh-global/search`, a module extension until search joins the shared contract) and server
contexts (`fsinterop.navigation.context`, `fsinterop.conversation.context`), always with the user's rights.

### Saved and shared views

A **view** is a saved set of filters (mailboxes, status, assigned to, search, automatic folder, e-mail tag, Refresh
view) and a sort of “All mailboxes”. It **never changes a ticket** and **never gives access to a mailbox**.

- **Save**: filter the list, then “Save these filters as a view”: name and **Visible to**: *Only me (private)*,
  *Users I choose* (searchable list of the users of your mailboxes; an administrator sees every user and can tick
  “may edit”) or *Everyone*.
- **Open**: sections *Pinned views*, *My views*, *Shared with me*, *For everyone* of the views panel. Changing a
  filter keeps the view open with “* Unsaved changes”: *Update view* (if you may edit it), *Save as new* or *Reset*.
  A shared view is **never** changed without that click.
- **“Me”** always means **the person looking at the list**. Example: “My pending tickets” — *Status: Active +
  Pending*, *Assigned to: Me* — published for everyone shows each user their own tickets.
- **Rights**: the author edits, shares and deletes their view; administrators manage every shared / global view,
  can make it the **default view** for everyone or for the chosen users, and let chosen users edit it; others can
  **copy it into their views** (when allowed), pin it or make it their own default without changing it. Setting
  *Only administrators publish views for everyone* (Manage › Settings › RefreshGlobal).
- **Mailboxes**: a “Support + Billing” view opened by someone who only has Support shows Support only (with a note);
  a mailbox, agent, folder or tag that no longer exists is ignored with a note.
- **Manage**: page *Saved views* (`/refresh-global/views`): order (↑ ↓), JSON export / import (mailboxes travel by
  e-mail address, no user id), audit of the shared views (admins).

## 3. What it does not do

- It **does not modify** Refresh or FreeScout (no core or Refresh file is changed, copied or replaced).
- It **moves no ticket**: each ticket stays in its mailbox.
- It **does not handle replies**: they happen on the normal ticket page, from the right mailbox address.

## 4. Requirements

| | Tested | Supported |
|---|---|---|
| FreeScout | 1.8.245 | 1.8.x (warning otherwise) |
| Refresh (optional) | 1.6.0 | 1.4.0 to 1.6.x (warning otherwise); without Refresh: FreeScout's standard look |
| PHP | 8.2 | 7.1+ (like FreeScout) |
| Database | MariaDB 10.11 | FreeScout's (MySQL/MariaDB; PostgreSQL supported by the code but not tested) |
| Installer OS | Debian 12, Ubuntu 24.04 | Linux with bash; full install: Ubuntu / Debian |

Details: [COMPATIBILITY.md](COMPATIBILITY.md). **Windows**: not supported by FreeScout's official documentation, so
there is no PowerShell installer; use the manual installation.

## 5. Quick install

Add to an existing FreeScout (one command):

```bash
curl -fsSL https://raw.githubusercontent.com/00MY00/RefreshGlobal/main/install.sh | sudo bash
```

Full install on a blank Ubuntu/Debian server (runs FreeScout's official installer, then RefreshGlobal; Refresh too
if you give its official archive):

```bash
curl -fsSL https://raw.githubusercontent.com/00MY00/RefreshGlobal/main/install.sh | sudo bash -s -- --full [--refresh-zip=/root/Refresh.zip]
```

Reading the script before running it (the commands above pipe it straight into `bash`):

```bash
sudo git clone https://github.com/00MY00/RefreshGlobal.git /opt/RefreshGlobal
less /opt/RefreshGlobal/install.sh
sudo bash /opt/RefreshGlobal/install.sh --dry-run --source=/opt/RefreshGlobal/RefreshGlobal   # shows every action, changes nothing
sudo bash /opt/RefreshGlobal/install.sh --source=/opt/RefreshGlobal/RefreshGlobal
```

Updates: `sudo git -C /opt/RefreshGlobal pull`, then the same command with `--update`. Without Git: download
`RefreshGlobal.zip`, `install.sh` and `SHA256SUMS` from the Releases page, `sha256sum -c SHA256SUMS --ignore-missing`, then
`sudo bash install.sh --source=RefreshGlobal.zip`; or unzip into FreeScout's `Modules/` folder,
`chown -R www-data:www-data Modules/RefreshGlobal` and activate it in **Manage › Modules** (no backup or rollback then).

## 6. After installing

- Menu **"All mailboxes"**, or `https://your-helpdesk/refresh-global/tickets`.
- Check: `cd /var/www/html && sudo -u www-data php artisan refreshglobal:check` (exit code 0 OK, 1 degraded, 2 blocking).
- Admin diagnostic page: `https://your-helpdesk/refresh-global/diagnostic`.
- Settings: **Manage › Settings › RefreshGlobal** (admins).

| Setting | Default | Effect |
|---|---|---|
| "Tickets" entry | off | replaces Refresh's "Tickets" entry by "All mailboxes" |
| Dashboard of all mailboxes | on | Refresh's "My dashboard" covers all the user's mailboxes; without Refresh, the same dashboard is shown above FreeScout's |
| First response / resolution target | 24 h / 72 h | SLA targets of the dashboard ("Overdue", "Due: today", "Resolved within SLA") when Refresh is not active; with Refresh, Refresh's |
| Mailbox above each ticket | on | name and address of the mailbox above the subject (list and dashboard) |
| Go to the next ticket | off | after a deletion: off = back to "All mailboxes" (last filters), on = next ticket of that list |
| Automatic refresh | 30 seconds | the list and the dashboard refresh themselves when their tickets change (never while typing, selecting or with a menu open); 0 = off |
| Keep my place in the list | on | after a deletion (from the ticket or from the list), "All mailboxes" opens where you were, the next ticket in place of the deleted one |
| Delete permanently | off | off = FreeScout's trash (can be restored); on = the ticket and its e-mails are removed from FreeScout at once (cannot be undone; the mail server is not touched) |
| Also on the mail server | on | when a ticket is deleted for good, its e-mails are moved to the mail server's trash folder (IMAP) within a minute, still restorable from the webmail; trash folder found automatically or set by hand |
| Empty automatically (trash) | 0 days = never | every day at 03:45, tickets in the trash for more than N days are deleted for good with their e-mails (all mailboxes; mail server untouched) |

**Empty the trash now**: "Empty the trash (N)" button in these settings and at the bottom of the "All mailboxes" views
panel (phones too), with confirmation; same rules as FreeScout's own "Empty trash" (admins or users allowed to delete
conversations, their mailboxes only). Cannot be undone. CLI: `php artisan refreshglobal:trash --older-than=30`.

Buttons below the settings: **"Check for updates"** (tells at once whether a newer version exists, installs nothing)
and **"Update now"** (safe update started within a minute).

With Refresh in French, the module also corrects two of Refresh's phone strings ("Créé 7h il y a" → "Créé il y a
7h") without modifying Refresh: see `RefreshGlobal/Resources/lang/refresh-fixes.php`.

## 7. Updating

```bash
curl -fsSL https://raw.githubusercontent.com/00MY00/RefreshGlobal/main/install.sh | sudo bash -s -- --update
```

**Since 1.9.0 RefreshGlobal has no updater of its own**: its `module.json` declares FreeScout's standard keys
(`latestVersionUrl`: `version.txt` of the latest release, `latestVersionZipUrl`: `RefreshGlobal.zip`, with
`SHA256SUMS` next to them).

- **With the [UpdateGuard](https://github.com/00MY00/UpdateGuard) module (recommended)**: SHA-256 check, staging,
  backup, the module's health check (`php artisan refreshglobal:health`), **automatic rollback**, optional automatic
  updates (Manage › UpdateGuard).
- **Without it**: like any other module, an **"Update"** button shows up in **Manage › Modules** when a new version is
  published (no backup, no rollback).
- `install.sh --update` (above) is still available, with its own backup and rollback.

After every Refresh or FreeScout update: 1. `php artisan refreshglobal:check`, 2. read the report, 3. fix the failed
items (usually: install the RefreshGlobal release made for the new version).

## 8. Uninstall and rollback

```bash
… | sudo bash -s -- --uninstall    # disables the module, offers to drop its table and files, touches nothing else
… | sudo bash -s -- --rollback     # restores the module files and its table from the last backup
```

Backups: `/var/backups/refreshglobal/<date>/` (root only). Log: `/var/log/refreshglobal-install.log` (no passwords).

## 9. Troubleshooting

See the `RG-xxx` table in the [French README](README.md#9-dépannage); every message states what was expected, the
effect and the action to take.

## 10. Security

Rights are enforced server-side by a single query for the list, counters and export; mailbox ids from the URL are
intersected with the mailboxes FreeScout allows. State-changing actions use POST/DELETE with a CSRF token. CSV
formulas are neutralised. **Read the script before running it** (or use `--dry-run`); it only downloads this
repository's archive (SHA-256 checked) and, with `--full`, FreeScout's official installer — never Refresh.

## 11. Contributing, license, changelog

[docs/CONTRIBUTING.md](docs/CONTRIBUTING.md) · [GNU AGPL v3](LICENSE) (same license as FreeScout and Refresh) ·
[CHANGELOG.md](CHANGELOG.md) (French: [CHANGELOG.fr.md](CHANGELOG.fr.md)) · [AGENTS.md](AGENTS.md) · [tests/RESULTS.md](tests/RESULTS.md)
