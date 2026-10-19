# Origin tags of the e-mails — API for other modules (RefreshGlobal 1.11.0)

RefreshGlobal tags every e-mail FreeScout fetches with the folder of the mail server it came from, and marks the spam
that was imported. Other modules (for example Workflow Packs rules) can read these tags. There is no hard dependency:
every call below returns its default value when RefreshGlobal is missing or older.

## Tags (always in English)

| Tag | Meaning |
|---|---|
| `inbox`, `spam`, `trash`, `sent`, `drafts`, `archive` | standard folder of the mail server, recognised in several languages (`Junk`, `Courrier indésirable`, `Papierkorb`, `Éléments envoyés`, `[Gmail]/Spam`…) |
| `folder:<name>` | any other folder, as a slug of its path without `INBOX` (`INBOX.[WP] Invoices.Suppliers` → `folder:wp-invoices-suppliers`) |
| custom | names set by the administrator: “Folder name = tag” (Manage › Settings › RefreshGlobal) |
| `classified_spam` | the mail server marked it as spam (`X-Spam-Flag: YES`, `X-Spam-Status: Yes`, `X-Spam: yes`, Exchange `SCL` ≥ 5) or it came from a spam folder — only when spam is imported (setting “Do not import spam” off; by default spam stays on the mail server and never becomes a ticket) |

One row per e-mail (thread) and tag; a ticket has the tags of all its e-mails.

## Read

```php
// tickets: [conversation id => ['classified_spam', 'inbox', …]] (sorted), only the tickets that have tags
$tags = \Eventy::filter('refreshglobal.mail_tags', [], $conversationIds);

// e-mails: [thread id => ['tags' => [...], 'folder' => 'INBOX.Junk']] (folder of the mail server, UTF-8)
$threads = \Eventy::filter('refreshglobal.thread_mail_tags', [], $threadIds);
```

## Be told when an e-mail gets tags

```php
\Eventy::addAction('refreshglobal.mail_tagged', function ($conversationId, $threadId, array $newTags) {
    // e.g. apply a rule when 'classified_spam' is in $newTags
}, 20, 3);
```

Fired while FreeScout fetches the e-mail (after the ticket and its thread are saved), and by the recalculation of
the tags (Manage › Settings › RefreshGlobal › “Recalculate the tags”, command `php artisan refreshglobal:mail-tags`).

## Be told when an e-mail is moved to another folder (RefreshGlobal ≥ 1.16.0)

```php
\Eventy::addAction('refreshglobal.mail_moved', function ($conversationId, $threadId, $fromFolder, $toFolder) {
    // folders as UTF-8 paths of the mail server ("INBOX", "INBOX.[WP] Purchases.Deliveries"…); the tags of the
    // e-mail were already updated (tag of the old folder replaced by the tag of the new one)
}, 20, 4);
```

Fired by “Move to a folder” (ticket page, selection bar, swipe on touch screens) and by its “Undo”.

## SQL (read only)

Table `refreshglobal_mail_tags`: `conversation_id`, `thread_id`, `mailbox_id`, `tag` (≤ 100), `folder` (≤ 191,
folder of the mail server), `created_at`; unique (`thread_id`, `tag`), indexes `conversation_id` and
(`mailbox_id`, `tag`). Rows are removed with their ticket (`conversations.before_delete_forever`, priority 1000).

## FreeScout's Tags module

When the official Tags module is installed and active, the same tags are also added to the tickets there
(`\Modules\Tags\Entities\Tag::attachByName()`): they can then be used directly by the conditions of the Workflows
module and by FreeScout's search.
