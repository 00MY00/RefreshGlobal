# Changelog

All notable changes to RefreshGlobal. This English file is the reference for the GitHub release notes; the French
version is [CHANGELOG.fr.md](CHANGELOG.fr.md). Both are updated together for every release.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) — versions: [SemVer](https://semver.org/).

Every published change gets a new version number: FreeScout's Manage › Modules page and UpdateGuard only offer an
update when the version changes.

## [1.16.2] — 2026-10-20

### Fixed
- **Untranslated texts of the dashboard and the list.** The all-mailboxes dashboard (KPIs, chart, “Unresolved
  tickets” card with its Open / Pending columns, “Undelivered e-mails”, tiles) and the “Hover previews” entry used
  Refresh's or FreeScout's translations, which only exist in French and German: in Spanish, Italian, Dutch and
  Brazilian Portuguese they showed in English (“Open” was even translated in French only). They now use the module's
  own strings, in its 7 languages (same words as Refresh in French and German), and no longer depend on Refresh's
  translation files.

### Documentation
- The English changelog now covers every version (1.0.0 to 1.9.2 translated too).

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

## [1.9.2] — 2026-10-14

### Fixed
- **Phone: long-press selection, stronger protection.** 1.9.1 only ignored the click sent on release. A real phone can
  also untick the ticket another way: FreeScout's native long press (`taphold`, `public/js/main.js`
  `conversationsTableInit()`) triggered again by the mouse events some phones send after a touch (`mousedown` /
  `mouseup` / `click`). Now, once a long press has ticked a ticket, **any other change of its checkbox** during the
  same press, or within 1.5 s after the release (before the next touch), is undone whatever its source; the card keeps
  its selection colour.

### Added
- **Touch log** for troubleshooting: `…/refresh-global/tickets?rg_debug=touch` shows at the top of the page what the
  phone sends (touches, context menu, mouse events, clicks, checkbox changes, undone changes); `?rg_debug=off` stops
  it (remembered for the browser tab only).

## [1.9.1] — 2026-10-14

### Fixed
- **Phone: long-press selection** on “All mailboxes”. A long press selected the ticket, then unselected it on
  release, and the selection mode closed: no action was possible on one or several tickets. Cause: Refresh's phone
  script (`Modules/Refresh/Public/js/mobile.js:260-298`) ticks the ticket after 500 ms, then only ignores the “click”
  sent on release when it comes less than 800 ms after that moment; a deliberate press longer than ~1.3 s had that
  click handled as a tap in selection mode, which unticked the ticket (same defect on Refresh's own list). Now, when
  the ticket's checkbox changed while the finger was down, the following click is ignored whatever the length of the
  press; short taps keep Refresh's behaviour (open the ticket, or tick / untick in selection mode). No more text
  selection or phone menu on the card during the long press.

## [1.9.0] — 2026-10-13

### Changed
- **Updates through FreeScout or through [UpdateGuard](https://github.com/00MY00/UpdateGuard)**: RefreshGlobal no
  longer has an updater of its own. Its `module.json` declares FreeScout's standard keys (`latestVersionUrl` →
  `version.txt` of the latest release, `latestVersionZipUrl` → `RefreshGlobal.zip`, `SHA256SUMS` next to it):
  - **without UpdateGuard**: the “Update” button of **Manage › Modules** appears when a new version is published (as
    for other modules; no backup or rollback); the settings and the diagnostics page **recommend installing
    UpdateGuard**;
  - **with UpdateGuard** active: it updates RefreshGlobal safely (SHA-256, backup, health check, automatic rollback,
    automatic updates when allowed); the settings and the diagnostics link to its RefreshGlobal page.
- Removed: the module's own automatic update (checkbox, “Check for updates”, “Update now”, scheduled tasks,
  `refreshglobal:update` command, `REFRESHGLOBAL_UPDATE_URL` / `…_AUTO_UPDATE_TIME` settings) and the RG-CORE-08
  check. The two options it left are erased by a migration; its old backups (`storage/app/refreshglobal/backups/`)
  stay in place and can be deleted by hand. `install.sh --auto-update` has no effect any more and recommends
  UpdateGuard.

### Added
- **`php artisan refreshglobal:health [--json]`** command (`healthCommand` of `module.json`), run by UpdateGuard after
  an update: module files, grouped compatibility checks (classes, views, routes, hooks, assets, database) and the page
  really rendered for an administrator. Exit code 0 = healthy.
- `module.json`: `"updateGuard": {"migrations": "backward-compatible"}` (the module's migrations add optional tables
  or columns: the previous version works with them); the release publishes `version.txt`.
- Integration checks RG-ROUTE-09 (FreeScout's Modules page) and RG-ROUTE-10 (UpdateGuard, skipped when absent).

### Compatibility
- An installation on 1.8.x **still receives this version through its old automatic update** (same release, same
  `SHA256SUMS`): `refreshglobal:check` and `refreshglobal:selftest`, which it runs after installing, are kept.

## [1.8.2] — 2026-10-12

### Added
- **Back to “All mailboxes” from a ticket.** Refresh's breadcrumb (and the phone's back arrow, which reads it) led to
  the last Refresh view of **one** mailbox, or to “All tickets” of the ticket's mailbox. For a ticket opened from “All
  mailboxes”, its first item becomes “All mailboxes” (or the name of the saved view) and leads back to the original
  list, **at the place of the opened ticket** (also after replying or moving to the next ticket). Remembered per
  browser tab; opening another list (Refresh view, mailbox, search, dashboard) brings back Refresh's normal
  breadcrumb. Integration checks RG-HOOK-25…27 (warning).
- **Landmark above the views panel on the ticket page**: “← All mailboxes (N)” and “Mailbox: <name>”. That panel is
  Refresh's for the ticket's mailbox: its numbers only concern that mailbox (≈ 50 instead of ≈ 800), which felt like
  being “stuck” elsewhere.
- **“Automatic folder … — N ticket(s)” banner** above the list when a Workflow Packs folder is chosen, always visible,
  with **“Leave the folder”** (the other filters are kept).

### Changed
- Going back to the list (after deleting a ticket, ticket breadcrumb) happens **without the automatic folder**: the
  user is no longer “stuck” in a folder. A saved view holding a folder is replaced by its filters without the folder.
  The “go to the next ticket” setting still looks for the next one in the list shown.

## [1.8.1] — 2026-10-12

### Fixed
- **“Filters” button hidden under the filters panel** on medium screens (laptop, 1080p screen zoomed to 125–150 %):
  the toolbar overflowed the list, the button went under the panel and the panel could no longer be folded. The
  toolbar never overflows the list any more.

### Improved
- **Layout by the room really available** (CSS container queries: they also follow the views panel, folded or not),
  “All mailboxes” page only, computer and tablet; phones keep Refresh's layout:
  - **sliding filters panel**: when the list and the panel do not fit side by side (list under ~760 px), the panel no
    longer takes room from the list; it opens **over** it from the right (“Filters” button) and closes with ×,
    Escape or a click outside. On a large screen it stays on the right and **folds to the side** with × (choice
    remembered, like Refresh's “Filters” button);
  - **“Active filters” bar** above the list when the panel is folded or closed: one chip per filter (search,
    mailboxes, statuses, assignee, Refresh view, automatic folder) with × to remove it, “Edit” (opens the panel) and
    “Clear filters”; the “Filters” button is coloured when a filter is active;
  - compact **toolbar** when the list is narrow (“Sort by” labels hidden, “Export” as an icon, then the
    “1 - 30 of N” counter hidden);
  - **more readable cards**: the right column (priority / agent / status) shrinks with the list, the subject keeps
    the room (at 1280 px: ~460 px of subject instead of ~200); the mailbox address is hidden (name only, address in
    the tooltip) when room is short; **table** layout: customer and priority / agent / status columns narrowed
    (“Ticket” column from ~90 to ~320 px at 1280 px).
  - Phone: Refresh's “mailbox” pill shows the name only (the address overflowed the card).
  - The automatic refresh waits until the panel opened over the list is closed.

## [1.8.0] — 2026-10-12

### Added
- **Automatic folders of [Workflow Packs](https://github.com/00MY00/WorkflowPacks)** (only when Workflow Packs
  **1.14.0 or later** is installed and active; nothing changes otherwise). Workflow Packs files e-mails into folders
  of the mail server (“[WP] Invoices › Suppliers”) and keeps the current folder of each ticket; RefreshGlobal reads it
  (table `workflowpacks_ticket_folders`, read only) to keep an overview:
  - **“Automatic folders”** section in the views panel (and the phone drawer): a tree of the folders of all your
    mailboxes, each with the number of tickets of the list shown (sub-folders included); a click filters the list on
    that folder. The filter (`wf` in the address) combines with the others, can be saved in a view, follows the CSV
    export and the automatic refresh;
  - **folder badge** on every ticket of the list (next to the mailbox), also on phones;
  - **“Overview”** page (`/refresh-global/folders`): per folder, tickets, open tickets, split per mailbox, last filing
    and automatic clean-up set in Workflow Packs; links to the rules and history of Workflow Packs for those who have
    access.
  - Same rights as the list: everyone only sees the folders and tickets of their mailboxes (and only their own
    tickets with “see only assigned conversations”). A ticket whose e-mail was removed by Workflow Packs' automatic
    clean-up is no longer counted in the folder.
- Integration checks RG-DB-09/10, RG-ROUTE-07/08 (skipped without Workflow Packs) and RG-CORE-16.

### Fixed
- **Permanent deletion and Workflow Packs folders**: the e-mail of a ticket that Workflow Packs had moved into one of
  its folders (“INBOX.[WP] Invoices”) was not found in the folders FreeScout fetches, so it was not moved to the
  server's trash. The folder is now recorded at deletion time (new `imap_path` column of the queue, module
  migration) and the e-mail is looked for there too.

## [1.7.1] — 2026-10-11

### Added
- **Loading animation** during an action, as in the Workflow Packs module: the clicked button shows FreeScout's
  spinning icon (`glyphicon-spin`) and the buttons are disabled while it runs (no double submit). For every action of
  the module: filters, saved views, “Empty trash”, language, settings, diagnostics (“Run the checks again”). Nothing
  spins when a confirmation is cancelled; everything is reset with the browser's “Back” button.

## [1.7.0] — 2026-10-10

### Added
- **“Fetch e-mails”** button (circular arrow) on the “All mailboxes” page (toolbar; drawer on phones), **only when the
  [SyncNow](https://github.com/rabsym/freescout-syncnow) module is installed and active** and the user may use it
  (administrator, or SyncNow's “Force sync” permission). It fetches the IMAP mailboxes of the list shown at once, one
  after the other, with SyncNow's own calls (`syncnow.force` then `syncnow.status` until done: SyncNow's history and
  lock handled normally); the icon spins during the fetch, the list reloads when there are new e-mails, otherwise a
  message (“No new e-mail”, SyncNow's delay before a new fetch…). Integration checks RG-ROUTE-05/06 (skipped without
  SyncNow).

## [1.6.0] — 2026-10-10

### Added
- **Automatic refresh** of the “All mailboxes” list and of the all-mailboxes dashboard: every N seconds (setting, 30
  by default, 0 = off) the page asks the module for the “fingerprint” of what it shows (`GET /refresh-global/state`,
  same filters and rights; one aggregate query) and reloads itself when a ticket was added, changed or deleted —
  never while the user ticks tickets, types text, or has a menu / window / phone panel open. “Keep my place in the
  list” restores the same position. The dashboard's one-minute cache takes this fingerprint into account: the figures
  shown after a refresh are always up to date.

## [1.5.3] — 2026-10-10

### Fixed
- GitHub CI: the shellcheck task (in “lint (8.2)”) had failed since the first version on an `A && B || C` construct of
  `install.sh` (SC2015, `--rollback`), rewritten as an `if` (same behaviour). Since the `release` job depends on
  `lint`, **no GitHub release had ever been created**; they now are on every tag (archive, `install.sh`,
  `module.json`, `SHA256SUMS`).
- CI: `actions/checkout@v5` (Node.js 24) instead of v4 (“Node.js 20 is deprecated” warning).

## [1.5.2] — 2026-10-10

### Fixed
- **Permanent deletion: the e-mails stayed on the mail server.** FreeScout only copies e-mails: it never deletes them
  from the server. Now, when a user deletes a ticket for good (“Delete forever”, bulk deletion of tickets already in
  the trash, FreeScout's or the module's “Empty trash”, automatic emptying), its e-mails are **moved to the trash of
  the mail server** (IMAP) within a minute: they leave the inbox but can still be recovered from the webmail.
  - The e-mails are recorded at deletion time (FreeScout's `conversations.before_delete_forever` action), then moved
    by the `refreshglobal:mail-trash` task (every minute) with FreeScout's IMAP connection: the deletion itself never
    waits for the mail server; on error, it is retried (5 times).
  - Customer e-mails are looked for in the folders FreeScout fetches; agent replies in the mailbox's “Sent” folder
    when it is set.
  - Server trash found automatically (Trash, Corbeille, Deleted Items, [Gmail]/Trash…) or set in the settings. POP3
    mailboxes: not possible (skipped).
  - Never for FreeScout's other deletions (deletion of a whole mailbox, e-mail that can not be imported).
  - Setting “Also on the mail server” (on by default) and queue state in Manage › Settings › RefreshGlobal › Trash.
    Integration checks RG-HOOK-24, RG-CORE-14, RG-CORE-15, RG-DB-07, RG-DB-08.

## [1.5.1] — 2026-10-10

### Added
- Setting **“Keep my place in the list”** (on by default): after a deletion, the “All mailboxes” list opens where the
  user was, the next ticket in place of the deleted one, instead of going back to the top. Works for deletion from
  the ticket (computer and phone) and for bulk or swipe deletion from the list (page reload), also when tickets above
  disappear: the position is set again on a landmark ticket, not on a number of pixels.
- Phone: **“Delete forever”** in Refresh's “Ticket actions” for a ticket in the trash (Refresh offered no deletion in
  that case); the entry clicks FreeScout's button (its confirmation and rights). Integration check RG-HOOK-23.

## [1.5.0] — 2026-10-09

### Added
- **Empty the trash**: “Empty the trash (N)” button in Manage › Settings › RefreshGlobal and at the bottom of the views
  panel of “All mailboxes” (and the phone drawer), with confirmation. The tickets in the trash are deleted for good
  with their e-mails, through FreeScout's own function (`Conversation::deleteConversationsForever`), with the same
  rules as its per-mailbox “Empty trash”: administrators or users allowed to delete conversations, their mailboxes
  only, their own tickets only when they only see assigned tickets.
- **Automatic emptying**: setting “Empty automatically — N days” (0 = never, the default). Every day (03:45,
  `REFRESHGLOBAL_TRASH_AUTO_TIME`), tickets in the trash for more than N days are deleted for good, in every mailbox;
  each ticket therefore stays recoverable for at least N days. Command `php artisan refreshglobal:trash
  --older-than=N`.
- The mail server is never touched.
- Integration checks RG-CORE-13 (FreeScout's permanent deletion) and RG-DB-06 (date of entry into the trash).

## [1.4.5] — 2026-10-09

### Changed
- Clearer update state (settings and diagnostics, same block):
  - **up to date** (for example right after a successful update): “Installed version: 1.4.5 — Up to date”, without
    “latest: … (main branch …)” that looked like a new version;
  - **new version**: “new version: X” (with the source when it is the `main` branch);
  - **requested** then **running** (“Updating to X…”): buttons disabled and the page reloads by itself until done.
    “Running” comes from the lock really held by the update.
- “Last update: successful (installed and verified)” instead of “Last automatic update: installed” (the update can
  come from the button).
- “Check for updates” and “Update now” buttons side by side.

## [1.4.4] — 2026-10-09

### Changed
- Manage › Settings › RefreshGlobal: links become real buttons (“Open the “All mailboxes” page”, “RefreshGlobal
  diagnostics”, “Change my language”, “Default language (General)”).
- Diagnostics page: “Run the checks again”, “Open the “All mailboxes” page” and “Settings” buttons.
- Views panel of “All mailboxes”: “RefreshGlobal diagnostics” as a button.

### Fixed
- Tests: the “Check for updates” button test restores the update state file as it was.

## [1.4.3] — 2026-10-09

### Changed
- **Without a published release, the current version of the `main` branch is used**: “Check for updates”, the
  automatic update / “Update now” and `install.sh` (without `--version`) fall back to `main` when GitHub answers 404
  for the latest release (repository with tags only). There is no SHA256SUMS file for a branch: integrity relies on
  HTTPS; the archive's hash is written to the log, and the post-install check with automatic rollback stays in place.
  A published release still comes first. Configurable addresses (`REFRESHGLOBAL_UPDATE_BRANCH_MANIFEST`,
  `REFRESHGLOBAL_UPDATE_BRANCH_ZIP`, `RG_BRANCH_ZIP_URL` for `install.sh`); empty value = no fallback.

### Fixed
- “Check for updates”: the raw HTTP 404 error is replaced by a clear message when neither a release nor the `main`
  branch can be read.

## [1.4.2] — 2026-10-09

### Added
- **Language selector** at the bottom of the views panel of “All mailboxes” (and the phone drawer). There is only one
  language choice, the user's FreeScout profile one, followed by FreeScout, Refresh and the module: the selector saves
  it like the Profile page, so the whole interface changes language together. Languages offered: FreeScout's; the
  module is translated into 7 languages (English for the others).
- Manage › Settings › RefreshGlobal: current language, “Change my language” and “Default language” links.
- Integration check RG-VIEW-06 (FreeScout's list of languages).

## [1.4.1] — 2026-10-09

### Added
- **“Check for updates”** button (Manage › Settings › RefreshGlobal and diagnostics page): reads the `module.json` of
  the latest published version at once (15 s timeout, nothing is installed) and tells whether a newer version exists,
  whether it needs a newer FreeScout, or why the check failed. Date of the last check shown.

## [1.4.0] — 2026-10-09

### Added
- **Refresh's “My dashboard” over all mailboxes**: Refresh computes its dashboard for the first mailbox only; the
  module replaces that block (`dashboard.before` filter) with the same dashboard, with the same classes and Refresh's
  functions (`Views::query`, `Dashboard::chart/delta/duration`), for all the user's mailboxes and with their rights.
  Tiles, agents and undelivered e-mails per mailbox, list of unresolved tickets with their mailbox. Setting
  “Dashboard of all mailboxes” (on by default).
- **Refresh view** filter (`rv`) on “All mailboxes” (unresolved, overdue, due today, open, pending, unassigned, new),
  with Refresh's definition; removable chip in the filters.
- **Deleting a ticket** (middleware on `conversations.ajax`): back to “All mailboxes” with the last filters, or to the
  next ticket of that list (setting “Go to the next ticket”); setting “Delete permanently” (the ticket and its e-mails
  are erased from FreeScout, also in bulk deletion; the mail server is not touched). Default: back to the list, trash.
- Setting **“Mailbox above each ticket”** (on by default): name **and address** of the mailbox.
- Settings shown as switches, like FreeScout's.
- Integration checks RG-HOOK-18…22, RG-CORE-09…12, RG-ROUTE-04, with their own effect texts.

### Fixed
- French, Refresh on phones: “Créé 7h il y a” / “Fermé 7h il y a” become “Créé il y a 7h” / “Fermé il y a 7h”
  (correction applied to Refresh's dictionary in the page, without changing Refresh, and only while Refresh keeps the
  old wording).

## [1.3.0] — 2026-10-09

### Added
- **Manage › Settings › RefreshGlobal** section (like Refresh): option “replace Refresh's Tickets entry”, automatic
  update, installed / available versions, last result, links to the page and the diagnostics.
- **“Update now”** button (settings and diagnostics): the safe update is started within a minute by the scheduled
  task `refreshglobal:update --requested` (never during a web request), even when the daily update is off.
- Integration checks RG-HOOK-15…17 (`settings.sections`, `settings.section_settings`, `settings.view` filters).

## [1.2.1] — 2026-10-09

### Fixed
- `refreshglobal:selftest` answered HTTP 403 on a server whose `APP_URL` is not `localhost` (FreeScout's `TrustHosts`
  check): an update through `install.sh --update` (and the automatic update) was then wrongly cancelled by the
  automatic rollback. The self-test now uses the full `APP_URL` address.

## [1.2.0] — 2026-10-09

### Added
- Phone: “All mailboxes” tab in Refresh's bottom tab bar (script `Public/js/shell.js`, loaded like Refresh's scripts).
- Option (diagnostics page, administrators): replace Refresh's “Tickets” entry with “All mailboxes” in the left bar
  and the tab bar; Refresh's entry is hidden, not removed. Default value: `REFRESHGLOBAL_REPLACE_REFRESH_TICKETS`.
- Integration checks RG-HOOK-11…14 (`javascripts` / `layout.head` filters, Refresh's `.rf-m-tab-tickets` and
  `fd-all-tickets` elements).

## [1.1.0] — 2026-10-09

### Added
- Daily automatic update (off by default) with automatic rollback: mandatory SHA-256 hash, backup, install,
  `refreshglobal:check` + `refreshglobal:selftest`, back to the previous version (files, saved views, migrations) when
  the new one does not work; a cancelled version is not retried; banner for administrators. Command
  `php artisan refreshglobal:update` (`--check`, `--enable`, `--disable`, `--force`), setting on the diagnostics page,
  option `install.sh --auto-update=on|off`.
- Command `php artisan refreshglobal:selftest`: renders the page for an administrator and checks it.
- `install.sh --update` goes back to the previous version automatically when the new one is blocking
  (`--no-auto-rollback`).
- Module image for Manage › Modules (`Public/img/module.svg`).
- Integration checks RG-HOOK-10 (scheduler), RG-CORE-07 (`App\Option`), RG-CORE-08 (ZipArchive, Guzzle, Symfony
  Process).

## [1.0.1] — 2026-10-09

### Fixed
- Phone: Refresh's mobile version hides its left bar and toolbar; the “All mailboxes” page is now reachable from the
  views drawer (`mailbox.after_sidebar_buttons` hook, also in FreeScout's native sidebar), and its drawer shows the
  section titles and the “Export (CSV)” link.
- Full installation: instruction for the “All files … will be removed” question of the official script.

## [1.0.0] — 2026-10-09

First version.

### Added
- “All mailboxes” page (`/refresh-global/tickets`): tickets of every allowed mailbox, FreeScout's native list with a
  “Mailbox” column; a ticket opens on its native page.
- Filters by mailbox (multi-select), status, assignee; search on subject / customer / e-mail / number; sort; filters
  in the address.
- Counters per mailbox and per status (grouped queries).
- Streamed CSV export (UTF-8 with BOM, configurable limit, protection against formulas).
- Personal saved views (create, rename, default view, delete); table `refreshglobal_saved_views`.
- Same look as Refresh when it is installed (structure and classes reused, no file copied); “All mailboxes” entry in
  the menu and in Refresh's sidebar; phone version.
- Compatibility check (`php artisan refreshglobal:check`, `/refresh-global/diagnostic` page, check cached when pages
  load) with OK / warning / degraded / blocking states and `RG-xxx` messages.
- Translations: French, English, German, Spanish, Italian, Dutch, Brazilian Portuguese.
- `install.sh` installer: add, full installation (`--full`), `--update`, `--uninstall`, `--rollback`, `--dry-run`,
  `--yes`; time-stamped backups, log without passwords.
- Module PHPUnit tests (access, compatibility, export, saved views, translations) and installer test bench in Docker.
