<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Conversation;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * Layout by width (Public/css/refreshglobal.css, Public/js/refreshglobal.js filtersPanel()): what the server prints —
 * active filters bar with one removable chip per filter, close button of the filters panel. The sliding panel itself
 * is checked in a browser (tests/RESULTS.md).
 */
class LayoutTest extends TestCase
{
    public function testActiveFiltersBarWithRemovableChips()
    {
        if (!$this->refreshInstalled()) {
            $this->markTestSkipped('Refresh is not installed: standard FreeScout look, no filters panel.');
        }
        $s = $this->s;
        $query = ['mb' => [$s['support']->id, $s['sales']->id], 'status' => [Conversation::STATUS_ACTIVE], 'q' => 'printer', 'assignee' => 'me'];
        $page = $this->ticketsPage($s['admin'], $query);
        $page->assertStatus(200);
        $this->seeIn($page, 'class="rg-active-filters"');
        $this->seeIn($page, '<span class="rg-af-val">'.e($s['support']->name).'</span>');
        $this->seeIn($page, '<span class="rg-af-val">« printer »</span>');
        $this->seeIn($page, '<span class="rg-af-val">'.e(__('refreshglobal::messages.me')).'</span>');
        // removing one mailbox keeps the other one and the other filters
        $this->seeIn($page, e(route('refreshglobal.tickets', ['mb' => [$s['sales']->id], 'status' => [Conversation::STATUS_ACTIVE], 'assignee' => 'me', 'q' => 'printer'])));
        // removing the search keeps the rest
        $this->seeIn($page, e(route('refreshglobal.tickets', ['mb' => [$s['support']->id, $s['sales']->id], 'status' => [Conversation::STATUS_ACTIVE], 'assignee' => 'me'])));
        // panel: close button, "Filters" button marked
        $this->seeIn($page, 'class="rg-filters-close"');
        $this->seeIn($page, 'rg-has-filters');
    }

    public function testNoBarWithoutFilters()
    {
        $page = $this->ticketsPage($this->s['admin'], ['reset' => 1]);
        $page->assertStatus(200);
        $this->dontSeeIn($page, 'class="rg-active-filters"');
    }

    public function testMailboxAddressIsNotInTheBadgeText()
    {
        // Refresh's phone pills copy the badge text: the address is an attribute drawn by CSS
        $page = $this->ticketsPage($this->s['admin']);
        $this->seeIn($page, 'class="rg-mailbox-email" data-email="'.e($this->s['support']->email).'"></span>');
    }
    /**
     * FreeScout bundles the module's main stylesheet with the others ("stylesheets" filter); its minifier does not
     * understand an @container inside an @media and nested every following rule in "@media (min-width: 768px)" (the
     * phone rules of the ticket page were lost). The container queries live in a file the page loads itself.
     */
    public function testBundledStylesheetHasNoContainerQuery()
    {
        $main = preg_replace('#/\*.*?\*/#s', '', file_get_contents(__DIR__.'/../../Public/css/refreshglobal.css'));
        $this->assertStringNotContainsString('@container', $main);
        $this->assertStringContainsString('@container rglayout', file_get_contents(__DIR__.'/../../Public/css/refreshglobal-layout.css'));
        $this->seeIn($this->ticketsPage($this->s['admin']), '/css/refreshglobal-layout.css');
        // phone: the subject of a ticket keeps its width next to long badges (Workflow Packs folder, origin tags)
        $this->assertStringContainsString('body.rf-m-ticket .conv-subjwrap > .conv-subjtext { flex: 1 1 0; min-width: 55%;', $main);
    }
}
