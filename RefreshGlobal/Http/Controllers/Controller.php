<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker;
use Modules\RefreshGlobal\Services\Compatibility\Messages;
use Modules\RefreshGlobal\Services\MailboxAccess;

/**
 * Base of the module's controllers: no missing component may end in a blank page or an HTTP 500.
 * Any unexpected error is logged with the code RG-ERR-01 and replaced by an explicit page with links to the native
 * mailboxes.
 */
abstract class Controller extends \App\Http\Controllers\Controller
{
    protected function safely(callable $action)
    {
        try {
            return $action();
        } catch (\Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                || $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Illuminate\Auth\AuthenticationException
                || $e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Illuminate\Http\Exceptions\HttpResponseException
            ) {
                throw $e;
            }
            try {
                \Log::error('[RefreshGlobal] [RG-ERR-01] '.get_class($e).': '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
            } catch (\Throwable $ignored) {
            }
            $result = [
                'code' => 'RG-ERR-01', 'family' => 'error', 'severity' => 'blocking', 'status' => 'failed',
                'expected' => 'storage/logs/laravel.log', 'details' => get_class($e).': '.$e->getMessage(),
                'params' => ['item' => 'storage/logs/laravel.log'],
            ];

            return $this->blockedResponse([$result]);
        }
    }

    /** Page shown instead of the list: explicit message + links to the user's native mailboxes. */
    protected function blockedResponse(array $failed, $status = 200)
    {
        $user = auth()->user();
        $mailboxes = [];
        try {
            foreach ((new MailboxAccess($user))->mailboxes() as $mailbox) {
                $mailboxes[] = ['name' => $mailbox->name, 'url' => MailboxAccess::mailboxUrl($mailbox)];
            }
        } catch (\Throwable $e) {
            // ACL unavailable: only the dashboard link is offered
        }
        $data = [
            'failed'     => $failed,
            'is_admin'   => $user && method_exists($user, 'isAdmin') && $user->isAdmin(),
            'mailboxes'  => $mailboxes,
            'home_url'   => url('/'),
            'diagnostic' => \Route::has('refreshglobal.diagnostic') ? route('refreshglobal.diagnostic') : '',
        ];
        try {
            if (view()->exists('layouts.app')) {
                return response()->view('refreshglobal::blocked', $data, $status);
            }
        } catch (\Throwable $e) {
            // fall through to the page without FreeScout's layout
        }
        try {
            return response()->view('refreshglobal::blocked_plain', $data, $status);
        } catch (\Throwable $e) {
            $text = '';
            foreach ($failed as $r) {
                $text .= Messages::block($r)."\n\n";
            }

            return response('<pre>'.e($text).'</pre><p><a href="'.e(url('/')).'">FreeScout</a></p>', $status);
        }
    }

    protected function report()
    {
        return CompatibilityChecker::cached();
    }
}
