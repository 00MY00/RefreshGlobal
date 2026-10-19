<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Thread;
use Modules\RefreshGlobal\Services\MailTags;
use Modules\RefreshGlobal\Services\RemoteImages;
use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * Remote images of the e-mails (Services/RemoteImages.php): show / ask / block, rules per origin tag (most
 * restrictive wins, prefixes), images of the message itself kept, through FreeScout's filter "thread.body_output".
 */
class RemoteImagesTest extends TestCase
{
    const PIXEL_MAIL = '<p>Hi</p><img src="https://track.example.org/open?u=1" width="1" height="1">'
        .'<img src=\'http://cdn.example.org/logo.png\' srcset="https://cdn.example.org/logo@2x.png 2x">'
        .'<img src="cid:part1@example.test"><img src="data:image/png;base64,AAAA">'
        .'<table background="https://cdn.example.org/bg.png"><tr><td style="background-image:url(\'https://cdn.example.org/x.png\'); color:red">x</td></tr></table>';

    protected function setUp(): void
    {
        parent::setUp();
        RemoteImages::reset();
        \App\Option::set(Settings::REMOTE_IMAGES, 'ask');
        \App\Option::set(Settings::REMOTE_IMAGES_RULES, '');
        Settings::setFlag(Settings::MAIL_TAGS, true);
    }

    protected function thread($conversation, array $tags)
    {
        $t = new Thread();
        $t->conversation_id = $conversation->id;
        $t->type = Thread::TYPE_CUSTOMER;
        $t->state = Thread::STATE_PUBLISHED;
        $t->status = Thread::STATUS_ACTIVE;
        $t->body = self::PIXEL_MAIL;
        $t->message_id = 'ri-'.uniqid().'@example.test';
        $t->source_via = Thread::PERSON_CUSTOMER;
        $t->source_type = Thread::SOURCE_TYPE_EMAIL;
        $t->save();
        if ($tags) {
            MailTags::attach($conversation, $t, $tags, 'INBOX');
        }

        return $t;
    }

    public function testAskKeepsTheAddressesBehindAButton()
    {
        list($html, $n) = RemoteImages::neutralize(self::PIXEL_MAIL, true);
        $this->assertSame(3, $n); // 2 images (src) + 1 url() of a style; srcset / background are neutralized too
        $this->assertDoesNotMatchRegularExpression('#[^-]src="https://track#', $html);
        $this->assertStringContainsString('data-rg-src="https://track.example.org/open?u=1"', $html);
        $this->assertStringContainsString('data-rg-srcset="https://cdn.example.org/logo@2x.png 2x"', $html);
        $this->assertStringContainsString('data-rg-background="https://cdn.example.org/bg.png"', $html);
        $this->assertStringContainsString('data-rg-style="background-image:url(\'https://cdn.example.org/x.png\'); color:red"', $html);
        $this->assertStringContainsString('style="background-image:none; color:red"', $html);
        // images of the message itself untouched
        $this->assertStringContainsString('<img src="cid:part1@example.test">', $html);
        $this->assertStringContainsString('<img src="data:image/png;base64,AAAA">', $html);
        // same site as this FreeScout: kept
        $own = rtrim(config('app.url'), '/').'/storage/attachment/1.png';
        $this->assertSame(0, RemoteImages::neutralize('<img src="'.$own.'">')[1]);
    }

    public function testBlockRemovesTheAddresses()
    {
        list($html, $n) = RemoteImages::neutralize(self::PIXEL_MAIL, false);
        $this->assertGreaterThan(0, $n);
        $this->assertStringNotContainsString('example.org', $html);
    }

    public function testRulesPerTag()
    {
        $rules = RemoteImages::rules("# comment\nclassified_spam = block\ninbox = show\nfolder:clients-* = show\nbad line\nx = maybe");
        $this->assertSame([['tag' => 'classified_spam', 'mode' => 'block'], ['tag' => 'inbox', 'mode' => 'show'], ['tag' => 'folder:clients-*', 'mode' => 'show']], $rules);
        $this->assertSame('show', RemoteImages::modeFor(['inbox'], $rules, 'ask'));
        $this->assertSame('show', RemoteImages::modeFor(['folder:clients-acme'], $rules, 'ask'));
        $this->assertSame('ask', RemoteImages::modeFor(['folder:other'], $rules, 'ask'));
        // most restrictive wins
        $this->assertSame('block', RemoteImages::modeFor(['inbox', 'classified_spam'], $rules, 'ask'));
        $this->assertSame('block', RemoteImages::modeFor([], [], 'block'));
    }

    public function testThroughFreeScoutFilter()
    {
        $c = $this->s['t_support_open'];
        $inbox = $this->thread($c, ['inbox']);
        $spam = $this->thread($c, ['inbox', 'classified_spam']);
        \App\Option::set(Settings::REMOTE_IMAGES_RULES, "classified_spam = block\ninbox = ask");

        $out = \Eventy::filter('thread.body_output', self::PIXEL_MAIL, $inbox, $c, $c->mailbox);
        $this->assertStringContainsString('rg-remote-images rg-ri-ask', $out);
        $this->assertStringContainsString('rg-ri-show', $out);
        $this->assertStringContainsString('data-rg-src=', $out);
        // after FreeScout's own cleaning (safe_raw_html), the attributes are still there
        $this->assertStringContainsString('data-rg-src=', safe_raw_html($out));

        $out = \Eventy::filter('thread.body_output', self::PIXEL_MAIL, $spam, $c, $c->mailbox);
        $this->assertStringContainsString('rg-ri-block', $out);
        $this->assertStringNotContainsString('rg-ri-show', $out);
        $this->assertStringNotContainsString('track.example.org', $out);

        \App\Option::set(Settings::REMOTE_IMAGES, 'show');
        \App\Option::set(Settings::REMOTE_IMAGES_RULES, '');
        $this->assertSame(self::PIXEL_MAIL, \Eventy::filter('thread.body_output', self::PIXEL_MAIL, $inbox, $c, $c->mailbox));
        // a message without remote image is left as it is
        \App\Option::set(Settings::REMOTE_IMAGES, 'block');
        $this->assertSame('<p>plain</p>', \Eventy::filter('thread.body_output', '<p>plain</p>', $inbox, $c, $c->mailbox));
    }

    public function testSettingsPage()
    {
        $r = $this->actingAs($this->s['admin'])->get(route('settings', ['section' => 'refreshglobal']));
        $r->assertStatus(200);
        $this->seeIn($r, 'name="settings['.Settings::REMOTE_IMAGES.']"');
        $this->seeIn($r, 'name="settings['.Settings::REMOTE_IMAGES_RULES.']"');
        $this->seeIn($r, 'name="settings['.Settings::MAIL_MOVE.']"');
        // unknown mode falls back to "ask"
        \App\Option::set(Settings::REMOTE_IMAGES, 'whatever');
        $this->assertSame('ask', Settings::remoteImages());
    }
}
