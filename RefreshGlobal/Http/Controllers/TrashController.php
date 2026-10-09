<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Illuminate\Http\Request;
use Modules\RefreshGlobal\Services\Trash;

/** "Empty the trash" button: tickets in the trash of the user's mailboxes are deleted for good (Services\Trash). */
class TrashController extends Controller
{
    public function empty(Request $request)
    {
        return $this->safely(function () use ($request) {
            $user = auth()->user();
            if (!Trash::userCanEmpty($user)) {
                abort(403);
            }
            $n = Trash::emptyFor($user);
            \Log::info('[RefreshGlobal] [trash] emptied by user #'.$user->id.': '.$n.' ticket(s) deleted for good.');

            $back = $request->input('back') === 'settings'
                ? route('settings', ['section' => 'refreshglobal'])
                : route('refreshglobal.tickets');

            return redirect($back)->with('flash_success', $n
                ? __('refreshglobal::messages.trash_emptied', ['count' => $n])
                : __('refreshglobal::messages.trash_nothing'));
        });
    }
}
