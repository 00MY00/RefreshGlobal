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
        // 1.31.0: line break, kept at the end
        $this->assertSame('<div>Hi Zoé,</div><div><br></div><div><br></div>', ReplyStart::html(ReplyStart::fill('Hi $first_name,$br', $v)));
        $this->assertSame("A
B", ReplyStart::fill('A $br|x|B', $v));
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
        $this->assertSame([], $this->htmls($c), 'no profile');
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

    /** 1.30.0: profiles are used unless the switch was turned off since; administrators see why nothing is put in. */
    public function testOnByDefaultAndReason()
    {
        $c = $this->s['t_support_open'];
        $page = function ($who) use ($c) {
            return $this->actingAs($this->s[$who])->get($c->url())->getContent();
        };
        $this->assertStringNotContainsString('refreshglobal-reply', $page('admin'), 'no profile: nothing');
        $row = ['name' => 'P', 'text' => 'Hello', 'where' => 'all', 'mailboxes' => [], 'lang' => ''];
        \App\Option::set('refreshglobal.reply_start', 0);
        \App\Option::set(ReplyStart::TEXTS, [$row]);
        \App\Option::remove(ReplyStart::ENABLED);
        $this->assertCount(1, $this->htmls($c), 'the former switch, off, is not read any more');
        $this->assertSame('', ReplyStart::why($c));
        $this->assertStringNotContainsString('refreshglobal-reply-why', $page('admin'));
        \App\Option::set(ReplyStart::TEXTS, [['where' => 'only', 'mailboxes' => [$this->s['sales']->id]] + $row]);
        $this->assertSame('mailbox', ReplyStart::why($c));
        $this->assertStringContainsString('refreshglobal-reply-why', $page('admin'));
        $this->assertStringNotContainsString('refreshglobal-reply-why', $page('bob'), 'administrators only');
        \App\Option::set(ReplyStart::ENABLED, 0);
        $this->assertSame('off', ReplyStart::why($c));
        // a broken character never loses the whole
        $this->assertNotEmpty(json_decode(ReplyStart::json(['texts' => [['html' => "a\xC3"]]]), true));
    }

    /** 1.30.0: "Support powered by FreeScout" of the e-mails to customers */
    public function testBranding()
    {
        \App\Option::set('email_branding', true);
        \App\Option::remove(Settings::REPLY_BRANDING);
        $this->assertFalse(Settings::replyBranding(), 'off by default');
        $c = $this->s['t_support_open'];
        $thread = new \App\Thread();
        $thread->forceFill(['conversation_id' => $c->id, 'type' => \App\Thread::TYPE_CUSTOMER, 'status' => 1, 'state' => 2, 'source_via' => 1, 'source_type' => 1, 'customer_id' => $c->customer_id,
            'created_by_customer_id' => $c->customer_id, 'body' => 'Hello']);
        $thread->save();
        $threads = collect([$thread->fresh()]);
        $data = ['conversation' => $c, 'mailbox' => $c->mailbox, 'threads' => $threads, 'threads_count' => count($threads), 'mailbox_change_history' => [],
            'headers' => ['Message-ID' => 'x@example.test'], 'auto_reply_message' => 'Hello', 'subject' => 'S'];
        foreach (['reply_fancy', 'reply_fancy_text', 'auto_reply', 'auto_reply_text'] as $name) {
            foreach (['emails/customer/', 'emails.customer.'] as $dir) {
                Settings::setFlag(Settings::REPLY_BRANDING, false);
                $this->assertStringNotContainsString('powered by', view($dir.$name, $data)->render(), $name);
                Settings::setFlag(Settings::REPLY_BRANDING, true);
                $this->assertStringContainsString('powered by', view($dir.$name, $data)->render(), $name.': FreeScout decides');
            }
        }
        $this->assertTrue((bool) \App\Option::where('name', 'email_branding')->value('value'), 'never changed');
        $this->seeIn($this->actingAs($this->s['admin'])->get(route('settings', ['section' => 'refreshglobal'])), 'settings['.Settings::REPLY_BRANDING.']');
    }

    /** 1.29.0: previous messages sent with a reply */
    public function testPreviousMessages()
    {
        \App\Option::remove(Settings::REPLY_HISTORY);
        $this->assertSame('full', Settings::replyHistory());
        \App\Option::set(Settings::REPLY_HISTORY, 'bad');
        $this->assertSame('global', Settings::replyHistory());
        $this->actingAs($this->s['admin'])->post(route('settings.save', ['section' => 'refreshglobal']), ['settings' => [Settings::REPLY_HISTORY => 'last']])->assertStatus(302);
        $this->assertSame('last', Settings::replyHistory());
        $html = $this->actingAs($this->s['bob'])->get($this->s['t_support_open']->url())->getContent();
        $this->assertStringContainsString('name="refreshglobal-history" content="{&quot;value&quot;:&quot;last&quot;', $html);
        $this->assertStringContainsString('name="conv_history"', $html);
        $this->seeIn($this->actingAs($this->s['admin'])->get(route('settings', ['section' => 'refreshglobal'])), 'id="rg-reply-history"');
    }

    public function testProfilePerLanguage()
    {
        $row = function ($name, $lang, $where = 'all', $ids = []) {
            return ['name' => $name, 'text' => $name, 'where' => $where, 'mailboxes' => $ids, 'lang' => $lang];
        };
        $c = $this->s['t_support_open'];
        $own = [$this->s['support']->id];
        \App\Option::set(ReplyStart::ENABLED, 1);
        \App\Option::set(ReplyStart::TEXTS, [$row('FR', 'fr'), $row('DE', 'de'), $row('Any', ''), $row('FR own', 'fr', 'only', $own), $row('Bad', 'xx')]);
        $this->assertSame(['fr', 'de', '', 'fr', ''], array_column(ReplyStart::texts(), 'lang'), 'unknown code: any language');
        $names = function ($lang) use ($c) {
            return implode(',', array_column(ReplyStart::rank(ReplyStart::textsFor($c->mailbox_id), $lang), 'name'));
        };
        $this->assertSame('FR own,FR,Any,Bad,DE', $names('fr'));
        $this->assertSame('DE,Any,Bad,FR own,FR', $names('de'));
        $this->assertSame('Any,Bad,FR own,FR,DE', $names('it'));
        $this->assertSame('Any,Bad,FR own,FR,DE', $names(''));

        // from the message of the customer
        $thread = \App\Thread::where('conversation_id', $c->id)->where('type', \App\Thread::TYPE_CUSTOMER)->orderBy('id', 'desc')->first() ?: new \App\Thread();
        $thread->forceFill(['conversation_id' => $c->id, 'type' => \App\Thread::TYPE_CUSTOMER, 'status' => 1, 'state' => 2, 'source_via' => 1, 'source_type' => 1, 'customer_id' => $c->customer_id,
            'created_by_customer_id' => $c->customer_id, 'body' => '<div>Guten Tag, ich habe meine Rechnung noch nicht erhalten.</div><blockquote>Bonjour, votre facture est prête, merci pour votre patience et à bientôt.</blockquote>']);
        $thread->save();
        $texts = ReplyStart::forTicket($c->fresh(), $this->s['bob'], $lang);
        $this->assertSame(['de', 'DE'], [$lang, $texts[0]['name']]);
        $this->assertStringContainsString('&quot;lang&quot;:&quot;de&quot;', $this->actingAs($this->s['bob'])->get($c->url())->getContent());
        // 1.27.0: no profile in the language of the message nor for all languages: the first one of the mailbox
        \App\Option::set(ReplyStart::TEXTS, [$row('FR', 'fr'), $row('IT', 'it'), $row('FR own', 'fr', 'only', $own)]);
        $this->assertSame(['FR own', 'FR', 'IT'], array_column(ReplyStart::forTicket($c->fresh(), $this->s['bob'], $lang), 'name'));
        $this->assertSame('de', $lang);
        $r = $this->actingAs($this->s['admin'])->get(route('settings', ['section' => 'refreshglobal']));
        $this->seeIn($r, 'data-rg-reply-off');
        $this->seeIn($r, 'data-rg-reply-save');
        $this->seeIn($r, '[0][lang]');
    }

    public function testLanguageOfAMessage()
    {
        foreach ([
            'fr' => ['Bonjour, je n’ai pas reçu ma commande, pouvez-vous vérifier ?', 'Merci pour votre réponse', 'Bonjour, ça ne marche pas'],
            'de' => ['Guten Tag, ich habe eine Frage zu meiner Rechnung.', 'Vielen Dank für Ihre Antwort', 'Grüezi, können Sie mir bitte helfen? Danke'],
            'en' => ['Hello, I have not received my order, could you please check?', 'Thanks for your help', 'It does not work'],
            'it' => ['Buongiorno, non ho ricevuto la mia fattura, potete verificare?', 'Grazie per la vostra risposta'],
            'es' => ['Hola, no he recibido mi pedido, ¿pueden comprobarlo?', 'Muchas gracias por su ayuda, estoy muy contento'],
            'pt' => ['Olá, não recebi a minha encomenda, podem verificar?', 'Obrigado pela sua ajuda, está tudo bem'],
            'nl' => ['Goedemiddag, ik heb mijn bestelling nog niet ontvangen, kunt u dit controleren?', 'Bedankt voor uw antwoord'],
            'da' => ['Hej, jeg har ikke modtaget min ordre, hvornår kommer den? Venlig hilsen'], 'sv' => ['Hej, jag har inte fått min beställning, när kommer den?'],
            'no' => ['Hei, jeg har ikke mottatt bestillingen min, når kommer den? Takk'], 'fi' => ['Hei, en ole saanut tilaustani, voitteko tarkistaa? Kiitos'],
            'pl' => ['Dzień dobry, nie otrzymałem mojego zamówienia, proszę o sprawdzenie.'], 'cs' => ['Dobrý den, ještě jsem nedostal svou objednávku, děkuji.'], 'sk' => ['Dobrý deň, ešte som nedostal svoju objednávku, ďakujem.'],
            'hu' => ['Jó napot, még nem kaptam meg a rendelésemet, köszönöm.'], 'ro' => ['Bună ziua, nu am primit comanda mea, vă rog să verificați.'], 'hr' => ['Dobar dan, još nisam dobio narudžbu, molim provjerite. Hvala'],
            'tr' => ['Merhaba, siparişim gelmedi, kontrol eder misiniz lütfen? Teşekkürler'], 'ru' => ['Здравствуйте, я не получил мой заказ, это возможно проверить?'], 'uk' => ['Добрий день, я не отримав моє замовлення, будь ласка, перевірте. Дякую'],
            'bg' => ['Здравейте, не съм получил моята поръчка, моля проверете.'], 'el' => ['Καλημέρα, δεν έλαβα την παραγγελία μου.'], 'ar' => ['مرحبا، لم أستلم طلبي'], 'he' => ['שלום, לא קיבלתי את ההזמנה שלי'],
            'zh' => ['您好，我还没有收到我的订单。'], 'ja' => ['こんにちは、注文した商品がまだ届いていません。'], 'ko' => ['안녕하세요, 주문을 아직 받지 못했습니다.'], 'th' => ['สวัสดี ฉันยังไม่ได้รับสินค้า'],
        ] as $code => $texts) {
            foreach ($texts as $text) {
                $this->assertSame($code, \Modules\RefreshGlobal\Services\LanguageGuess::find(['<p>'.$text.'</p>']), $text);
            }
        }
        $guess = function ($body, $subject = '') {
            return \Modules\RefreshGlobal\Services\LanguageGuess::find((array) $body, $subject);
        };
        foreach (['', 'OK', '12345', 'https://example.test/the/for/you', 'Zoé Dupont', "\xff\xfe"] as $text) {
            $this->assertSame('', $guess($text), $text);
        }
        $this->assertSame('de', $guess(['OK', '<p>Ich habe noch eine Frage zu meiner Rechnung.</p>']), 'an older message');
        $this->assertSame('fr', $guess('OK', 'Votre facture est dans mon dossier'), 'the subject');
        $this->assertSame('fr', $guess('<p>Bonjour, je vous remercie pour votre réponse.</p><p>-- </p><p>This e-mail is confidential and is only for the person that it is addressed to. If you are not this person please do not use it.</p>'), 'not the disclaimer');
        $this->assertSame('', $guess('<p>OK</p><blockquote>Bonjour, je vous remercie pour votre réponse</blockquote>'), 'not the quoted message');
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
        // 1.32.0: on by default, the next ticket of FreeScout too, and a closed ticket
        $this->assertSame($c->url(), $this->reply($bob, $c, $next));
        Settings::setFlag(Settings::REPLY_STAYS, false);
        $this->assertSame($c->url(), $this->reply($bob, $c, $list));
        $this->assertSame($next, $this->reply($bob, $c, $next), 'a next ticket is kept');
        // choices of the agent
        $this->assertSame($list, $this->reply($bob, $c, $list, MailboxUser::AFTER_SEND_FOLDER));
        $this->assertSame($c->url(), $this->reply($bob, $c, $c->url(), MailboxUser::AFTER_SEND_STAY));
        // other actions, new conversation, closed by the reply
        $this->assertSame($list, $this->reply($bob, $c, $list, MailboxUser::AFTER_SEND_NEXT, ['action' => 'conversation_change_status']));
        $this->assertSame($list, $this->reply($bob, $c, $list, MailboxUser::AFTER_SEND_NEXT, ['is_create' => 1]));
        $this->assertSame($list, $this->reply($bob, $c, $list, MailboxUser::AFTER_SEND_NEXT, [], true));
        Settings::setFlag(Settings::REPLY_STAYS, true);
        $this->assertSame($c->url(), $this->reply($bob, $c, $list), '1.32.0: a closed ticket too');
    }

    public function testNextTicketOfTheList()
    {
        Settings::setFlag(Settings::REPLY_GOES_NEXT, true);
        Settings::setFlag(Settings::REPLY_STAYS, false);
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
