<?php

namespace Modules\RefreshGlobal\Services\Compatibility\Checks;

use Modules\RefreshGlobal\Services\Compatibility\Check;

/**
 * RG-ACL-01: FreeScout's list of the mailboxes a user can view (User::mailboxesCanView, app/User.php:251) is usable.
 * RG-ACL-02: FreeScout's "only assigned conversations" permission (User::canSeeOnlyAssignedConversations,
 * app/User.php:1353) is usable. Without either, the module can not apply the rights: blocking.
 */
class AclCheck extends Check
{
    public function run()
    {
        $user = $this->checker->user();

        $ok1 = method_exists('App\User', 'mailboxesCanView');
        $details1 = $ok1 ? '' : 'App\User::mailboxesCanView() missing';
        if ($ok1 && $user) {
            try {
                $mailboxes = $user->mailboxesCanView();
                $ok1 = is_array($mailboxes) || $mailboxes instanceof \Traversable;
                $details1 = $ok1 ? '' : 'unexpected result type';
            } catch (\Exception $e) {
                $ok1 = false;
                $details1 = 'error: '.$e->getMessage();
            }
        }

        $ok2 = method_exists('App\User', 'canSeeOnlyAssignedConversations');
        $details2 = $ok2 ? '' : 'App\User::canSeeOnlyAssignedConversations() missing';
        if ($ok2 && $user) {
            try {
                $user->canSeeOnlyAssignedConversations();
            } catch (\Exception $e) {
                $ok2 = false;
                $details2 = 'error: '.$e->getMessage();
            }
        }

        return [
            $this->result('RG-ACL-01', 'acl', self::BLOCKING, $ok1, 'App\User::mailboxesCanView()', $details1),
            $this->result('RG-ACL-02', 'acl_assigned', self::BLOCKING, $ok2, 'App\User::canSeeOnlyAssignedConversations()', $details2),
        ];
    }
}
