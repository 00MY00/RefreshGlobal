<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Thread;
use Modules\RefreshGlobal\Services\MailTags;
use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * Origin tags of the fetched e-mails, spam left on the mail server (Services/MailTags.php), message details
 * (Http/Controllers/ThreadDetailsController.php).
 */
class MailTagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Settings::setFlag(Settings::MAIL_TAGS, true);
        Settings::setFlag(Settings::SKIP_SPAM, true);
        Settings::setFlag(Settings::SHOW_MAIL_TAGS, true);
        \App\Option::set(Settings::MAIL_TAGS_FOLDERS, '');
        MailTags::$folder = null;
        MailTags::$pending = null;
    }

    protected function thread($conversation, $messageId, $type = Thread::TYPE_CUSTOMER, array $extra = [])
    {
        $t = new Thread();
        $t->conversation_id = $conversation->id;
        $t->type = $type;
        $t->state = Thread::STATE_PUBLISHED;
        $t->status = Thread::STATUS_ACTIVE;
        $t->body = $extra['body'] ?? '<p>Hello<br>world &amp; co</p>';
        $t->headers = $extra['headers'] ?? null;
        $t->message_id = $messageId;
        $t->source_via = Thread::PERSON_CUSTOMER;
        $t->source_type = Thread::SOURCE_TYPE_EMAIL;
        $t->save();

        return $t;
    }

    /** An e-mail as given by the IMAP library: getHeader()->raw, getMessageId(). */
    protected function message($id, $raw = '')
    {
        return new class($id, $raw) {
            public $id;
            public $raw;
            public function __construct($id, $raw) { $this->id = $id; $this->raw = $raw; }
            public function getHeader() { return (object) ['raw' => $this->raw]; }
            public function getMessageId() { return $this->id; }
        };
    }

    public function testFolderNamesInEnglish()
    {
        $cases = [
            'INBOX' => 'inbox', 'INBOX.Spam' => 'spam', 'Junk E-mail' => 'spam', 'Courrier indésirable' => 'spam', '[Gmail]/Spam' => 'spam',
            'INBOX/Corbeille' => 'trash', 'Deleted Items' => 'trash', 'Éléments envoyés' => 'sent', 'INBOX.Sent' => 'sent', 'Brouillons' => 'drafts',
            'Archives' => 'archive', 'INBOX.&AMk-l&AOk-ments supprim&AOk-s' => 'trash', // UTF7-IMAP "Éléments supprimés"
            'INBOX.[WP] Factures.Fournisseurs' => 'folder:wp-factures-fournisseurs', 'Clients' => 'folder:clients',
        ];
        foreach ($cases as $folder => $tag) {
            $this->assertSame($tag, MailTags::tagForFolder($folder), $folder);
        }
        $this->assertNull(MailTags::tagForFolder(''));
        // the administrator's own names
        \App\Option::set(Settings::MAIL_TAGS_FOLDERS, "Mes pourriels = spam\nClients = Customers");
        $this->assertSame('spam', MailTags::tagForFolder('INBOX.Mes pourriels'));
        $this->assertSame('customers', MailTags::tagForFolder('Clients'));
    }

    public function testSpamHeaders()
    {
        foreach (["X-Spam-Flag: YES\r\n", "Subject: x\nX-Spam-Status: Yes, score=7.1\n", "X-Spam: yes\n", "X-MS-Exchange-Organization-SCL: 6\n"] as $h) {
            $this->assertTrue(MailTags::isSpam($h), $h);
        }
        foreach (['', "X-Spam-Flag: NO\n", "X-Spam-Status: No, score=0.2\n", "X-MS-Exchange-Organization-SCL: 1\n", "Subject: X-Spam: yes\n"] as $h) {
            $this->assertFalse(MailTags::isSpam($h), $h);
        }
    }

    public function testSpamIsLeftOnTheServerByDefault()
    {
        $mb = $this->s['support'];
        // spam by its headers
        MailTags::onFolder($mb, (object) ['path' => 'INBOX']);
        $this->assertFalse(MailTags::shouldSave(true, ['message_id' => 'a@x', 'message' => $this->message('a@x', "X-Spam-Flag: YES\n")]));
        // spam by its folder
        MailTags::onFolder($mb, (object) ['path' => 'INBOX.Junk']);
        $this->assertFalse(MailTags::shouldSave(true, ['message_id' => 'b@x', 'message' => $this->message('b@x', '')]));
        // a normal e-mail: imported, its tags wait for its thread
        MailTags::onFolder($mb, (object) ['path' => 'INBOX']);
        $this->assertTrue(MailTags::shouldSave(true, ['message_id' => '<c@x>', 'message' => $this->message('c@x', "Subject: hi\n")]));
        $this->assertSame(['inbox'], MailTags::$pending['tags']);
        // another module already said no: left as it is
        $this->assertFalse(MailTags::shouldSave(false, ['message_id' => 'd@x', 'message' => $this->message('d@x', '')]));
    }

    public function testSpamImportedWithItsTagWhenAllowed()
    {
        Settings::setFlag(Settings::SKIP_SPAM, false);
        $c = $this->s['t_support_open'];
        MailTags::onFolder($c->mailbox, (object) ['path' => 'INBOX']);
        $this->assertTrue(MailTags::shouldSave(true, ['message_id' => 'spam-'.$this->s['prefix'].'@x', 'message' => $this->message('x', "X-Spam-Status: Yes\n")]));
        $t = $this->thread($c, 'spam-'.$this->s['prefix'].'@x');
        // FreeScout's action after the e-mail is saved
        \Eventy::action('conversation.created_by_customer', $c, $t, null);
        $this->assertSame([$c->id => ['classified_spam', 'inbox']], MailTags::forConversations([$c->id]));
        $this->assertSame(['tags' => ['classified_spam', 'inbox'], 'folder' => 'INBOX'], MailTags::forThreads([$t->id])[$t->id]);
        // read API for the other modules
        $this->assertSame(['classified_spam', 'inbox'], \Eventy::filter('refreshglobal.mail_tags', [], [$c->id])[$c->id]);
        $this->assertSame('INBOX', \Eventy::filter('refreshglobal.thread_mail_tags', [], [$t->id])[$t->id]['folder']);
        // the thread of ANOTHER message gets nothing
        MailTags::onFolder($c->mailbox, (object) ['path' => 'INBOX.Archives']);
        MailTags::shouldSave(true, ['message_id' => 'other@x', 'message' => $this->message('other@x', '')]);
        $t2 = $this->thread($c, 'not-the-same@x');
        \Eventy::action('conversation.customer_replied', $c, $t2, null);
        $this->assertArrayNotHasKey($t2->id, MailTags::forThreads([$t2->id]));
    }

    public function testFeatureOff()
    {
        Settings::setFlag(Settings::MAIL_TAGS, false);
        MailTags::onFolder($this->s['support'], (object) ['path' => 'INBOX.Spam']);
        $this->assertTrue(MailTags::shouldSave(true, ['message_id' => 'e@x', 'message' => $this->message('e@x', "X-Spam-Flag: YES\n")]));
        $this->assertNull(MailTags::$pending);
    }

    public function testListFilterBadgesAndPanel()
    {
        $s = $this->s;
        $t1 = $this->thread($s['t_support_open'], 'l1@x');
        $t2 = $this->thread($s['t_sales_open'], 'l2@x');
        MailTags::attach($s['t_support_open'], $t1, ['inbox'], 'INBOX');
        MailTags::attach($s['t_sales_open'], $t2, ['inbox', 'classified_spam'], 'INBOX');
        $page = $this->ticketsPage($s['admin'], ['mt' => 'classified_spam']);
        $this->seeIn($page, $s['prefix'].'-SALES-OPEN');
        $this->dontSeeIn($page, $s['prefix'].'-SUPPORT-OPEN');
        $this->seeIn($page, 'rg-mail-tag rg-mail-tag-spam');
        $this->seeIn($page, 'data-sec="rg-mail-tags"');
        // "show the tags" off: kept but not shown
        Settings::setFlag(Settings::SHOW_MAIL_TAGS, false);
        $page = $this->ticketsPage($s['admin']);
        $this->dontSeeIn($page, 'data-sec="rg-mail-tags"');
        $this->dontSeeIn($page, 'rg-mail-tag ');
        // deleted for good: rows removed
        $s['t_sales_open']->deleteForever();
        $this->assertSame([], MailTags::forThreads([$t2->id]));
    }

    public function testRecalcReadsTheFoldersOfTheMailServer()
    {
        $mb = $this->s['support'];
        $mb->in_protocol = \App\Mailbox::IN_PROTOCOL_IMAP;
        $mb->in_server = 'imap.example.test';
        $mb->save();
        $p = $this->s['prefix'];
        $ta = $this->thread($this->s['t_support_open'], 'ra-'.$p.'@x');
        $tc = $this->thread($this->s['t_support_pending'], '<rc-'.$p.'@x>');
        $folders = [
            'INBOX' => [$this->message('<ra-'.$p.'@x>', "X-Spam-Flag: YES\n"), $this->message('unknown@x', '')],
            'INBOX.[WP] Invoices' => [$this->message('rc-'.$p.'@x', '')],
        ];
        $client = new class($folders) {
            public $f;
            public function __construct($f) { $this->f = $f; }
            public function connect() { return $this; }
            public function disconnect() { return $this; }
            public function getFolders($h = true) {
                $out = [];
                foreach ($this->f as $path => $messages) {
                    $out[] = new class($path, $messages) {
                        public $path;
                        public $no_select = false;
                        public $m;
                        public $page = 1;
                        public $n = 100;
                        public function __construct($path, $m) { $this->path = $path; $this->m = $m; }
                        public function query() { return $this; }
                        public function all() { return $this; }
                        public function setFetchBody($b) { return $this; }
                        public function leaveUnread() { return $this; }
                        public function limit($n, $page) { $this->page = $page; $this->n = $n; return $this; }
                        public function get() { return array_slice($this->m, ($this->page - 1) * $this->n, $this->n); }
                    };
                }
                return $out;
            }
        };
        $r = MailTags::recalc(null, function () use ($client) { return $client; });
        $this->assertSame([], $r['errors']);
        $this->assertSame(['classified_spam', 'inbox'], MailTags::forThreads([$ta->id])[$ta->id]['tags']);
        $this->assertSame(['folder:wp-invoices'], MailTags::forThreads([$tc->id])[$tc->id]['tags']);
        $this->assertSame('done', MailTags::recalcStatus()['state']);
        // a second run adds nothing
        $this->assertSame(0, MailTags::recalc(null, function () use ($client) { return $client; })['tagged']);
    }

    public function testSettingsAndRecalcRequest()
    {
        $page = $this->actingAs($this->s['admin'])->get(route('settings', ['section' => 'refreshglobal']));
        foreach ([Settings::MAIL_TAGS, Settings::SKIP_SPAM, Settings::SHOW_MAIL_TAGS, Settings::MAIL_TAGS_FOLDERS] as $key) {
            $this->seeIn($page, 'name="settings['.$key.']"');
        }
        $this->actingAs($this->s['bob'])->post(route('refreshglobal.mail_tags.recalc'))->assertStatus(403);
        $this->actingAs($this->s['admin'])->post(route('refreshglobal.mail_tags.recalc'))->assertRedirect(route('settings', ['section' => 'refreshglobal']));
        $this->assertSame('requested', MailTags::recalcStatus()['state']);
        // the scheduled task (every minute) picks it up
        $commands = array_map(function ($e) { return (string) $e->command; }, app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $this->assertNotEmpty(array_filter($commands, function ($c) { return strpos($c, 'refreshglobal:mail-tags --requested') !== false; }));
        \App\Option::set(MailTags::OPTION_RECALC, '');
    }

    public function testThreadDetailsAndRawText()
    {
        $s = $this->s;
        $t = $this->thread($s['t_support_open'], 'td-'.$s['prefix'].'@x', Thread::TYPE_CUSTOMER, [
            'headers' => "Received: from mx.example.test\nX-Spam-Status: No\nSubject: Printer", 'body' => '<p>Line one</p><p>Line &amp; two</p>',
        ]);
        MailTags::attach($s['t_support_open'], $t, ['inbox'], 'INBOX');
        $page = $this->actingAs($s['bob'])->get(route('refreshglobal.thread_details', ['id' => $t->id]));
        $page->assertStatus(200);
        $this->seeIn($page, 'td-'.$s['prefix'].'@x');
        $this->seeIn($page, 'Received: from mx.example.test');
        $this->seeIn($page, "Line one\nLine &amp; two");
        $this->seeIn($page, 'rg-mail-tag');
        $this->seeIn($page, e(route('conversations.ajax_html', ['action' => 'show_original', 'thread_id' => $t->id])));
        // a ticket of a mailbox the user can not see
        $t2 = $this->thread($s['t_billing_other'], 'td2@x');
        $this->actingAs($s['bob'])->get(route('refreshglobal.thread_details', ['id' => $t2->id]))->assertStatus(403);
        $this->actingAs($s['bob'])->get(route('refreshglobal.thread_details', ['id' => 999999999]))->assertStatus(404);
    }
}
