<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Conversation;
use App\MailboxUser;
use Illuminate\Http\Request;
use Modules\RefreshGlobal\Http\Middleware\AfterDelete;
use Modules\RefreshGlobal\Http\Middleware\AfterReply;
use Modules\RefreshGlobal\Services\ReplyStart;
use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Tests\TestCase;

/** 1.24.0: text a reply starts with, and where a reply leads. */
class ReplyTest extends TestCase
{
    /* ---------------------------------------------------------------- text */

    public function testVariables()
    {
        $v = ['first_name' => 'Zoé', 'last_name' => '', 'ticket_number' => '42', 'company' => "A\nB"];
        $this->assertSame('Bonjour Zoé,', ReplyStart::fill('Bonjour $first_name,', $v));
        $this->assertSame('Bonjour,', ReplyStart::fill('Bonjour $last_name,', $v), 'no value: the space goes too');
        $this->assertSame('Bonjour Madame, Monsieur,', ReplyStart::fill('Bonjour $last_name|Madame, Monsieur|,', $v));
        $this->assertSame('Bonjour Zoé,', ReplyStart::fill('Bonjour $first_name|client|,', $v));
        $this->assertSame('Zoé #42 A B', ReplyStart::fill('$first_name #$ticket_number $company', $v), 'a value never adds a line');
        $this->assertSame('$nom $first_names 5$', ReplyStart::fill('$nom $first_names 5$', $v), 'unknown: left as it is');
        // a value is never read as a variable
        $this->assertSame('$email', ReplyStart::fill('$first_name', ['first_name' => '$email', 'email' => 'x@example.test']));
        foreach (ReplyStart::VARIABLES as $name) {
            $this->assertArrayHasKey($name, ReplyStart::SAMPLE);
        }
    }

    public function testHtmlIsEscaped()
    {
        $this->assertSame('', ReplyStart::html("  \n "));
        $this->assertSame('<div>Hello &lt;b&gt;</div><div><br></div><div>x</div><div><br></div>', ReplyStart::html("Hello <b>\n\nx"));
        $c = $this->s['t_support_open'];
        $customer = $c->customer;
        $customer->first_name = '<script>alert(1)</script>';
        $customer->save();
        \App\Option::set(ReplyStart::ENABLED, 1);
        \App\Option::set(ReplyStart::TEXT, 'Hello $first_name,');
        Settings::setFlag(ReplyStart::DETECT, false);
        $html = ReplyStart::forTicket($c->fresh(), $this->s['bob'])[0]['html'];
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('&lt;script', $html);
    }

    protected function htmls($c)
    {
        return array_column(ReplyStart::forTicket($c, $this->s['bob']), 'html');
    }

    /** Settings saved by 1.24.0 keep the same result until the list is saved. */
    public function testSettingsOf124()
    {
        $c = $this->s['t_support_open'];
        $sales = $this->s['t_sales_open'];
        \App\Option::set(ReplyStart::ENABLED, 1);
        \App\Option::set(ReplyStart::TEXT, 'Common');
        \App\Option::set(ReplyStart::MAILBOXES, [$this->s['sales']->id => 'Sales']);
        $this->assertSame(['<div>Common</div><div><br></div>'], $this->htmls($c));
        $this->assertSame('<div>Sales</div><div><br></div>', $this->htmls($sales)[0]);
        \App\Option::set(ReplyStart::MAILBOXES, [$this->s['sales']->id => '-']);
        $this->assertSame([], $this->htmls($sales), 'dash: nothing for this mailbox');
        $this->assertCount(1, $this->htmls($c));
        // saved empty: the former settings do not come back
        $this->actingAs($this->s['admin'])->post(route('settings.save', ['section' => 'refreshglobal']), ['settings' => [ReplyStart::ENABLED => '1', ReplyStart::TEXTS => '']])->assertStatus(302);
        $this->assertSame([], $this->htmls($c));
    }

    public function testTextsPerMailboxAndWhere()
    {
        $c = $this->s['t_support_open'];
        $sales = $this->s['t_sales_open'];
        $this->assertSame([], $this->htmls($c), 'off by default');
        $this->actingAs($this->s['admin'])->post(route('settings.save', ['section' => 'refreshglobal']), ['settings' => [
            ReplyStart::ENABLED => '1', ReplyStart::TEXTS => [
                'n2' => ['name' => 'General', 'text' => "Bonjour,\r\nticket \$ticket_number", 'where' => 'all'],
                0    => ['name' => ' Sales  team ', 'text' => 'Sales: $mailbox / $agent_first_name', 'where' => 'only', 'mailboxes' => [(string) $this->s['sales']->id]],
                'n1' => ['name' => '', 'text' => 'Not sales', 'where' => 'except', 'mailboxes' => [(string) $this->s['sales']->id]],
                'n3' => ['name' => 'Nowhere', 'text' => 'x', 'where' => 'only'],
                'n4' => ['name' => 'Wrong', 'text' => 'x', 'where' => 'bad'],
                'n5' => ['name' => 'Empty', 'text' => ' ', 'where' => 'all'],
                'n6' => 'not an array',
            ],
        ]])->assertStatus(302);
        $this->assertSame(['General', 'Sales team', '', 'Nowhere', 'Wrong'], array_column(ReplyStart::texts(), 'name'), 'order of the form');
        $this->assertSame(['<div>Bonjour,</div><div>ticket '.$c->number.'</div><div><br></div>', '<div>Not sales</div><div><br></div>'], $this->htmls($c));
        $this->assertSame(['General', 'Not sales'], array_column(ReplyStart::forTicket($c, $this->s['bob']), 'name'), 'no name: the text');
        // those of the mailbox before the general ones
        $this->assertSame(['Sales team', 'General'], array_column(ReplyStart::forTicket($sales, $this->s['bob']), 'name'));
        $this->assertStringContainsString('Sales: '.$this->s['sales']->name.' / '.$this->s['bob']->first_name, html_entity_decode($this->htmls($sales)[0], ENT_QUOTES));
        $chat = $c->fresh();
        $chat->type = Conversation::TYPE_CHAT;
        $this->assertSame([], $this->htmls($chat), 'not in chats');

        // on the ticket page only
        $page = $this->actingAs($this->s['bob'])->get($c->url())->getContent();
        $this->assertStringContainsString('name="refreshglobal-reply"', $page);
        $this->assertStringNotContainsString('name="refreshglobal-reply"', $this->ticketsPage($this->s['bob'])->getContent());
        \App\Option::set(ReplyStart::ENABLED, 0);
        $this->assertStringNotContainsString('name="refreshglobal-reply"', $this->actingAs($this->s['bob'])->get($c->url())->getContent());
    }

    public function testSettingsShowTheGuide()
    {
        $r = $this->actingAs($this->s['admin'])->get(route('settings', ['section' => 'refreshglobal']));
        $r->assertStatus(200);
        foreach (ReplyStart::VARIABLES as $name) {
            $this->seeIn($r, 'data-rg-var="$'.$name.'"');
        }
        $this->seeIn($r, 'name="settings['.ReplyStart::TEXTS.'][__i__][mailboxes][]" value="'.$this->s['support']->id.'"');
        $this->seeIn($r, 'data-rg-reply-add');
        $this->seeIn($r, 'data-rg-mailbox="'.$this->s['support']->id.'"');
        $this->dontSeeIn($r, 'refreshglobal::reply.');
        // the variables are never translated
        foreach (glob(__DIR__.'/../../Resources/lang/*/reply.php') as $file) {
            $texts = require $file;
            $this->assertStringContainsString('$first_name', $texts['placeholder'], $file);
            $this->assertStringContainsString('$first_name|', $texts['fallback_example'], $file);
        }
    }

    /* ---------------------------------------------------------------- after a reply */

    /** Runs both roles of the middleware around an answer of FreeScout that leads to $url. */
    protected function reply($user, Conversation $c, $url, $after = MailboxUser::AFTER_SEND_NEXT, array $more = [], $closes = false)
    {
        $this->actingAs($user);
        $request = Request::create(route('conversations.ajax'), 'POST', $more + ['action' => 'send_reply', 'conversation_id' => $c->id, 'after_send' => $after]);
        $request->setRouteResolver(function () use ($request) {
            return \Route::getRoutes()->match($request);
        });
        $m = new AfterReply();
        $response = $m->handle($request, function ($request) use ($m, $url, $c, $closes) {
            return $m->handle($request, function () use ($url, $c, $closes) {
                if ($closes) {
                    Conversation::where('id', $c->id)->update(['status' => Conversation::STATUS_CLOSED]);
                }

                return response()->json(['status' => 'success', 'redirect_url' => $url]);
            });
        }, 'outer');

        return $response->getData(true)['redirect_url'];
    }

    public function testStaysOnTheTicketWithoutNextTicket()
    {
        $c = $this->s['t_support_open'];
        $next = route('conversations.view', ['id' => $this->s['t_support_pending']->id]);
        $list = url('/mailbox/'.$c->mailbox_id.'/12');
        $bob = $this->s['bob'];
        $this->assertSame($c->url(), $this->reply($bob, $c, $list));
        $this->assertSame($c->url(), $this->reply($bob, $c, url('/mailbox/'.$c->mailbox_id.'/tickets/unresolved')), 'a view of Refresh too');
        $this->assertSame($next, $this->reply($bob, $c, $next), 'a next ticket is kept');
        // choices of the agent
        $this->assertSame($list, $this->reply($bob, $c, $list, MailboxUser::AFTER_SEND_FOLDER));
        $this->assertSame($c->url(), $this->reply($bob, $c, $c->url(), MailboxUser::AFTER_SEND_STAY));
        // other actions, new conversation, closed by the reply
        $this->assertSame($list, $this->reply($bob, $c, $list, MailboxUser::AFTER_SEND_NEXT, ['action' => 'conversation_change_status']));
        $this->assertSame($list, $this->reply($bob, $c, $list, MailboxUser::AFTER_SEND_NEXT, ['is_create' => 1]));
        $this->assertSame($list, $this->reply($bob, $c, $list, MailboxUser::AFTER_SEND_NEXT, [], true));
    }

    public function testNextTicketOfTheList()
    {
        Settings::setFlag(Settings::REPLY_GOES_NEXT, true);
        $bob = $this->s['bob'];
        $c = $this->s['t_support_open'];
        $pending = $this->s['t_support_pending'];
        $list = url('/mailbox/'.$c->mailbox_id.'/12');
        $this->assertSame($c->url(), $this->reply($bob, $c, $list), 'no list shown: as without the setting');
        session([AfterDelete::SESSION_KEY => ['params' => [], 'filters' => ['mailboxes' => [$this->s['support']->id], 'status' => [1, 2], 'sort' => 'created', 'order' => 'asc']]]);
        $this->assertSame(route('conversations.view', ['id' => $pending->id]), $this->reply($bob, $c, $list));
        // closed meanwhile: nothing open left, stays on the ticket
        Conversation::where('id', $pending->id)->update(['status' => Conversation::STATUS_CLOSED]);
        $this->assertSame($c->url(), $this->reply($bob, $c, $list));
        // never a ticket of a mailbox the agent does not see
        session([AfterDelete::SESSION_KEY => ['params' => [], 'filters' => ['mailboxes' => [$this->s['billing']->id], 'status' => [1, 2]]]]);
        $this->assertStringNotContainsString('/conversation/'.$this->s['t_billing_other']->id, $this->reply($bob, $c, $list));
    }

    public function testRealReplyThroughFreeScout()
    {
        $c = $this->s['t_support_open'];
        \Illuminate\Support\Facades\Queue::fake();
        $r = $this->actingAs($this->s['bob'])->post(route('conversations.ajax'), ['action' => 'send_reply', 'conversation_id' => $c->id, 'mailbox_id' => $c->mailbox_id,
            'is_note' => 1, 'body' => '<div>note</div>', 'status' => Conversation::STATUS_ACTIVE, 'user_id' => -1, 'after_send' => MailboxUser::AFTER_SEND_NEXT]);
        $r->assertStatus(200);
        $this->assertSame('success', $r->json()['status'], (string) ($r->json()['msg'] ?? ''));
        $this->assertRegExpCompat('#/conversation/\d+#', $r->json()['redirect_url']);
    }

    protected function assertRegExpCompat($pattern, $text)
    {
        $this->assertSame(1, preg_match($pattern, (string) $text), (string) $text);
    }
}
