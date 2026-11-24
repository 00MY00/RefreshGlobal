{{-- Replies (Manage › Settings › RefreshGlobal, 1.24.0): where a reply leads (Http/Middleware/AfterReply.php) and the text it
     starts with (Services/ReplyStart.php), with the guide of the variables. Buttons and preview: Public/js/shell.js. --}}
@php
    $rg_r = '\Modules\RefreshGlobal\Services\ReplyStart';
    $rg_texts = is_array($settings[$rg_r::MAILBOXES] ?? null) ? $settings[$rg_r::MAILBOXES] : [];
@endphp
<h3 class="subheader" id="rg-replies">{{ __('refreshglobal::reply.title') }}</h3>
@include('refreshglobal::partials.switch', ['key' => $rg_s::REPLY_GOES_NEXT, 'label' => __('refreshglobal::reply.goes_next'),
    'help' => __('refreshglobal::reply.goes_next_help')])
@include('refreshglobal::partials.switch', ['key' => $rg_r::ENABLED, 'label' => __('refreshglobal::reply.enabled'),
    'help' => __('refreshglobal::reply.enabled_help')])
<div class="form-group rg-reply-start">
    <label for="rg-reply-text" class="col-sm-2 control-label">{{ __('refreshglobal::reply.text') }}</label>
    <div class="col-sm-6">
        <textarea id="rg-reply-text" name="settings[{{ $rg_r::TEXT }}]" rows="4" maxlength="{{ $rg_r::MAX }}" class="form-control" data-rg-reply-text
            data-sample="{{ json_encode($rg_r::SAMPLE) }}" placeholder="{{ __('refreshglobal::reply.placeholder') }}">{{ $settings[$rg_r::TEXT] ?? '' }}</textarea>
        <p class="form-help">{{ __('refreshglobal::reply.text_help') }}</p>
        <div class="rg-reply-vars" role="group" aria-label="{{ __('refreshglobal::reply.variables') }}">
            @foreach ($rg_r::VARIABLES as $v)
                <button type="button" class="btn btn-default btn-xs" data-rg-var="{{ '$'.$v }}" title="{{ __('refreshglobal::reply.var_'.$v) }}">{{ '$'.$v }}</button>
            @endforeach
        </div>
        <p class="rg-reply-preview-title"><strong>{{ __('refreshglobal::reply.preview') }}</strong></p>
        <div class="rg-reply-preview" data-rg-reply-preview></div>
        <details class="rg-details rg-reply-guide">
            <summary>{{ __('refreshglobal::reply.guide') }}</summary>
            <p>{{ __('refreshglobal::reply.guide_intro') }}</p>
            <table class="table table-condensed">
                <thead><tr><th>{{ __('refreshglobal::reply.variable') }}</th><th>{{ __('refreshglobal::reply.meaning') }}</th><th>{{ __('refreshglobal::reply.example') }}</th></tr></thead>
                <tbody>
                    @foreach ($rg_r::VARIABLES as $v)
                        <tr><td><code>{{ '$'.$v }}</code></td><td>{{ __('refreshglobal::reply.var_'.$v) }}</td><td>{{ $rg_r::SAMPLE[$v] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <p>{{ __('refreshglobal::reply.guide_empty') }}</p>
            <p>{{ __('refreshglobal::reply.guide_fallback') }}<br><code>{{ __('refreshglobal::reply.fallback_example') }}</code></p>
            <p>{{ __('refreshglobal::reply.guide_rules') }}</p>
        </details>
        <details class="rg-details rg-reply-mailboxes" @if (array_filter($rg_texts)) open @endif>
            <summary>{{ __('refreshglobal::reply.per_mailbox') }}</summary>
            <p class="form-help">{{ __('refreshglobal::reply.per_mailbox_help') }}</p>
            @foreach (\App\Mailbox::orderBy('name')->get(['id', 'name']) as $mb)
                <label for="rg-reply-mb-{{ $mb->id }}" class="rg-label">{{ $mb->name }}</label>
                <textarea id="rg-reply-mb-{{ $mb->id }}" name="settings[{{ $rg_r::MAILBOXES }}][{{ $mb->id }}]" rows="2" maxlength="{{ $rg_r::MAX }}" class="form-control" data-rg-reply-text>{{ $rg_texts[$mb->id] ?? '' }}</textarea>
            @endforeach
        </details>
    </div>
</div>
