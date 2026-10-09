<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Modules\RefreshGlobal\Tests\TestCase;

/** Language switch: it changes the user's FreeScout language, the one FreeScout, Refresh and the module follow. */
class LanguageTest extends TestCase
{
    public function testSwitchIsShownOnThePage()
    {
        $r = $this->ticketsPage($this->s['bob']);
        $this->seeIn($r, 'class="form-control input-sm rg-language-select"');
        $this->seeIn($r, route('refreshglobal.language'));
        $this->seeIn($r, 'value="fr"');
    }

    public function testChangesTheProfileLanguage()
    {
        $bob = $this->s['bob'];
        $back = route('refreshglobal.tickets');
        $r = $this->actingAs($bob)->from($back)->post(route('refreshglobal.language'), ['locale' => 'fr']);
        $r->assertRedirect($back);
        $this->assertSame('fr', $bob->fresh()->locale);
        $this->assertSame('fr', session('user_locale'));

        // next page in French (module texts)
        $this->seeIn($this->actingAs($bob->fresh())->get($back), 'Toutes les boîtes');
    }

    public function testUnknownLanguageIsRefused()
    {
        $bob = $this->s['bob'];
        $before = $bob->locale;
        $this->actingAs($bob)->post(route('refreshglobal.language'), ['locale' => 'xx-evil'])->assertStatus(302);
        $this->assertSame($before, $bob->fresh()->locale);
    }

    public function testExternalRefererIsIgnored()
    {
        $r = $this->actingAs($this->s['bob'])->from('https://elsewhere.example.test/x')
            ->post(route('refreshglobal.language'), ['locale' => 'de']);
        $r->assertRedirect(route('refreshglobal.tickets'));
    }
}
