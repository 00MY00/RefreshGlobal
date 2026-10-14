<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Modules\RefreshGlobal\Services\MailTags;

/**
 * "Recalculate the tags" (Manage › Settings › RefreshGlobal, administrators): only records the request; FreeScout's
 * scheduler runs refreshglobal:mail-tags --requested every minute, which reads the mail servers outside any web
 * request (Console/MailTagsCommand.php).
 */
class MailTagsController extends Controller
{
    public function recalc()
    {
        return $this->safely(function () {
            if (!MailTags::tableExists()) {
                return redirect()->route('settings', ['section' => 'refreshglobal'])
                    ->with('flash_error', __('refreshglobal::messages.mail_tags_no_table'));
            }
            MailTags::requestRecalc();

            return redirect()->route('settings', ['section' => 'refreshglobal'])
                ->with('flash_success', __('refreshglobal::messages.mail_tags_recalc_requested'));
        });
    }
}
