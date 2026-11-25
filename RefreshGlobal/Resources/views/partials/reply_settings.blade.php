{{-- Replies (Manage › Settings › RefreshGlobal, 1.24.0): where a reply leads (Http/Middleware/AfterReply.php) and the texts it
     starts with (Services/ReplyStart.php, several since 1.25.0), with the guide of the variables. Buttons, previews and the
     list of texts: Public/js/shell.js. The order of the fields is the order of the texts. --}}
@php
    $rg_r = '\Modules\RefreshGlobal\Services\ReplyStart';
    $rg_texts = is_array($settings[$rg_r::TEXTS] ?? null) ? array_values($settings[$rg_r::TEXTS]) : [];
    $rg_boxes = \App\Mailbox::orderBy('name')->get(['id', 'name']);
    $rg_blank = ['name' => '', 'text' => '', 'where' => $rg_r::ALL, 'mailboxes' => [], 'lang' => ''];
@endphp
<h3 class="subheader" id="rg-replies">{{ __('refreshglobal::reply.title') }}</h3>
@include('refreshglobal::partials.switch', ['key' => $rg_s::REPLY_GOES_NEXT, 'label' => __('refreshglobal::reply.goes_next'),
    'help' => __('refreshglobal::reply.goes_next_help')])
<div class="form-group">
    <label for="rg-reply-history" class="col-sm-2 control-label">{{ __('refreshglobal::reply.history') }}</label>
    <div class="col-sm-6">
        <select id="rg-reply-history" name="settings[{{ $rg_s::REPLY_HISTORY }}]" class="form-control input-sized">
            @foreach ($rg_s::REPLY_HISTORY_VALUES as $rg_h)
                <option value="{{ $rg_h }}" @if ($rg_s::replyHistory() === $rg_h) selected @endif>{{ \App\Conversation::getEmailHistoryName($rg_h) }}</option>
            @endforeach
        </select>
        <p class="form-help">{{ __('refreshglobal::reply.history_help') }}</p>
    </div>
</div>
@include('refreshglobal::partials.switch', ['key' => $rg_r::ENABLED, 'label' => __('refreshglobal::reply.enabled'),
    'help' => __('refreshglobal::reply.enabled_help')])
@include('refreshglobal::partials.switch', ['key' => $rg_r::DETECT, 'label' => __('refreshglobal::reply.detect'),
    'help' => __('refreshglobal::reply.detect_help')])
<div class="form-group rg-reply-start">
    <label class="col-sm-2 control-label">{{ __('refreshglobal::reply.texts') }}</label>
    <div class="col-sm-9">
        <p class="form-help">{{ __('refreshglobal::reply.texts_help') }}</p>
        <p class="alert alert-warning" data-rg-reply-off role="status" hidden>{{ __('refreshglobal::reply.off', ['switch' => __('refreshglobal::reply.enabled')]) }}</p>
        {{-- posted alone when every text was removed --}}
        <input type="hidden" name="settings[{{ $rg_r::TEXTS }}]" value="">
        <div class="rg-reply-list" data-rg-reply-list data-switch="rg-sw-{{ md5($rg_r::ENABLED) }}" data-max="{{ $rg_r::MAX_TEXTS }}" data-sample="{{ json_encode($rg_r::SAMPLE) }}"
            data-texts="{{ json_encode(['unnamed' => __('refreshglobal::reply.unnamed'), 'all' => __('refreshglobal::reply.where_all'), 'except' => __('refreshglobal::reply.where_except'),
                'others' => __('refreshglobal::reply.lang_others'), 'nowhere' => __('refreshglobal::reply.nowhere'), 'none' => __('refreshglobal::reply.none')]) }}">
            @foreach ($rg_texts as $i => $row)
                @include('refreshglobal::partials.reply_text', ['i' => $i, 'row' => $row])
            @endforeach
        </div>
        <p class="rg-reply-none" data-rg-reply-none @if ($rg_texts) hidden @endif>{{ __('refreshglobal::reply.no_profile') }}</p>
        <template data-rg-reply-new>@include('refreshglobal::partials.reply_text', ['i' => '__i__', 'row' => $rg_blank])</template>
        <div class="rg-reply-foot">
            <button type="button" class="btn btn-default btn-sm" data-rg-reply-add>+ {{ __('refreshglobal::reply.add') }}</button>
            <button type="submit" class="btn btn-primary btn-sm" data-rg-reply-save>{{ __('refreshglobal::messages.save') }}</button>
            <span class="rg-reply-dirty" data-rg-reply-dirty role="status" hidden>{{ __('refreshglobal::reply.unsaved') }}</span>
        </div>
        <p class="rg-reply-preview-title"><strong>{{ __('refreshglobal::reply.by_mailbox') }}</strong></p>
        <p class="form-help">{{ __('refreshglobal::reply.by_mailbox_help') }}</p>
        <table class="table table-condensed rg-reply-used" data-rg-reply-used>
            <thead><tr><th>{{ __('refreshglobal::reply.mailbox') }}</th><th>{{ __('refreshglobal::reply.used') }}</th><th>{{ __('refreshglobal::reply.others') }}</th></tr></thead>
            <tbody>
                @foreach ($rg_boxes as $mb)
                    <tr data-rg-mailbox="{{ $mb->id }}"><td>{{ $mb->name }}</td><td></td><td></td></tr>
                @endforeach
            </tbody>
        </table>
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
    </div>
</div>
