{{-- Details and raw text of a message (Http/Controllers/ThreadDetailsController.php), loaded into a window. --}}
@php
    $rg_date = function ($d) {
        if (!$d) {
            return '';
        }
        try {
            return \App\User::dateFormat($d, 'Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return (string) $d;
        }
    };
    $rg_list = function ($v) {
        if (is_array($v)) {
            return implode(', ', $v);
        }
        $j = json_decode((string) $v, true);

        return is_array($j) ? implode(', ', $j) : (string) $v;
    };
    $rg_type = [\App\Thread::TYPE_CUSTOMER => 'thread_customer', \App\Thread::TYPE_MESSAGE => 'thread_agent', \App\Thread::TYPE_NOTE => 'thread_note'][$thread->type] ?? 'thread_other';
    $rg_raw = trim((string) $thread->headers);
    $rg_raw = ($rg_raw !== '' ? $rg_raw."\n\n" : '').$plain;
@endphp
<div class="rg-td" data-thread="{{ $thread->id }}">
    <div class="rg-td-tabs" role="tablist">
        <button type="button" class="rg-td-tab active" data-tab="details" role="tab">{{ __('refreshglobal::messages.td_details') }}</button>
        <button type="button" class="rg-td-tab" data-tab="raw" role="tab">{{ __('refreshglobal::messages.td_raw') }}</button>
    </div>
    <div class="rg-td-pane active" data-pane="details">
        <table class="rg-td-table">
            <tr><th>{{ __('refreshglobal::messages.td_type') }}</th><td>{{ __('refreshglobal::messages.'.$rg_type) }} · #{{ $conversation->number }} · {{ __('refreshglobal::messages.td_message') }} {{ $thread->id }}</td></tr>
            <tr><th>{{ __('refreshglobal::messages.td_from') }}</th><td>{{ $thread->from ?: ($thread->isCustomerMessage() && $thread->customer ? $thread->customer->getFullName(true).' <'.$thread->customer->getMainEmail().'>' : ($thread->created_by_user ? $thread->created_by_user->getFullName() : '')) }}</td></tr>
            <tr><th>{{ __('refreshglobal::messages.td_to') }}</th><td>{{ $rg_list($thread->to) }}</td></tr>
            @if ($rg_list($thread->cc) !== '')<tr><th>Cc</th><td>{{ $rg_list($thread->cc) }}</td></tr>@endif
            @if ($rg_list($thread->bcc) !== '')<tr><th>Bcc</th><td>{{ $rg_list($thread->bcc) }}</td></tr>@endif
            <tr><th>{{ __('refreshglobal::messages.td_date') }}</th><td>{{ $rg_date($thread->created_at) }}</td></tr>
            <tr><th>{{ __('refreshglobal::messages.td_mailbox') }}</th><td>{{ $mailbox ? $mailbox->name.' <'.$mailbox->email.'>' : '' }}</td></tr>
            @if ($thread->message_id)<tr><th>Message-ID</th><td class="rg-td-mono">{{ $thread->message_id }}</td></tr>@endif
            @if ($tags['folder'] !== '')<tr><th>{{ __('refreshglobal::messages.td_folder') }}</th><td class="rg-td-mono">{{ $tags['folder'] }}</td></tr>@endif
            @if ($tags['tags'])
                <tr><th>{{ __('refreshglobal::messages.mail_tags') }}</th><td>@foreach ($tags['tags'] as $t)<span class="rg-mail-tag @if ($t === \Modules\RefreshGlobal\Services\MailTags::SPAM) rg-mail-tag-spam @endif">{{ $t }}</span> @endforeach</td></tr>
            @endif
            @if ($thread->opened_at)<tr><th>{{ __('refreshglobal::messages.td_opened') }}</th><td>{{ $rg_date($thread->opened_at) }}</td></tr>@endif
        </table>
        @if ($original_url)
            <p class="rg-td-original"><a href="#" class="rg-td-original-link" data-url="{{ $original_url }}">{{ __('refreshglobal::messages.td_original') }}</a></p>
        @endif
    </div>
    <div class="rg-td-pane" data-pane="raw">
        <p class="rg-td-help">{{ $thread->headers ? __('refreshglobal::messages.td_raw_help') : __('refreshglobal::messages.td_raw_no_headers') }}
            <button type="button" class="btn btn-default btn-xs rg-td-copy">{{ __('refreshglobal::messages.td_copy') }}</button></p>
        <pre class="rg-td-raw">{{ $rg_raw }}</pre>
    </div>
</div>
