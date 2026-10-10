{{--
    "Fetch e-mails now" (circular arrow): only when the SyncNow module is installed and the user may use it
    (Services/SyncNow.php). Public/js/refreshglobal.js runs SyncNow's own force + status calls for each mailbox.
    Parameters: class (button classes), label (bool: show the text).
--}}
@if (!empty($rg_sync))
    <button type="button" class="{{ $class }} rg-sync-btn" title="{{ __('refreshglobal::messages.sync_now_help') }}"
        data-mailboxes="{{ json_encode($rg_sync, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
        data-msg-running="{{ __('refreshglobal::messages.sync_running') }}"
        data-msg-none="{{ __('refreshglobal::messages.sync_none') }}"
        data-msg-done="{{ __('refreshglobal::messages.sync_done') }}"
        data-msg-problem="{{ __('refreshglobal::messages.sync_problem') }}"
        data-msg-cooldown="{{ __('refreshglobal::messages.sync_cooldown') }}">
        <i class="glyphicon glyphicon-refresh rg-sync-icon"></i>@if (!empty($label)) <span class="rg-sync-label">{{ __('refreshglobal::messages.sync_now') }}</span>@endif
    </button>
@endif
