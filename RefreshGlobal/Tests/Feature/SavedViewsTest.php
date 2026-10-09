<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Modules\RefreshGlobal\Entities\SavedView;
use Modules\RefreshGlobal\Tests\TestCase;

class SavedViewsTest extends TestCase
{
    protected function store($user, array $data)
    {
        return $this->actingAs($user)->post(route('refreshglobal.views.store'), $data);
    }

    public function testCreateLoadRenameDefaultDelete()
    {
        $s = $this->s;
        $this->store($s['bob'], ['name' => 'Sales only', 'mb' => [$s['sales']->id]])->assertStatus(302);
        $view = SavedView::forUser($s['bob'])->where('name', 'Sales only')->first();
        $this->assertNotNull($view);
        $this->assertSame([$s['sales']->id], $view->filters['mailboxes']);

        $r = $this->ticketsPage($s['bob'], ['view' => $view->id]);
        $this->seeIn($r, $s['prefix'].'-SALES-OPEN');
        $this->dontSeeIn($r, $s['prefix'].'-SUPPORT-OPEN');
        $this->actingAs($s['bob'])->post(route('refreshglobal.views.rename', ['id' => $view->id]), ['name' => 'Sales'])->assertStatus(302);
        $this->assertSame('Sales', $view->fresh()->name);

        $this->actingAs($s['bob'])->post(route('refreshglobal.views.default', ['id' => $view->id]))->assertStatus(302);
        $this->assertTrue($view->fresh()->is_default);
        // the default view opens on the bare address
        $r = $this->ticketsPage($s['bob']);
        $this->dontSeeIn($r, $s['prefix'].'-SUPPORT-OPEN');
        // ?reset=1 shows everything
        $this->seeIn($this->ticketsPage($s['bob'], ['reset' => 1]), $s['prefix'].'-SUPPORT-OPEN');
        $this->actingAs($s['bob'])->delete(route('refreshglobal.views.destroy', ['id' => $view->id]))->assertStatus(302);
        $this->assertNull(SavedView::find($view->id));
    }

    public function testForbiddenMailboxesAreNotStored()
    {
        $s = $this->s;
        $this->store($s['bob'], ['name' => 'Try', 'mb' => [$s['billing']->id, $s['sales']->id]]);
        $view = SavedView::forUser($s['bob'])->where('name', 'Try')->first();
        $this->assertSame([$s['sales']->id], $view->filters['mailboxes']);
    }

    public function testLostMailboxIsIgnoredWithNotice()
    {
        $s = $this->s;
        $this->store($s['bob'], ['name' => 'Support', 'mb' => [$s['support']->id, $s['sales']->id]]);
        $view = SavedView::forUser($s['bob'])->where('name', 'Support')->first();
        // Bob loses access to Support
        $s['bob']->mailboxes()->sync([$s['sales']->id]);
        $r = $this->ticketsPage($s['bob']->fresh(), ['view' => $view->id]);
        $r->assertStatus(200);
        $this->dontSeeIn($r, $s['prefix'].'-SUPPORT-OPEN');
        $this->seeIn($r, 'rg-dropped');
    }

    public function testViewsArePersonal()
    {
        $s = $this->s;
        $this->store($s['bob'], ['name' => 'Bob view']);
        $view = SavedView::forUser($s['bob'])->where('name', 'Bob view')->first();

        $this->actingAs($s['dave'])->post(route('refreshglobal.views.rename', ['id' => $view->id]), ['name' => 'x'])->assertStatus(404);
        $this->actingAs($s['dave'])->delete(route('refreshglobal.views.destroy', ['id' => $view->id]))->assertStatus(404);
        $this->dontSeeIn($this->ticketsPage($s['dave']), 'Bob view');
        // loading somebody else's view id falls back to the normal list
        $this->dontSeeIn($this->ticketsPage($s['dave'], ['view' => $view->id]), 'Bob view');
        $this->assertSame('Bob view', $view->fresh()->name);
    }

    public function testValidation()
    {
        $s = $this->s;
        $this->store($s['bob'], ['name' => ''])->assertSessionHasErrors('name');
        $this->store($s['bob'], ['name' => str_repeat('x', 101)])->assertSessionHasErrors('name');
    }
}
