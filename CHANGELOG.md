# Changelog

All notable changes to RefreshGlobal. This English file is the reference for the GitHub release notes; the French
version is [CHANGELOG.fr.md](CHANGELOG.fr.md). Both are updated together for every release. Versions before 1.10.0
are described in the French changelog only.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) — versions: [SemVer](https://semver.org/).

Every published change gets a new version number: FreeScout's Manage › Modules page and UpdateGuard only offer an
update when the version changes.

## [1.16.1] — 2026-10-20

### Documentation
- **English changelog**: `CHANGELOG.md` is now in English (versions 1.10.0 to 1.16.1 translated); the complete French
  changelog moved to `CHANGELOG.fr.md`. GitHub release notes are in English (section of `CHANGELOG.md`, with a link to
  the French version).
- `AGENTS.md`: instructions for AI coding agents (update both changelogs for every release, project rules).
- The release archive contains both changelogs; `tools/build-release.sh` refuses a version missing from either one.
- No code or interface translation change.

## [1.16.0] — 2026-10-19

### Added
- **Move a ticket to a folder of the mail server.** The ticket's e-mails go to an existing folder of their mailbox on
  the mail server (IMAP). The ticket itself does not change (status, mailbox, assignee); its origin tags follow the
  folder (`inbox` → `folder:invoices`…). A message offers **“Undo”** for 8 seconds.
  - **“Move”** button on the ticket page (next to “Close” with Refresh, in FreeScout's toolbar without Refresh), and
    “Move to a folder” in the phone's “Ticket actions” menu;
  - folder button in the **selection bar** of the lists (several tickets at once);
  - **swipe a ticket to the left** on touch screens (phones, tablets) to choose the folder (swiping to the right is
    still Refresh's own: Delete / Take / Spam);
  - folder dialog: the mailbox's folders (sub-folders of INBOX shown without “INBOX”), search, current folder marked;
  - rights: FreeScout's own (“update the ticket”); IMAP mailboxes only; the folder must exist in the mailbox; undo
    only puts an e-mail back into a folder of its mailbox;
  - setting “Move to a folder” (on by default); for other modules, action `refreshglobal.mail_moved`
    (`docs/api-tags.md`).
- **Remote images in e-mails** (tracking pixels): default mode **Always show**, **Restricted** (not loaded, “Show
  images” button per message — the default) or **Blocked** (never loaded, addresses removed from the page);
  **rules per origin tag** “tag = mode” (prefixes with `*`, e.g. `folder:clients-* = show`); the most restrictive
  matching rule wins (default rules: `classified_spam = block`, `spam = block`). Images attached to the e-mail and
  images of this FreeScout always show. Uses FreeScout's `thread.body_output` filter.
- Integration checks RG-HOOK-38…41, RG-CORE-21, RG-CORE-22.

## [1.15.1] — 2026-10-19

### Fixed
- **Phone: ticket title written one letter per line.** On a ticket filed in an automatic folder (Workflow Packs badge
  “[WP] …”) or carrying e-mail tags, Refresh puts the subject and the badges on one line that never wraps; a long
  badge squeezed the subject to 0 px. The subject now keeps its width and the badges go below (often seen after
  “Close”, which opens the next ticket).
- **Module styles lost on phones outside the module's own pages** (ticket page, dashboard): FreeScout bundles the
  stylesheets and its minifier does not understand an `@container` rule inside an `@media`; everything after it was
  nested inside `@media (min-width: 768px)` (e.g. e-mail tags without style on phones). The list's `@container` rules
  are now in `Public/css/refreshglobal-layout.css`, loaded by the “All mailboxes” page only; a test keeps them out of
  the bundled stylesheet.
- **Browser warning “Incorrect use of <label for=FORM_ELEMENT>”**: it came from FreeScout's own forms (“After Sending”
  pointing to a hidden field, “To” pointing to a field that is not always printed: `editor_bottom_toolbar.blade.php:34,63`,
  `conversations/view.blade.php:267`, `create.blade.php:153`); the module's script removes the invalid `for` (the text
  stays), without changing FreeScout's files.

### Information
- A request to an external domain seen in the browser when a ticket is opened (“…/wf/open?upn=…”) comes neither from
  Refresh nor from this module: it is a **tracking pixel** contained in the received e-mail, loaded with its images
  (see the remote images setting of 1.16.0).

## [1.15.0] — 2026-10-19

### Added
- **Shared saved views.** A view (filters + sort of “All mailboxes”) can be:
  - **private** (its author only), **shared with chosen users**, or **for everyone** (everyone with access to “All
    mailboxes”); every agent may publish for everyone, or administrators only (setting “Only administrators publish
    views for everyone”, off by default);
  - views panel sections: **Pinned views**, **My views**, **Shared with me**, **For everyone**;
  - **opened, then changed**: the view stays open, marked “* Unsaved changes”, with **Update view** (only with the
    edit right, confirmation for a shared view), **Save as new** and **Reset**; a shared view is never changed
    without that action;
  - **Copy to my views** (private copy “Name (copy)”), unless the author forbids it;
  - **pin** and **my default view**: personal preferences, the view itself does not change;
  - an administrator can make it the **default view** for everyone (view for everyone) or for the users it is shared
    with; each user's own choice comes first; an administrator can also let chosen users **edit** it (filters and
    name, not the sharing);
  - **order** of the views (↑ ↓): each user for their own views, administrators for shared views;
  - **“Saved views” page** (`/refresh-global/views`, “Manage views” link): name, owner, visibility, shared with,
    dates, actions (open, edit / share, copy, pin, default, order, export, delete); **JSON import / export**
    (mailboxes by e-mail address, no user id exported); audit of the shared views for administrators; a table on
    computers, cards on phones.
- **Dynamic “Me”**: “Assigned to: me” always means the person looking at the list, never the author of the view (a
  “My open tickets” view for everyone shows each user their own tickets).
- **A view never gives access to a mailbox**: everyone only sees the tickets of their own mailboxes (and only their
  own tickets with the “see only assigned tickets” permission), also in the export and the automatic refresh; a view
  referring to a deleted mailbox, a mailbox that is not yours, a deleted agent, an automatic folder or e-mail tag
  that no longer exists says so above the list and ignores it; an unreadable definition shows the whole list with a
  message.
- Versioned view format (`schema_version` 2: `filters`, `sorting`, `display`); views of older versions are read and
  converted automatically.

### Migration
- New tables `refreshglobal_view_users` (sharing), `refreshglobal_view_prefs` (pinned, each user's default view — the
  existing default views are copied there) and `refreshglobal_view_audit`; new columns of
  `refreshglobal_saved_views`. Additions only: existing views are kept, nothing to do by hand (the module's migration
  runs on update).
- The list of visible views is cached 1 minute per user and cleared on every change; 3 queries whatever the number
  of views.
- Integration checks RG-DB-12…14 (tables), RG-DB-05 (new columns), RG-CORE-20 (`App\User` methods used).

### Not included
- **Sharing with a team**: FreeScout has no teams of its own; the teams of the paid “Teams” module are “robot” users
  whose members RefreshGlobal can not read without that module's code. The data model keeps room for it
  (`SavedView::SHARED_TEAM`).

## [1.14.0] — 2026-10-19

### Compatibility
- **Refresh 1.6.0** (current version): tested with Refresh 1.6.0; range accepted by `refreshglobal:check`
  `>= 1.4.0` and `< 1.7.0` (before: `< 1.5.0`, hence the RG-REF-02 warning with a recent Refresh). Every Refresh
  element the module uses was checked again in Refresh 1.6.0's code and its line numbers updated
  (`INTEGRATION_MAP.md`). Tested: 124 PHPUnit tests, layout at 5 widths, back to the list, long-press selection,
  sending address (computer and phone), dashboard.

### Added
- **Global dashboard: unresolved tickets per agent in two columns, “Open” and “Pending”**, like Refresh's own card
  since Refresh 1.5.0; agents sorted by their total (open + pending), then by name; the “Unassigned” row is split the
  same way; each number opens “All mailboxes” filtered on the agent and the status. One grouped query (agent ×
  status) with the same rights as before (allowed mailboxes, “only assigned tickets” permission, published tickets,
  no spam). On phones, each number has its label. Dashboard data cached by an older version is ignored (new cache
  key); nothing to clear by hand.
- **Refresh 1.6.0 hover previews**: the “Hover previews” entry (on / off, per agent) is also in the Layout menu of
  “All mailboxes”; the previews (first message of the ticket, contact card) work on this list. Integration checks
  RG-CORE-19 and RG-HOOK-37, skipped with an older Refresh (new `refresh_min` option of the checks).

## [1.13.0] — 2026-10-15

### Fixed
- **The way back to “All mailboxes” no longer disappears after handling a ticket.** When a ticket opened from “All
  mailboxes” was closed and the mailbox had no other active ticket, FreeScout showed that mailbox's list and the way
  back to “All mailboxes” was lost for the following tickets. Now, when FreeScout leaves the ticket for a mailbox
  list after an action (closing the last active ticket, “After sending: folder” setting, assignment to another agent,
  move…), the user goes back to the “All mailboxes” list the ticket was opened from (same filters, at the ticket's
  place). Going to the next ticket is unchanged.
- Integration check RG-HOOK-36 (`redirect_url` answer of FreeScout's ticket actions).

## [1.12.0] — 2026-10-14

Republished under a **new version number**: changes had been published without a new release number. FreeScout's
Manage › Modules page and UpdateGuard only offer an update when the version changes, so an installation already on
1.11.0 would not have received them. From now on **every published change gets a new version number**.

### Changes (since 1.10.0)
- **Spam left on the mail server** (setting “Do not import spam”, on by default); off: spam is imported with the tag
  `classified_spam`.
- **Origin tags of the e-mails** in English (`inbox`, `spam`, `trash`, `sent`, `drafts`, `archive`,
  `folder:<name>`, administrator's own names): badges on tickets, “E-mail tags” section of the panel, list filter,
  display setting, “Recalculate tags” button (`refreshglobal:mail-tags` command), API for other modules
  (`docs/api-tags.md`) and copy into the Tags module when installed.
- **Details / raw text** of each message: “i” button (Refresh's tools) or message menu; headers as received, message
  as plain text, Copy button, link to FreeScout's original source.
- **“← All mailboxes (N)” landmark** at the top of the panel on every Refresh view of one mailbox and on every ticket
  (also in the phone drawer).
- The module's stylesheet is loaded on every page (tickets, Refresh views).
- The GitHub release page now shows the CHANGELOG section of the version.

## [1.11.0] — 2026-10-14

### Added
- **Spam left on the mail server** (setting “Do not import spam”, **on by default**): an e-mail the mail server marked
  as spam (`X-Spam-Flag: YES`, `X-Spam-Status: Yes`, `X-Spam: yes`, Exchange SCL ≥ 5) or fetched from a spam folder
  (Spam, Junk, Courrier indésirable…) does not become a ticket; it stays in the mailbox on the server (marked as read
  by FreeScout). Off: it is imported with the tag `classified_spam`.
- **Origin tags of the e-mails** (setting, on by default): every fetched e-mail gets the tag of the server folder it
  comes from, **in English** whatever the server's language (`inbox`, `spam`, `trash`, `sent`, `drafts`, `archive`,
  `folder:<name>` for the others; extra “Folder name = tag” names in the settings). One tag per e-mail, grouped on
  the ticket:
  - shown (setting “Show tags”) on the tickets of “All mailboxes”, under the ticket subject, and in an **“E-mail
    tags”** section of the views panel with the number of tickets; list **filter** (`mt`, combinable, can be saved in
    a view);
  - **“Recalculate tags”** (settings): e-mails already in FreeScout are tagged by reading every folder of the IMAP
    mailboxes (in the background, `refreshglobal:mail-tags` command);
  - **for other modules** (Workflow Packs…): filters `refreshglobal.mail_tags` / `refreshglobal.thread_mail_tags`,
    action `refreshglobal.mail_tagged`, table `refreshglobal_mail_tags` (`docs/api-tags.md`); also copied into the
    official **Tags** module when installed (Workflows rules, FreeScout's search).
- **Details / raw text of a message**: “i” button on each message (Refresh's tools; message menu without Refresh):
  sender, recipients, date, Message-ID, mailbox, server folder and tags; **“Raw text”** tab (headers as received +
  message as plain text, Copy button); link to FreeScout's original source. Refresh hid FreeScout's “Original” window.
- **“← All mailboxes (N)” landmark** at the top of the views panel on **every** Refresh view of one mailbox
  (`/mailbox/N/tickets/…`) and on every ticket, also in the phone drawer, with “Mailbox: name”: the user is no longer
  “stuck” in one mailbox. It leads back to the last “All mailboxes” list (otherwise the whole list).
- The module's stylesheet is loaded on every page (FreeScout's `stylesheets` filter).
- Integration checks RG-HOOK-30…35, RG-CORE-18 (Tags module, skipped when absent), RG-DB-11.

### Migration
- New table `refreshglobal_mail_tags` (migration of the module, run on update).

## [1.10.0] — 2026-10-14

### Added
- **Choice of the sending address** on “Send an e-mail” and “New ticket” (FreeScout's create form, rebuilt by
  Refresh; also without Refresh and on phones). FreeScout ties this form to one mailbox and only shows a “From” field
  when that mailbox has aliases with “reply from aliases” on. Now:
  - **“From”** field (e-mail) with **every allowed address**: the address of each of the user's mailboxes (except
    archived ones) and the **aliases** of these mailboxes, grouped by mailbox; **“Mailbox”** field for a ticket;
  - an alias of the same mailbox applies at once (sent as `from_alias`); an address of **another mailbox** opens that
    mailbox's form **keeping what was already typed** (recipients, subject, message, contact, type, priority);
  - **suggested address**: the mailbox “All mailboxes” is filtered on, otherwise the **last address used** (this
    browser), otherwise the form's mailbox; opened from a mailbox or a ticket, that mailbox is kept;
  - replies in a ticket stay tied to the ticket's mailbox (and its aliases, FreeScout's feature).

### Security
- FreeScout applied any `from_alias` sent with a message without checking it belongs to the mailbox (a forged request
  could send from any address). An alias that is neither the address nor an alias of the ticket's mailbox is now
  removed before sending (`Http/Middleware/CheckFromAlias.php`).
- Integration checks RG-HOOK-28/29, RG-CORE-17, RG-ROUTE-11 (warning).

## Older versions

1.0.0 to 1.9.2: see the French changelog, [CHANGELOG.fr.md](CHANGELOG.fr.md).
