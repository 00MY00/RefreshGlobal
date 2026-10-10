<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Conversation;
use App\Mailbox;
use App\Thread;
use Modules\RefreshGlobal\Services\MailServerTrash;
use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Services\Trash;
use Modules\RefreshGlobal\Tests\TestCase;

/** E-mails of the tickets deleted for good -> mail server's trash (Services/MailServerTrash.php), with a fake IMAP server. */
class MailServerTrashTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Settings::setServerTrash(true);
        \App\Option::set(Settings::SERVER_TRASH_FOLDER, '');
        Settings::setDeletePermanently(false);
    }

    protected function email(Conversation $c, $message_id, $type = Thread::TYPE_CUSTOMER)
    {
        $t = new Thread();
        $t->conversation_id = $c->id;
        $t->type = $type;
        $t->state = Thread::STATE_PUBLISHED;
        $t->status = Thread::STATUS_ACTIVE;
        $t->body = 'Body';
        $t->message_id = $message_id;
        $t->source_via = Thread::PERSON_CUSTOMER;
        $t->source_type = Thread::SOURCE_TYPE_EMAIL;
        $t->save();

        return $t;
    }

    protected function queued()
    {
        return \DB::table(MailServerTrash::TABLE)->orderBy('id')->get();
    }

    protected function imapMailbox(Mailbox $mailbox)
    {
        $mailbox->in_protocol = Mailbox::IN_PROTOCOL_IMAP;
        $mailbox->in_server = 'imap.example.test';
        $mailbox->save();

        return $mailbox;
    }

    /** Fake IMAP server: folders by path, messages by Message-ID; records the moves. */
    protected function fakeServer(array $folders, array $messages)
    {
        $server = new \stdClass();
        $server->moves = [];
        $server->folders = $folders;
        $server->messages = $messages; // folder path => [message ids]
        $client = new class($server) {
            public $s;
            public function __construct($s) { $this->s = $s; }
            public function connect() { return $this; }
            public function disconnect() { return $this; }
            public function getFolders($hierarchical = true) {
                $s = $this->s;
                return array_map(function ($path) use ($s) { return new FakeImapFolder($s, $path); }, $s->folders);
            }
            public function getFolderByPath($path) {
                return in_array($path, $this->s->folders, true) ? new FakeImapFolder($this->s, $path) : null;
            }
        };

        return [$server, function () use ($client) { return $client; }];
    }

    public function testDeleteForeverQueuesTheEmails()
    {
        $c = $this->s['t_support_open'];
        $this->email($c, '<customer-1-'.$this->s['prefix'].'@example.test>');
        $this->email($c, '<reply-1-'.$this->s['prefix'].'@example.test>', Thread::TYPE_MESSAGE);
        $this->email($c, null); // note / no Message-ID: nothing to move
        $r = $this->actingAs($this->s['admin'])->post(route('conversations.ajax'), ['action' => 'delete_conversation_forever', 'conversation_id' => $c->id]);
        $this->assertSame('success', $r->json()['status']);
        $rows = $this->queued();
        $this->assertCount(2, $rows);
        $this->assertSame('customer-1-'.$this->s['prefix'].'@example.test', $rows[0]->message_id); // without < >
        $this->assertSame('in', $rows[0]->folder);
        $this->assertSame('sent', $rows[1]->folder);
        $this->assertSame((int) $this->s['support']->id, (int) $rows[0]->mailbox_id);
        $this->assertSame('pending', $rows[0]->status);
    }

    public function testMoveToTrashIsNotADeletionForGood()
    {
        $c = $this->s['t_support_open'];
        $this->email($c, '<to-trash@example.test>');
        $this->actingAs($this->s['admin'])->post(route('conversations.ajax'), ['action' => 'delete_conversation', 'conversation_id' => $c->id]);
        $this->assertCount(0, $this->queued());
    }

    public function testEmptyTrashButtonQueues()
    {
        $this->email($this->s['t_billing_deleted'], '<in-trash@example.test>');
        Trash::emptyFor($this->s['admin']);
        $this->assertSame(['in-trash@example.test'], $this->queued()->pluck('message_id')->all());
    }

    public function testOtherPermanentDeletionsOfFreeScoutAreIgnored()
    {
        // e.g. a whole mailbox deleted in FreeScout, or an e-mail that could not be imported: nothing on the server
        $this->email($this->s['t_sales_open'], '<mailbox-deleted@example.test>');
        $this->s['t_sales_open']->deleteForever();
        $this->assertCount(0, $this->queued());
    }

    public function testSettingOff()
    {
        Settings::setServerTrash(false);
        $this->email($this->s['t_billing_deleted'], '<off@example.test>');
        Trash::emptyFor($this->s['admin']);
        $this->assertCount(0, $this->queued());
    }

    public function testProcessMovesToTheServerTrash()
    {
        $mailbox = $this->imapMailbox($this->s['support']);
        $mailbox->imap_sent_folder = 'Sent';
        $mailbox->save();
        $now = date('Y-m-d H:i:s');
        foreach ([['a@example.test', 'in'], ['gone@example.test', 'in'], ['r@example.test', 'sent']] as $row) {
            \DB::table(MailServerTrash::TABLE)->insert(['mailbox_id' => $mailbox->id, 'message_id' => $row[0], 'folder' => $row[1], 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now]);
        }
        list($server, $factory) = $this->fakeServer(['INBOX', 'Sent', 'INBOX.Trash'], ['INBOX' => ['a@example.test'], 'Sent' => ['r@example.test']]);
        $done = MailServerTrash::process(null, $factory);

        $this->assertSame(2, $done['moved']);
        $this->assertSame(1, $done['not_found']);
        $this->assertSame([['INBOX', 'a@example.test', 'INBOX.Trash'], ['Sent', 'r@example.test', 'INBOX.Trash']], $server->moves);
        $this->assertSame(['moved', 'not_found', 'moved'], $this->queued()->pluck('status')->all());
    }

    public function testTrashFolderSetByHandAndErrorsAreRetried()
    {
        $mailbox = $this->imapMailbox($this->s['support']);
        $now = date('Y-m-d H:i:s');
        \DB::table(MailServerTrash::TABLE)->insert(['mailbox_id' => $mailbox->id, 'message_id' => 'x@example.test', 'folder' => 'in', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now]);

        // no usual trash name on this server: error, tried again later
        list($server, $factory) = $this->fakeServer(['INBOX', 'Bin'], ['INBOX' => ['x@example.test']]);
        $done = MailServerTrash::process(null, $factory);
        $this->assertSame(1, $done['failed']);
        $row = $this->queued()->first();
        $this->assertSame('pending', $row->status);
        $this->assertSame(1, (int) $row->attempts);
        $this->assertStringContainsString('trash folder not found', $row->error);

        // folder given in the settings
        \App\Option::set(Settings::SERVER_TRASH_FOLDER, 'Bin');
        $done = MailServerTrash::process(null, $factory);
        $this->assertSame(1, $done['moved']);
        $this->assertSame([['INBOX', 'x@example.test', 'Bin']], $server->moves);
    }

    public function testPop3MailboxIsSkipped()
    {
        $mailbox = $this->s['sales'];
        $mailbox->in_protocol = Mailbox::IN_PROTOCOL_POP3;
        $mailbox->in_server = 'pop.example.test';
        $mailbox->save();
        $now = date('Y-m-d H:i:s');
        \DB::table(MailServerTrash::TABLE)->insert(['mailbox_id' => $mailbox->id, 'message_id' => 'p@example.test', 'folder' => 'in', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now]);
        $done = MailServerTrash::process(null, function () {
            throw new \RuntimeException('must not connect');
        });
        $this->assertSame(1, $done['skipped']);
        $this->assertSame('skipped', $this->queued()->first()->status);
    }

    public function testScheduledEveryMinuteAndSettingsShown()
    {
        $commands = array_map(function ($e) {
            return (string) $e->command;
        }, app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $this->assertNotEmpty(array_filter($commands, function ($c) {
            return strpos($c, 'refreshglobal:mail-trash') !== false;
        }));
        $page = $this->actingAs($this->s['admin'])->get(route('settings', ['section' => 'refreshglobal']));
        $this->seeIn($page, 'name="settings['.Settings::SERVER_TRASH.']"');
        $this->seeIn($page, 'name="settings['.Settings::SERVER_TRASH_FOLDER.']"');
        // nothing waiting: the command does not connect anywhere
        $this->assertSame(0, \Artisan::call('refreshglobal:mail-trash'));
    }
}

/** Folder of the fake IMAP server (same calls as the code: query()->whereMessageId()->leaveUnread()->get()). */
class FakeImapFolder
{
    public $path;
    public $name;
    protected $s;
    protected $wanted;

    public function __construct($s, $path)
    {
        $this->s = $s;
        $this->path = $path;
        $this->name = $path;
    }

    public function query()
    {
        return $this;
    }

    public function whereMessageId($id)
    {
        $this->wanted = $id;

        return $this;
    }

    public function leaveUnread()
    {
        return $this;
    }

    public function get()
    {
        $s = $this->s;
        $path = $this->path;
        $found = [];
        foreach ($s->messages[$path] ?? [] as $id) {
            if ($id === $this->wanted) {
                $found[] = new class($s, $path, $id) {
                    protected $s;
                    protected $path;
                    protected $id;
                    public function __construct($s, $path, $id) { $this->s = $s; $this->path = $path; $this->id = $id; }
                    public function move($to) {
                        $this->s->moves[] = [$this->path, $this->id, $to];
                        $this->s->messages[$this->path] = array_values(array_diff($this->s->messages[$this->path], [$this->id]));
                        return $this;
                    }
                };
            }
        }

        return $found;
    }
}
