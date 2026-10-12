<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Modules\RefreshGlobal\Services\AutoFolders;
use Modules\RefreshGlobal\Services\GlobalTicketQuery;
use Modules\RefreshGlobal\Services\MailboxAccess;

/**
 * Overview of the automatic folders of Workflow Packs (GET /refresh-global/folders): per folder, the tickets the user
 * can see (all statuses but spam), open ones, per mailbox, the last filing and the automatic clean-up set in Workflow
 * Packs. Same rights as the list (GlobalTicketQuery). Without Workflow Packs 1.14.0+, an explicit message.
 */
class AutoFoldersController extends Controller
{
    public function index()
    {
        return $this->safely(function () {
            $user = auth()->user();
            $access = new MailboxAccess($user);
            $available = AutoFolders::available();
            $rows = [];
            if ($available) {
                $query = new GlobalTicketQuery($access, GlobalTicketQuery::normalize([], $access));
                $rows = AutoFolders::overview($query->base(), $access->mailboxes());
            }

            return response(view('refreshglobal::folders', [
                'available' => $available,
                'installed' => self::workflowPacksInstalled(),
                'rows'      => $rows,
                'mailboxes' => $access->mailboxes()->keyBy('id'),
                'wp_links'  => self::workflowPacksLinks($user),
            ])->render());
        });
    }

    protected static function workflowPacksInstalled()
    {
        try {
            return (bool) \App\Module::isActive(AutoFolders::ALIAS);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Links to the pages of Workflow Packs (rules, history), for the users it lets in: its own rule
     * (WorkflowPacksServiceProvider::userCanUse()), else administrators.
     */
    protected static function workflowPacksLinks($user)
    {
        $links = [];
        try {
            $provider = '\Modules\WorkflowPacks\Providers\WorkflowPacksServiceProvider';
            $allowed = class_exists($provider) && method_exists($provider, 'userCanUse') ? $provider::userCanUse($user) : $user->isAdmin();
            if (!$allowed) {
                return $links;
            }
            foreach (['workflowpacks.graph' => 'wp_rules', 'workflowpacks.history' => 'wp_history'] as $route => $label) {
                if (\Route::has($route)) {
                    $links[] = ['url' => route($route), 'label' => __('refreshglobal::messages.'.$label)];
                }
            }
        } catch (\Throwable $e) {
            // optional links
        }

        return $links;
    }
}
