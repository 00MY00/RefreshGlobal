<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Modules\RefreshGlobal\Services\Search\GlobalSearch;
use Modules\RefreshGlobal\Tests\TestCase;

/**
 * Search provider "refreshglobal.global-search" of the FreeScout Interop contract (protocol 1.1): the JSON endpoint
 * GET /refresh-global/search, the server filters fsinterop.search.providers / fsinterop.search.results, and the
 * user's rights on every path.
 */
class SearchTest extends TestCase
{
    public function testEndpointFindsTicketsOfEveryAllowedMailbox()
    {
        $p = $this->s['prefix'];
        $r = $this->actingAs($this->s['bob'])->get(route('refreshglobal.search', ['q' => $p]));
        $r->assertStatus(200);
        $data = json_decode($r->getContent(), true);
        $this->assertSame(['1.0', 'refreshglobal.global-search', 'global-mailboxes'], [$data['schema'], $data['provider'], $data['context']]);
        $subjects = array_column($data['items'], 'subject');
        $this->assertContains($p.'-SUPPORT-OPEN printer broken', $subjects);
        $this->assertContains($p.'-SALES-OPEN quote request', $subjects);
        // never another mailbox, a draft, a deleted ticket or spam (like the "All mailboxes" list)
        foreach ($subjects as $subject) {
            $this->assertStringNotContainsString('-BILLING-', $subject);
            $this->assertStringNotContainsString('-DRAFT', $subject);
            $this->assertStringNotContainsString('-SPAM', $subject);
        }
        $this->assertSame(count($data['items']), $data['total']);
        $item = $data['items'][0];
        foreach (['id', 'number', 'subject', 'status', 'mailbox', 'customer', 'url', 'updated_at'] as $key) {
            $this->assertArrayHasKey($key, $item);
        }
        $this->assertContains($item['status'], ['active', 'pending', 'closed', 'spam']);
    }

    public function testLimitShortTextAndRequestedMailboxes()
    {
        $p = $this->s['prefix'];
        $data = json_decode($this->actingAs($this->s['bob'])->get(route('refreshglobal.search', ['q' => $p, 'limit' => 1]))->getContent(), true);
        $this->assertCount(1, $data['items']);
        $this->assertGreaterThan(1, $data['total'], 'total counts every match');

        $data = json_decode($this->actingAs($this->s['bob'])->get(route('refreshglobal.search', ['q' => 'a']))->getContent(), true);
        $this->assertSame([0, []], [$data['total'], $data['items']], 'one character: nothing searched');

        // a mailbox of the request the user can not see is ignored, never opened
        $data = json_decode($this->actingAs($this->s['bob'])->get(route('refreshglobal.search', ['q' => $p, 'mb' => [$this->s['billing']->id]]))->getContent(), true);
        foreach (array_column($data['items'], 'subject') as $subject) {
            $this->assertStringNotContainsString('-BILLING-', $subject);
        }
        $this->assertSame(GlobalSearch::MAX_LIMIT, GlobalSearch::cleanLimit(1000));
        $this->assertSame(GlobalSearch::DEFAULT_LIMIT, GlobalSearch::cleanLimit('x'));
    }

    public function testOnlyAssignedRightAndGuests()
    {
        $p = $this->s['prefix'];
        $data = json_decode($this->actingAs($this->s['carol'])->get(route('refreshglobal.search', ['q' => $p]))->getContent(), true);
        $this->assertSame([$p.'-BILLING-CAROL invoice'], array_column($data['items'], 'subject'));

        auth()->logout();
        $this->assertNotSame(200, $this->get(route('refreshglobal.search', ['q' => $p]))->getStatusCode());
    }

    public function testServerFilters()
    {
        $providers = \Eventy::filter('fsinterop.search.providers', []);
        $this->assertSame(route('refreshglobal.search'), $providers['refreshglobal.global-search']['endpoint']);

        $p = $this->s['prefix'];
        $results = \Eventy::filter('fsinterop.search.results', null, ['scope' => 'global-mailboxes', 'query' => $p.'-SALES', 'user_id' => $this->s['bob']->id]);
        $this->assertSame([$p.'-SALES-OPEN quote request'], array_column($results['items'], 'subject'));
        // another scope: not answered
        $this->assertEmpty(\Eventy::filter('fsinterop.search.results', null, ['scope' => 'mailbox', 'query' => $p]));
    }
}
