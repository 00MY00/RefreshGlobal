{{-- One profile (text a reply starts with) of partials/reply_settings.blade.php: its row, then its form (hidden by
     Public/js/shell.js until "Edit"). Parameters: i (its key), row (Services/ReplyStart::texts()). --}}
@php($rg_n = 'settings['.$rg_r::TEXTS.']['.$i.']')
<div class="rg-reply-item" data-rg-reply-item role="group">
    <div class="rg-reply-row">
        <div class="rg-reply-sum" data-rg-reply-toggle>
            <strong data-rg-reply-title>{{ $row['name'] !== '' ? $row['name'] : __('refreshglobal::reply.unnamed') }}</strong>
            <span class="rg-reply-line" data-rg-reply-line></span>
            <span class="rg-reply-chips" data-rg-reply-chips></span>
        </div>
        <div class="rg-reply-acts">
            <button type="button" class="btn btn-default btn-sm" data-rg-reply-edit aria-expanded="false">{{ __('refreshglobal::reply.edit') }}</button>
            <button type="button" class="btn btn-default btn-sm" data-rg-reply-up title="{{ __('refreshglobal::reply.up') }}" aria-label="{{ __('refreshglobal::reply.up') }}">↑</button>
            <button type="button" class="btn btn-default btn-sm" data-rg-reply-down title="{{ __('refreshglobal::reply.down') }}" aria-label="{{ __('refreshglobal::reply.down') }}">↓</button>
            <button type="button" class="btn btn-default btn-sm" data-rg-reply-remove title="{{ __('refreshglobal::reply.remove') }}" aria-label="{{ __('refreshglobal::reply.remove') }}">×</button>
        </div>
    </div>
    <div class="rg-reply-form" data-rg-reply-form>
        <label>{{ __('refreshglobal::reply.name') }}
            <input type="text" name="{{ $rg_n }}[name]" value="{{ $row['name'] }}" maxlength="{{ $rg_r::MAX_NAME }}" class="form-control" data-rg-reply-name></label>
        <label>{{ __('refreshglobal::reply.text') }}
            <textarea name="{{ $rg_n }}[text]" rows="3" maxlength="{{ $rg_r::MAX }}" class="form-control" data-rg-reply-text placeholder="{{ __('refreshglobal::reply.placeholder') }}">{{ $row['text'] }}</textarea></label>
        <p class="form-help">{{ __('refreshglobal::reply.text_help') }}</p>
        <div class="rg-reply-vars" role="group" aria-label="{{ __('refreshglobal::reply.variables') }}">
            @foreach ($rg_r::VARIABLES as $v)
                <button type="button" class="btn btn-default btn-xs" data-rg-var="{{ '$'.$v }}" title="{{ __('refreshglobal::reply.var_'.$v) }}">{{ '$'.$v }}</button>
            @endforeach
        </div>
        <p class="rg-reply-preview-title"><strong>{{ __('refreshglobal::reply.preview') }}</strong></p>
        <div class="rg-reply-preview" data-rg-reply-preview></div>
        <label>{{ __('refreshglobal::reply.lang') }}
            <select name="{{ $rg_n }}[lang]" class="form-control" data-rg-reply-lang>
                <option value="">{{ __('refreshglobal::reply.lang_any') }}</option>
                @foreach (\Modules\RefreshGlobal\Services\LanguageGuess::LANGUAGES as $code => $language)
                    <option value="{{ $code }}" @if ($row['lang'] === $code) selected @endif>{{ strtoupper($code) }} – {{ $language }}</option>
                @endforeach
            </select></label>
        <p class="form-help">{{ __('refreshglobal::reply.lang_help') }}</p>
        <label>{{ __('refreshglobal::reply.where') }}
            <select name="{{ $rg_n }}[where]" class="form-control" data-rg-reply-where>
                @foreach ([$rg_r::ALL, $rg_r::ONLY, $rg_r::EXCEPT] as $w)
                    <option value="{{ $w }}" @if ($row['where'] === $w) selected @endif>{{ __('refreshglobal::reply.where_'.$w) }}</option>
                @endforeach
            </select></label>
        <div class="rg-reply-boxes" data-rg-reply-boxes>
            @foreach ($rg_boxes as $mb)
                <label><input type="checkbox" name="{{ $rg_n }}[mailboxes][]" value="{{ $mb->id }}" @if (in_array((int) $mb->id, $row['mailboxes'], true)) checked @endif> <span>{{ $mb->name }}</span></label>
            @endforeach
        </div>
        <button type="submit" class="btn btn-primary rg-reply-save" data-rg-reply-save>{{ __('refreshglobal::messages.save') }}</button>
    </div>
</div>
