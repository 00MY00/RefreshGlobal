<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use App\Conversation;
use App\User;
use Modules\RefreshGlobal\Services\Settings;
use Modules\RefreshGlobal\Services\Trash;
use Modules\RefreshGlobal\Tests\Support\Fixtures;
use Modules\RefreshGlobal\Tests\TestCase;

/** "Empty the trash" button and automatic emptying (Services/Trash.php), with FreeScout's rules. */
class TrashTest extends TestCase
{
    protected function deleted($mailbox, $subject, array $attributes = [])
    {
        return Fixtures::conversation($mailbox, $this->s['prefix'].'-'.$subject, $attributes + [
            'state' => Conversation::STATE_DELETED, 'user_updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function exists($conversation)
    {
        return Conversation::where('id', $conversation->id)->exists();
    }

    protected function allowDelete(User $user)
    {
        $user->permissions = array_replace((array) $user->permissions, [User::PERM_DELETE_CONVERSATIONS => true]);
        $user->save();

        return $user->fresh();
    }

    public function testAdminEmptiesTheTrash()
    {
        $support = $this->deleted($this->s['support'], 'TRASH-SUPPORT');
        $r = $this->actingAs($this->s['admin'])->post(route('refreshglobal.trash.empty'), ['back' => 'settings']);
        $r->assertRedirect(route('settings', ['section' => 'refreshglobal']));
        $this->assertFalse($this->exists($support));
        $this->assertFalse($this->exists($this->s['t_billing_deleted']));
        // tickets that are not in the trash stay
        $this->assertTrue($this->exists($this->s['t_support_open']));
        $this->assertTrue($this->exists($this->s['t_sales_draft']));
        $this->assertTrue($this->exists($this->s['t_support_spam']));
    }

    public function testUserWithoutPermissionCanNotEmpty()
    {
        $this->actingAs($this->s['bob'])->post(route('refreshglobal.trash.empty'))->assertStatus(403);
        $this->assertTrue($this->exists($this->s['t_billing_deleted']));
        $this->dontSeeIn($this->ticketsPage($this->s['bob']), route('refreshglobal.trash.empty'));
    }

    public function testUserEmptiesOnlyHisMailboxes()
    {
        $bob = $this->allowDelete($this->s['bob']);
        $mine = $this->deleted($this->s['support'], 'TRASH-BOB');
        $this->assertSame(1, Trash::count($bob));
        $this->seeIn($this->ticketsPage($bob), route('refreshglobal.trash.empty'));

        $this->actingAs($bob)->post(route('refreshglobal.trash.empty'))->assertRedirect(route('refreshglobal.tickets'));
        $this->assertFalse($this->exists($mine));
        // billing is not one of bob's mailboxes
        $this->assertTrue($this->exists($this->s['t_billing_deleted']));
    }

    public function testOnlyAssignedUserEmptiesOnlyHisTickets()
    {
        $carol = $this->allowDelete($this->s['carol']);
        $hers = $this->deleted($this->s['billing'], 'TRASH-CAROL', ['user_id' => $carol->id]);
        Trash::emptyFor($carol);
        $this->assertFalse($this->exists($hers));
        $this->assertTrue($this->exists($this->s['t_billing_deleted'])); // not assigned to her
    }

    public function testAutomaticEmptyingKeepsRecentTickets()
    {
        $old = $this->deleted($this->s['support'], 'TRASH-OLD', ['user_updated_at' => date('Y-m-d H:i:s', time() - 40 * 86400)]);
        $recent = $this->deleted($this->s['support'], 'TRASH-RECENT', ['user_updated_at' => date('Y-m-d H:i:s', time() - 2 * 86400)]);

        // setting 0 (default): the scheduled run does nothing
        Settings::setTrashAutoDays(0);
        $this->assertSame(0, \Artisan::call('refreshglobal:trash', ['--scheduled' => true]));
        $this->assertTrue($this->exists($old));

        Settings::setTrashAutoDays(30);
        $this->assertSame(0, \Artisan::call('refreshglobal:trash', ['--scheduled' => true]));
        $this->assertFalse($this->exists($old));
        $this->assertTrue($this->exists($recent));
        $this->assertTrue($this->exists($this->s['t_support_open']));

        $commands = array_map(function ($e) {
            return (string) $e->command;
        }, app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $this->assertNotEmpty(array_filter($commands, function ($c) {
            return strpos($c, 'refreshglobal:trash --scheduled') !== false;
        }));
        Settings::setTrashAutoDays(0);
    }

    public function testSettingIsSavedByFreeScout()
    {
        $page = $this->actingAs($this->s['admin'])->get(route('settings', ['section' => 'refreshglobal']));
        $this->seeIn($page, 'name="settings['.Settings::TRASH_AUTO_DAYS.']"');
        $this->seeIn($page, 'form="rg-trash-form"');
        $this->seeIn($page, 'data-rg-confirm=');

        foreach (['30' => 30, '-5' => 0, '99999' => Settings::TRASH_AUTO_DAYS_MAX] as $posted => $expected) {
            $this->actingAs($this->s['admin'])->post(route('settings.save', ['section' => 'refreshglobal']), ['settings' => [
                Settings::TRASH_AUTO_DAYS => $posted,
            ]])->assertStatus(302);
            $this->assertSame($expected, Settings::trashAutoDays(), "posted $posted");
        }
        Settings::setTrashAutoDays(0);
    }
}
