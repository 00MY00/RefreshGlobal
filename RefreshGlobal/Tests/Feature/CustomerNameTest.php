<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Modules\RefreshGlobal\Services\CustomerName;
use Modules\RefreshGlobal\Services\ReplyStart;
use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Tests\TestCase;

/** 1.25.0: first and last name of the customer (record, signature, address). Invented names only. */
class CustomerNameTest extends TestCase
{
    protected function found($first, $last, $email = '', $body = '', $company = '')
    {
        $n = CustomerName::find($first, $last, $email, $body === '' ? [] : [$body], $company);

        return $n['first'].'|'.$n['last'];
    }

    public function testRecord()
    {
        foreach ([
            ['Jane', 'Doe', 'Jane|Doe'], ['JANE', 'DOE', 'Jane|Doe'], ['jane', 'doe', 'Jane|Doe'], ['Jean Pierre', 'Dupont', 'Jean Pierre|Dupont'],
            ['Jane Doe', '', 'Jane|Doe'], ['', 'Jane Doe', 'Jane|Doe'], ['Doe, Jane', '', 'Jane|Doe'], ['Doe,', 'Jane', 'Jane|Doe'], ['DOE Jane', '', 'Jane|Doe'], ['Jane DOE', '', 'Jane|Doe'],
            ['Dr. Jane', 'Doe', 'Jane|Doe'], ['Jan van der', 'Berg', 'Jan van der|Berg'], ['Jan van der Berg', '', 'Jan|van der Berg'], ['Maria', 'Sa', 'Maria|Sa'], ['JEAN-LUC', "D'ARC", "Jean-Luc|D'Arc"],
            ['Анна', 'Иванова', 'Анна|Иванова'], ['花子', '', '花子|'],
            ['Example', 'GmbH', '|'], ['Example SA', '', '|'], ['Support', 'Example', '|'], ['info@example.test', '', '|'], ['Service client', '', '|'], ['', '', '|'],
        ] as $c) {
            $this->assertSame($c[2], $this->found($c[0], $c[1]), $c[0].'/'.$c[1]);
        }
        $this->assertSame('Example GmbH', CustomerName::find('Example', 'GmbH')['full'], 'a company stays the full name');
    }

    public function testSignatureInEveryLanguage()
    {
        foreach (['Cordialement', 'Meilleures salutations,', 'Bien à vous', 'Kind regards,', 'Best,', 'Thanks!', 'Mit freundlichen Grüßen', 'Freundliche Grüsse', 'LG', 'Saludos cordiales', 'Un saludo,', 'Cordiali saluti', 'Grazie,',
            'Met vriendelijke groet,', 'Atenciosamente,', 'Obrigada,', 'Z poważaniem', 'Med venlig hilsen', 'Med vänliga hälsningar', 'S pozdravem', 'Üdvözlettel', 'Saygılarımla,', 'Cu stimă,', 'С уважением,', 'Με εκτίμηση,', '-- '] as $closing) {
            $this->assertSame('Zoé|Dupont', $this->found('', '', 'x1@example.test', '<div>Text of the message.</div><div>'.$closing.'</div><p><b>Zoé Dupont</b></p><div>Example Ltd</div><div>Tel 000</div>'), $closing);
        }
        $this->assertSame('Zoé|Dupont', $this->found('', '', '', 'Hello<br>Cordialement, Zoé Dupont'), 'same line');
        $this->assertSame('Zoé|', $this->found('', '', '', 'Hello<br>Merci<br><br>Zoé'), 'first name only');
        $this->assertSame('Анна|Иванова', $this->found('', '', '', 'Текст<br>С уважением,<br>Анна Иванова'));
        foreach (['Zoé DUPONT', 'DUPONT Zoé', 'Mme Zoé Dupont', 'Zoé Dupont | Manager', 'Zoé Dupont, Manager', 'Zoé Dupont (Example)', '*Zoé Dupont*', 'ZOÉ DUPONT'] as $line) {
            $this->assertSame('Zoé|Dupont', $this->found('', '', '', 'Hello<br>Cordialement<br>'.$line), $line);
        }
        $this->assertSame('Jan|van der Berg', $this->found('', '', '', 'Tekst<br>Met vriendelijke groeten,<br>Jan van der Berg'));
        $this->assertSame('María José|García', $this->found('', '', 'mariajose.garcia@example.test', 'Texto<br>Saludos,<br>María José García'), 'the address shows where the first name ends');
    }

    public function testDoubtGivesNothing()
    {
        foreach (['Cordialement<br>Service comptabilité', 'Cordialement<br>Ville de Exemple', 'Kind regards<br>The Example Team', 'Merci pour votre aide', 'Merci<br>Envoyé de mon iPhone', 'Thanks<br>Sent from my Android phone',
            'LG<br>JD', 'Cordialement<br>+00 000 00 00', 'Cordialement<br>www.example.test', 'Cordialement<br>Example SA', 'Hello<br>Bonjour Sam', 'I met Sam Agent.<br>Hello Sam Agent', 'Merci<br>Cordialement'] as $body) {
            $this->assertSame('|', $this->found('', '', 'info@example.test', $body), $body);
        }
        $this->assertSame('|', $this->found('', '', 'jane.doe@example.test', 'Hello'), 'the address alone gives no name');
        $this->assertSame('|', $this->found('', '', '', 'Hello<br>Cordialement<br>Nova Terra', 'Nova Terra'), 'the company of the record');
        // quoted messages: the agent's signature is not the customer's
        foreach (['<blockquote>Kind regards<br>Sam Agent</blockquote>', '<div class="gmail_quote">Kind regards<br>Sam Agent</div>', '<br>&gt; Kind regards<br>Sam Agent', '<br>On Mon, 1 Jan 2026 at 10:00, Support wrote:<br>Kind regards<br>Sam Agent',
            '<br>Le 1 janvier 2026 à 10:00, Support a écrit :<br>Cordialement<br>Sam Agent', '<br>-----Original Message-----<br>Regards<br>Sam Agent', '<br>Von: Support<br>Gruss<br>Sam Agent'] as $quote) {
            $this->assertSame('|', $this->found('', '', '', '<div>Ok for me</div>'.$quote), $quote);
        }
    }

    public function testRecordAndSignatureTogether()
    {
        $sign = function ($s) {
            return 'Hello<br>Cordialement<br>'.$s;
        };
        $this->assertSame('Jane|Doe', $this->found('Jane', 'Doe', '', $sign('Sam Other')), 'never another person');
        $this->assertSame('Jane|Doe', $this->found('Jane', '', '', $sign('Jane Doe')), 'the missing part');
        $this->assertSame('Jane|Doe', $this->found('Doe', '', '', $sign('Jane Doe')));
        $this->assertSame('Jane|Doe', $this->found('Doe', 'Jane', '', $sign('Jane DOE')), 'the order');
        $this->assertSame('Jane|Doe', $this->found('Doe', 'Jane', '', $sign('Jane')));
        $this->assertSame('Jane|Doe', $this->found('Jane', 'Doe', '', $sign('Doe Jane')), 'not certain: the record');
        $this->assertSame('Zoé|Dupont', $this->found('Example', 'GmbH', '', $sign('Zoé Dupont')), 'a company signs with a person');
        $this->assertSame('Jane|Doe', $this->found('', '', 'jane.doe@example.test', $sign('Jane')), 'the address completes');
        $this->assertSame('Jane|Doe', $this->found('', '', 'j.doe@example.test', 'Hello, see the file.<br>Jane Doe<br>Example Ltd'), 'no closing words: the address confirms');
        // broken or huge messages
        $this->assertSame('Jane|Doe', $this->found('Jane', 'Doe', '', "\xff\xfe<div"));
        $this->assertSame('Jane|Doe', $this->found('Jane', 'Doe', '', str_repeat('<div>long text </div>', 50000)));
    }

    public function testInAReply()
    {
        $c = $this->s['t_support_open'];
        $customer = $c->customer;
        $customer->first_name = 'Info';
        $customer->last_name = '';
        $customer->save();
        $thread = \App\Thread::where('conversation_id', $c->id)->where('type', \App\Thread::TYPE_CUSTOMER)->orderBy('id', 'desc')->first();
        if (!$thread) {
            $thread = new \App\Thread();
            $thread->conversation_id = $c->id;
            $thread->type = \App\Thread::TYPE_CUSTOMER;
            $thread->status = 1;
            $thread->state = 2;
            $thread->source_via = 1;
            $thread->source_type = 1;
            $thread->customer_id = $customer->id;
        }
        $thread->created_by_customer_id = $customer->id;
        $thread->body = '<div>Hello</div><div>Kind regards</div><div>Zoé Dupont</div>';
        $thread->save();
        \App\Option::set(ReplyStart::ENABLED, 1);
        \App\Option::set(ReplyStart::TEXTS, [['name' => 'a', 'text' => 'Bonjour $first_name $last_name,', 'where' => 'all']]);
        $this->assertSame('<div>Bonjour Zoé Dupont,</div><div><br></div>', ReplyStart::forTicket($c->fresh(), $this->s['bob'])[0]['html']);
        // switched off: the record as it is
        Settings::setFlag(ReplyStart::DETECT, false);
        $this->assertSame('<div>Bonjour Info,</div><div><br></div>', ReplyStart::forTicket($c->fresh(), $this->s['bob'])[0]['html']);
    }
}
