<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Mailbox;
use App\Thread;
use Modules\RefreshGlobal\Services\MailMove;
use Modules\RefreshGlobal\Services\MailTags;
use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * "Move to a folder" of the mail server (Services/MailMove.php, Http/Controllers/MailMoveController.php) against an
 * in-memory IMAP server: folders listed, e-mails found by Message-ID and moved, tags updated, undo, rights, mailbox not
 * IMAP, unknown folder; the ticket itself unchanged.
 */
class MailMoveTest extends TestCase
{
    /** @var \stdClass in-memory server: folder path => [message ids] */
    protected $server;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::setFlag(Settings::MAIL_MOVE, true);
        Settings::setFlag(Settings::MAIL_TAGS, true);
        \App\Option::set(Settings::MAIL_TAGS_FOLDERS, '');
        \Cache::flush();
        $support = $this->s['support'];
        $support->in_protocol = Mailbox::IN_PROTOCOL_IMAP;
        $support->in_server = 'imap.example.test';
        $support->save();
        $this->server = (object) ['folders' => [
            'INBOX'                  => ['m1@example.test', 'm2@example.test'],
            'INBOX.Factures'         => [],
            'INBOX.[WP] Achats.Livraisons' => [],
            'Junk'                   => [],
        ], 'moves' => [], 'created' => []];
        $server = $this->server;
        MailMove::$clientFactory = function () use ($server) {
            return self::fakeClient($server);
        };
    }

    protected function tearDown(): void
    {
        MailMove::$clientFactory = null;
        parent::tearDown();
    }

    /** Client with the parts of webklex used: connect, disconnect, getFolders(false), getFolderByPath(), query()->whereMessageId()->leaveUnread()->get(), move(). */
    protected static function fakeClient($server)
    {
        return new class($server) {
            public $s;
            public function __construct($s) { $this->s = $s; }
            public function connect() { return $this; }
            public function disconnect() { return $this; }
            public function folder($path)
            {
                $s = $this->s;

                return new class($s, $path) {
                    public $s; public $path; public $delimiter = '.'; public $no_select = false; protected $id;
                    public function __construct($s, $path) { $this->s = $s; $this->path = $path; }
                    public function query() { return $this; }
                    public function whereMessageId($id) { $this->id = trim($id, '<>'); return $this; }
                    public function leaveUnread() { return $this; }
                    public function get()
                    {
                        $s = $this->s; $path = $this->path; $out = [];
                        if (in_array($this->id, $s->folders[$path] ?? [], true)) {
                            $id = $this->id;
                            $out[] = new class($s, $path, $id) {
                                public $s; public $path; public $id;
                                public function __construct($s, $p, $id) { $this->s = $s; $this->path = $p; $this->id = $id; }
                                public function move($to)
                                {
                                    $this->s->folders[$this->path] = array_values(array_diff($this->s->folders[$this->path], [$this->id]));
                                    $this->s->folders[$to][] = $this->id;
                                    $this->s->moves[] = [$this->id, $this->path, $to];

                                    return true;
                                }
                            };
                        }

                        return $out;
                    }
                };
            }
            public function getFolders($h = true)
            {
                $out = [];
                foreach (array_keys($this->s->folders) as $p) {
                    $out[] = $this->folder($p);
                }

                return $out;
            }
            public function getFolderByPath($path) { return array_key_exists($path, $this->s->folders) ? $this->folder($path) : null; }
            public function createFolder($path, $expunge = true)
            {
                $this->s->folders[$path] = [];
                $this->s->created[] = $path;

                return $this->folder($path);
            }
        };
    }

    protected function thread($conversation, $messageId)
    {
        $t = new Thread();
        $t->conversation_id = $conversation->id;
        $t->type = Thread::TYPE_CUSTOMER;
        $t->state = Thread::STATE_PUBLISHED;
        $t->status = Thread::STATUS_ACTIVE;
        $t->body = '<p>Hello</p>';
        $t->message_id = $messageId;
        $t->source_via = Thread::PERSON_CUSTOMER;
        $t->source_type = Thread::SOURCE_TYPE_EMAIL;
        $t->save();
        MailTags::attach($conversation, $t, ['inbox'], 'INBOX');

        return $t;
    }

    public function testFoldersOfTheMailbox()
    {
        $c = $this->s['t_support_open'];
        $this->thread($c, 'm1@example.test');
        $r = $this->actingAs($this->s['bob'])->get(route('refreshglobal.move.folders', ['conversation_id' => $c->id]));
        $r->assertStatus(200);
        $data = $r->json();
        $this->assertSame('success', $data['status']);
        $paths = array_column($data['folders'], 'path');
        $this->assertSame('INBOX', $paths[0]);
        $this->assertContains('INBOX.[WP] Achats.Livraisons', $paths);
        $this->assertContains('[WP] Achats › Livraisons', array_column($data['folders'], 'label'));
        $this->assertSame(['INBOX'], $data['current']);
    }

    public function testMoveAndUndo()
    {
        $c = $this->s['t_support_open'];
        $before = [$c->status, $c->mailbox_id, $c->user_id, $c->state];
        $t1 = $this->thread($c, 'm1@example.test');
        $t2 = $this->thread($c, 'm2@example.test');
        $moved = [];
        \Eventy::addAction('refreshglobal.mail_moved', function ($cid, $tid, $from, $to) use (&$moved) {
            $moved[] = [$cid, $tid, $from, $to];
        }, 20, 4);

        $r = $this->actingAs($this->s['bob'])->post(route('refreshglobal.move'), ['conversation_id' => $c->id, 'folder' => 'INBOX.Factures']);
        $r->assertStatus(200);
        $data = $r->json();
        $this->assertSame('success', $data['status']);
        $this->assertSame(2, $data['moved']);
        $this->assertEqualsCanonicalizing(['m1@example.test', 'm2@example.test'], $this->server->folders['INBOX.Factures']);
        $this->assertSame([], $this->server->folders['INBOX']);
        // tags follow the folder; the ticket itself is unchanged
        $tags = MailTags::forThreads([$t1->id, $t2->id]);
        $this->assertSame(['folder:factures'], $tags[$t1->id]['tags']);
        $this->assertSame('INBOX.Factures', $tags[$t1->id]['folder']);
        $c = $c->fresh();
        $this->assertSame($before, [$c->status, $c->mailbox_id, $c->user_id, $c->state]);
        $this->assertCount(2, $moved);
        $this->assertSame(['INBOX', 'INBOX.Factures'], [$moved[0][2], $moved[0][3]]);

        // undo: back to INBOX
        $r = $this->actingAs($this->s['bob'])->post(route('refreshglobal.move.undo'), ['tickets' => json_encode($data['tickets'])]);
        $this->assertSame(2, $r->json()['moved']);
        $this->assertEqualsCanonicalizing(['m1@example.test', 'm2@example.test'], $this->server->folders['INBOX']);
        $this->assertSame(['inbox'], MailTags::forThreads([$t1->id])[$t1->id]['tags']);

        // the same folder again: nothing to move
        $this->actingAs($this->s['bob'])->post(route('refreshglobal.move'), ['conversation_id' => $c->id, 'folder' => 'INBOX']);
        $this->assertCount(4, $this->server->moves);
    }

    public function testUndoOnlyToFoldersOfTheMailbox()
    {
        $c = $this->s['t_support_open'];
        $t = $this->thread($c, 'm1@example.test');
        // a forged undo to a folder that does not exist / for a thread of another ticket does nothing
        $other = $this->thread($this->s['t_support_pending'], 'm2@example.test');
        $r = $this->actingAs($this->s['bob'])->post(route('refreshglobal.move.undo'), ['tickets' => json_encode([
            ['conversation_id' => $c->id, 'previous' => [$t->id => 'Nowhere', $other->id => 'Junk']],
        ])]);
        $this->assertSame(0, $r->json()['moved']);
        $this->assertSame([], $this->server->moves);
    }

    public function testRightsAndErrors()
    {
        $c = $this->s['t_support_open'];
        $this->thread($c, 'm1@example.test');
        // carol does not see the support mailbox
        $this->actingAs($this->s['carol'])->post(route('refreshglobal.move'), ['conversation_id' => $c->id, 'folder' => 'Junk'])->assertStatus(200);
        $this->assertSame('error', $this->actingAs($this->s['carol'])->post(route('refreshglobal.move'), ['conversation_id' => $c->id, 'folder' => 'Junk'])->json()['status']);
        $this->actingAs($this->s['carol'])->get(route('refreshglobal.move.folders', ['conversation_id' => $c->id]))->assertStatus(403);
        // unknown folder
        $r = $this->actingAs($this->s['bob'])->post(route('refreshglobal.move'), ['conversation_id' => $c->id, 'folder' => 'Nowhere']);
        $this->assertSame('error', $r->json()['status']);
        // mailbox that is not IMAP
        $this->s['support']->in_protocol = 2;
        $this->s['support']->save();
        $this->assertSame('error', $this->actingAs($this->s['bob'])->post(route('refreshglobal.move'), ['conversation_id' => $c->id, 'folder' => 'Junk'])->json()['status']);
        // feature off
        Settings::setFlag(Settings::MAIL_MOVE, false);
        $this->actingAs($this->s['bob'])->get(route('refreshglobal.move.folders', ['conversation_id' => $c->id]))->assertStatus(403);
        $this->assertSame([], $this->server->moves);
    }

    public function testBulkMoveAndListButton()
    {
        $c1 = $this->s['t_support_open'];
        $c2 = $this->s['t_support_pending'];
        $this->thread($c1, 'm1@example.test');
        $this->thread($c2, 'm2@example.test');
        $r = $this->actingAs($this->s['bob'])->post(route('refreshglobal.move'), ['conversation_ids' => [$c1->id, $c2->id], 'folder' => 'Junk']);
        $this->assertSame(2, $r->json()['moved']);
        $this->assertCount(2, $r->json()['tickets']);
        $this->assertEqualsCanonicalizing(['m1@example.test', 'm2@example.test'], $this->server->folders['Junk']);
        // the selection bar of the lists has the button; the page has the addresses for the script
        $page = $this->ticketsPage($this->s['bob'])->getContent();
        $this->assertStringContainsString('rg-move-bulk', $page);
        $this->assertStringContainsString('name="refreshglobal-move"', $page);
        Settings::setFlag(Settings::MAIL_MOVE, false);
        $this->assertStringNotContainsString('rg-move-bulk', $this->ticketsPage($this->s['bob'])->getContent());
    }

    /**
     * 1.21.0: "Refresh the folders" reads them again from the mail server (fresh=1), the cached list otherwise.
     */
    public function testRefreshTheFolders()
    {
        $c = $this->s['t_support_open'];
        $paths = function ($fresh) use ($c) {
            return array_column($this->actingAs($this->s['bob'])->get(route('refreshglobal.move.folders', ['conversation_id' => $c->id, 'fresh' => $fresh]))->json()['folders'], 'path');
        };
        $this->assertNotContains('INBOX.Archive', $paths(0));
        $this->server->folders['INBOX.Archive'] = []; // created on the server by another program
        $this->assertNotContains('INBOX.Archive', $paths(0), 'kept 10 minutes');
        $this->assertContains('INBOX.Archive', $paths(1), 'read again');
        $this->assertContains('INBOX.Archive', $paths(0), 'cache renewed');
    }

    /**
     * 1.21.0: new folder of the mail server from the "Move" dialog: under a parent, at the top level (under INBOX on an
     * "INBOX." server), UTF-8 name sent in UTF7-IMAP; invalid names, duplicates, rights and IMAP checked by the server.
     */
    public function testCreateFolder()
    {
        $c = $this->s['t_support_open'];
        $this->thread($c, 'm1@example.test');
        $create = function ($user, array $data) use ($c) {
            return $this->actingAs($user)->post(route('refreshglobal.move.create'), $data + ['conversation_id' => $c->id]);
        };

        $r = $create($this->s['bob'], ['name' => 'Réglé', 'parent' => 'INBOX.Factures']);
        $r->assertStatus(200);
        $data = $r->json();
        $this->assertSame('success', $data['status']);
        $this->assertSame('INBOX.Factures.Réglé', $data['folder']['path']);
        $this->assertSame(['INBOX.Factures.R&AOk-gl&AOk-'], $this->server->created, 'UTF7-IMAP like FreeScout');
        $this->assertContains('INBOX.Factures.Réglé', array_column($data['folders'], 'path'), 'folders read again');
        // then the move into it works
        $r = $this->actingAs($this->s['bob'])->post(route('refreshglobal.move'), ['conversation_id' => $c->id, 'folder' => 'INBOX.Factures.Réglé']);
        $this->assertSame(1, $r->json()['moved']);

        // top level: here "Junk" is outside INBOX, so the folder goes at the top
        $this->assertSame('Projets', $create($this->s['bob'], ['name' => 'Projets'])->json()['folder']['path']);
        // an "INBOX." server: every folder under INBOX, the new top-level one too
        $this->assertSame('INBOX', MailMove::topParent(['INBOX', 'INBOX.Sent', 'INBOX.Trash'], '.'));
        $this->assertSame('', MailMove::topParent(['INBOX', 'INBOX.Sent', 'Junk'], '.'));
        $this->assertSame('', MailMove::topParent(['INBOX'], '/'));

        // refused: already there, delimiter, wildcards, empty, unknown parent
        $this->assertSame('error', $create($this->s['bob'], ['name' => 'Factures', 'parent' => 'INBOX'])->json()['status']);
        $this->assertSame('error', $create($this->s['bob'], ['name' => 'a.b'])->json()['status']);
        $this->assertSame('error', $create($this->s['bob'], ['name' => 'a*'])->json()['status']);
        $this->assertSame('error', $create($this->s['bob'], ['name' => '..'])->json()['status']);
        $this->assertSame('error', $create($this->s['bob'], ['name' => 'X', 'parent' => 'Nowhere'])->json()['status']);
        $create($this->s['bob'], ['name' => ''])->assertStatus(302); // validation
        // rights: carol does not see the mailbox; feature off; not IMAP
        $create($this->s['carol'], ['name' => 'Carol'])->assertStatus(403);
        Settings::setFlag(Settings::MAIL_MOVE, false);
        $create($this->s['bob'], ['name' => 'Off'])->assertStatus(403);
        Settings::setFlag(Settings::MAIL_MOVE, true);
        $this->s['support']->in_protocol = 2;
        $this->s['support']->save();
        $create($this->s['bob'], ['name' => 'Pop'])->assertStatus(403);
        $this->assertSame(['INBOX.Factures.R&AOk-gl&AOk-', 'Projets'], $this->server->created);

        // the script gets the address and the texts
        $this->s['support']->in_protocol = Mailbox::IN_PROTOCOL_IMAP;
        $this->s['support']->save();
        $page = $this->ticketsPage($this->s['bob'])->getContent();
        $this->assertStringContainsString('"create":"'.route('refreshglobal.move.create').'"', html_entity_decode($page));
    }
}
