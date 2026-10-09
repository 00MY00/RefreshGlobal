# RefreshGlobal — "All mailboxes" for FreeScout

> **Repository address**: `https://github.com/OWNER/RefreshGlobal`
> (set it when publishing: it is the only value to replace here, together with `REPO_URL` at the top of `install.sh`).

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
- **"Mailbox" column** (badge in card view, pill on phones).
- **Counters** per mailbox and per status (grouped queries).
- **CSV export** of the filtered list (UTF-8 with BOM for Excel, capped number of rows).
- **Personal saved views**: save filters, rename, set a default view, delete.
- Filters live in the URL: a filtered list can be shared and the Back button works.
- **Access rights enforced**: users only see, count and export tickets of their own mailboxes (and only their own
  tickets with the "see only assigned conversations" permission).
- **Compatibility diagnostic**: `php artisan refreshglobal:check` and an admin page, with explicit `RG-xxx` messages
  after a FreeScout or Refresh update.
- Languages: English, French, German, Spanish, Italian, Dutch, Brazilian Portuguese.

## 3. What it does not do

- It **does not modify** Refresh or FreeScout (no core or Refresh file is changed, copied or replaced).
- It **moves no ticket**: each ticket stays in its mailbox.
- It **does not handle replies**: they happen on the normal ticket page, from the right mailbox address.

## 4. Requirements

| | Tested | Supported |
|---|---|---|
| FreeScout | 1.8.245 | 1.8.x (warning otherwise) |
| Refresh (optional) | 1.4.3 | 1.4.x (warning otherwise); without Refresh: FreeScout's standard look |
| PHP | 8.2 | 7.1+ (like FreeScout) |
| Database | MariaDB 10.11 | FreeScout's (MySQL/MariaDB; PostgreSQL supported by the code but not tested) |
| Installer OS | Debian 12, Ubuntu 24.04 | Linux with bash; full install: Ubuntu / Debian |

Details: [COMPATIBILITY.md](COMPATIBILITY.md). **Windows**: not supported by FreeScout's official documentation, so
there is no PowerShell installer; use the manual installation.

## 5. Quick install

Add to an existing FreeScout (one command):

```bash
curl -fsSL https://raw.githubusercontent.com/OWNER/RefreshGlobal/main/install.sh | sudo bash
```

Full install on a blank Ubuntu/Debian server (runs FreeScout's official installer, then RefreshGlobal; Refresh too
if you give its official archive):

```bash
curl -fsSL https://raw.githubusercontent.com/OWNER/RefreshGlobal/main/install.sh | sudo bash -s -- --full [--refresh-zip=/root/Refresh.zip]
```

Manual install (no `curl | bash`): download `RefreshGlobal.zip` and `SHA256SUMS` from the releases page, check with
`sha256sum -c SHA256SUMS --ignore-missing`, then either read and run `install.sh --source=RefreshGlobal.zip`
(`--dry-run` shows every action first), or unzip into FreeScout's `Modules/` folder, `chown -R www-data:www-data
Modules/RefreshGlobal` and activate it in **Manage › Modules**.

## 6. After installing

- Menu **"All mailboxes"**, or `https://your-helpdesk/refresh-global/tickets`.
- Check: `cd /var/www/html && sudo -u www-data php artisan refreshglobal:check` (exit code 0 OK, 1 degraded, 2 blocking).
- Admin diagnostic page: `https://your-helpdesk/refresh-global/diagnostic`.

## 7. Updating

```bash
curl -fsSL https://raw.githubusercontent.com/OWNER/RefreshGlobal/main/install.sh | sudo bash -s -- --update
```

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
[CHANGELOG.md](CHANGELOG.md) · [tests/RESULTS.md](tests/RESULTS.md)
