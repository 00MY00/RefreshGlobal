<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Illuminate\Http\Request;
use Modules\RefreshGlobal\Services\SendFrom;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * Address to send from on "Send an e-mail" / "New ticket" (Services/SendFrom.php, Http/Middleware/CheckFromAlias.php,
 * Public/js/shell.js sendFrom()): the addresses of the user's mailboxes and their aliases, and FreeScout's
 * "from_alias" checked against the ticket's mailbox.
 */
class SendFromTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $support = $this->s['support'];
        $support->aliases = 'help-'.$this->s['prefix'].'@example.test (Help desk), info-'.$this->s['prefix'].'@example.test';
        $support->aliases_reply = false; // FreeScout itself shows no "From" field
        $support->save();
    }

    protected function emails($user)
    {
        $p = $this->s['prefix'];
        $out = [];
        foreach (SendFrom::addresses($user) as $a) {
            if (strpos($a['name'], $p) === 0) {
                $out[] = [$a['name'], $a['email'], $a['alias'], $a['label']];
            }
        }

        return $out;
    }

    public function testAddressesOfTheUsersMailboxesAndTheirAliases()
    {
        $s = $this->s;
        $p = $s['prefix'];
        // Bob: support + sales only; support's address first, then its aliases (with their names)
        $this->assertSame([
            [$s['sales']->name, $s['sales']->email, false, $s['sales']->name],
            [$s['support']->name, $s['support']->email, false, $s['support']->name],
            [$s['support']->name, strtolower('help-'.$p.'@example.test'), true, 'Help desk'],
            [$s['support']->name, strtolower('info-'.$p.'@example.test'), true, ''],
        ], $this->emails($s['bob']));
        // the administrator: every mailbox but the archived one
        $names = array_unique(array_column($this->emails($s['admin']), 0));
        $this->assertContains($s['billing']->name, $names);
        $this->assertNotContains($s['archived']->name, $names);
        $this->assertSame([], SendFrom::addresses(null));
    }

    public function testFromAliasIsCheckedAgainstTheTicketsMailbox()
    {
        $s = $this->s;
        $p = $s['prefix'];
        $conv = $s['t_support_open'];
        // an alias of the ticket's mailbox (or its own address): kept
        foreach (['help-'.$p.'@example.test', 'INFO-'.$p.'@example.test', $s['support']->email] as $alias) {
            $r = Request::create('/', 'POST', ['conversation_id' => $conv->id, 'from_alias' => $alias]);
            $this->assertFalse(SendFrom::sanitize($r));
            $this->assertSame($alias, $r->input('from_alias'));
        }
        // the address of another mailbox, or any other address: removed
        foreach ([$s['sales']->email, 'ceo@bank.example'] as $alias) {
            $r = Request::create('/', 'POST', ['conversation_id' => $conv->id, 'from_alias' => $alias]);
            $this->assertTrue(SendFrom::sanitize($r));
            $this->assertSame('', $r->input('from_alias'));
        }
        // new e-mail: the mailbox of the form
        $r = Request::create('/', 'POST', ['mailbox_id' => $s['support']->id, 'from_alias' => 'help-'.$p.'@example.test']);
        $this->assertFalse(SendFrom::sanitize($r));
        $r = Request::create('/', 'POST', ['mailbox_id' => $s['sales']->id, 'from_alias' => 'help-'.$p.'@example.test']);
        $this->assertTrue(SendFrom::sanitize($r));
        // nothing posted: nothing to do
        $this->assertFalse(SendFrom::sanitize(Request::create('/', 'POST', ['mailbox_id' => $s['sales']->id])));
    }

    public function testMiddlewareIsInTheWebGroup()
    {
        $this->assertContains(\Modules\RefreshGlobal\Http\Middleware\CheckFromAlias::class, $this->app['router']->getMiddlewareGroups()['web']);
        // through FreeScout's conversations.ajax: the forged alias is removed before FreeScout reads it
        $seen = null;
        $mw = new \Modules\RefreshGlobal\Http\Middleware\CheckFromAlias();
        $request = Request::create(route('conversations.ajax'), 'POST', ['action' => 'send_reply', 'conversation_id' => $this->s['t_support_open']->id, 'from_alias' => 'ceo@bank.example']);
        $request->setRouteResolver(function () use ($request) {
            return $this->app['router']->getRoutes()->match($request);
        });
        $mw->handle($request, function ($r) use (&$seen) {
            $seen = $r->input('from_alias');

            return response('');
        });
        $this->assertSame('', $seen);
    }

    public function testCreatePageData()
    {
        $s = $this->s;
        $c = SendFrom::pageConfig($s['bob'], $s['sales']->id);
        $this->assertSame((int) $s['sales']->id, $c['mailbox']);
        $this->assertSame(route('conversations.create', ['mailbox_id' => $s['support']->id]), $c['urls'][$s['support']->id]);
        $this->assertArrayNotHasKey($s['billing']->id, $c['urls']); // not one of Bob's mailboxes
        $this->assertContains(strtolower('help-'.$s['prefix'].'@example.test'), array_column($c['addresses'], 'email'));
        $this->assertSame(__('refreshglobal::messages.send_from'), $c['labels']['from']);
        // a user with a single address: nothing to choose
        $this->assertNull(SendFrom::pageConfig($s['carol'], $s['billing']->id));
    }
}
