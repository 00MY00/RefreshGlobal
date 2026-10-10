{{--
    Manage › Settings › RefreshGlobal (included by resources/views/settings/view.blade.php through the "settings.view"
    filter). The form posts to FreeScout's own settings route, which saves the values in its options table.
    Same markup as FreeScout's settings pages and Refresh's (Modules/Refresh/Resources/views/settings.blade.php).
--}}
@php
    $rg_refresh = \App\Module::isActive('refresh');
    $rg_s = '\Modules\RefreshGlobal\Services\Settings';
    $rg_need_refresh = $rg_refresh ? '' : __('refreshglobal::messages.refresh_required');
    $rg_trash = \Modules\RefreshGlobal\Services\Trash::count(auth()->user());
@endphp
<p class="margin-top">{{ __('refreshglobal::messages.settings_intro') }}</p>
<p class="rg-buttons">
    <a class="btn btn-default" href="{{ route('refreshglobal.tickets') }}"><i class="glyphicon glyphicon-inbox"></i> {{ __('refreshglobal::messages.open_page') }}</a>
    <a class="btn btn-default" href="{{ route('refreshglobal.diagnostic') }}"><i class="glyphicon glyphicon-check"></i> {{ __('refreshglobal::messages.diagnostic') }}</a>
</p>
<p class="form-help">
    {{ __('refreshglobal::messages.language_settings_info', ['language' => \Modules\RefreshGlobal\Http\Controllers\LanguageController::name(app()->getLocale())]) }}
</p>
<p class="rg-buttons">
    <a class="btn btn-default btn-sm" href="{{ route('users.profile', ['id' => auth()->id()]) }}"><i class="glyphicon glyphicon-globe"></i> {{ __('refreshglobal::messages.language_profile_link') }}</a>
    <a class="btn btn-default btn-sm" href="{{ route('settings', ['section' => 'general']) }}"><i class="glyphicon glyphicon-cog"></i> {{ __('refreshglobal::messages.language_default_link') }}</a>
</p>

<form class="form-horizontal margin-top margin-bottom" method="POST" action="" autocomplete="off">
    {{ csrf_field() }}

    <h3 class="subheader">{{ __('refreshglobal::messages.navigation') }}</h3>
    @include('refreshglobal::partials.switch', ['key' => $rg_s::REPLACE_REFRESH_TICKETS, 'label' => __('refreshglobal::messages.replace_tickets_label'),
        'help' => __('refreshglobal::messages.replace_tickets').'. '.__('refreshglobal::messages.replace_tickets_help'), 'extra_help' => $rg_need_refresh, 'disabled' => !$rg_refresh])
    @include('refreshglobal::partials.switch', ['key' => $rg_s::GLOBAL_DASHBOARD, 'label' => __('refreshglobal::messages.global_dashboard'),
        'help' => __('refreshglobal::messages.global_dashboard_help'), 'extra_help' => $rg_need_refresh, 'disabled' => !$rg_refresh])
    @include('refreshglobal::partials.switch', ['key' => $rg_s::SHOW_MAILBOX, 'label' => __('refreshglobal::messages.show_mailbox'),
        'help' => __('refreshglobal::messages.show_mailbox_help')])

    <h3 class="subheader">{{ __('refreshglobal::messages.ticket_deletion') }}</h3>
    @include('refreshglobal::partials.switch', ['key' => $rg_s::DELETE_GOES_NEXT, 'label' => __('refreshglobal::messages.delete_goes_next'),
        'help' => __('refreshglobal::messages.delete_goes_next_help')])
    @include('refreshglobal::partials.switch', ['key' => $rg_s::KEEP_POSITION, 'label' => __('refreshglobal::messages.keep_position'),
        'help' => __('refreshglobal::messages.keep_position_help')])
    @include('refreshglobal::partials.switch', ['key' => $rg_s::DELETE_PERMANENTLY, 'label' => __('refreshglobal::messages.delete_permanently'),
        'help' => __('refreshglobal::messages.delete_permanently_help')])

    <h3 class="subheader">{{ __('refreshglobal::messages.trash') }}</h3>
    <div class="form-group">
        <label for="rg-trash-days" class="col-sm-2 control-label">{{ __('refreshglobal::messages.trash_auto') }}</label>
        <div class="col-sm-6">
            <div class="input-group" style="max-width: 220px;">
                <input type="number" id="rg-trash-days" name="settings[{{ $rg_s::TRASH_AUTO_DAYS }}]" value="{{ (int) ($settings[$rg_s::TRASH_AUTO_DAYS] ?? 0) }}" min="0" max="{{ $rg_s::TRASH_AUTO_DAYS_MAX }}" step="1" class="form-control">
                <span class="input-group-addon">{{ __('refreshglobal::messages.trash_days') }}</span>
            </div>
            <p class="form-help">{{ __('refreshglobal::messages.trash_auto_help', ['time' => (string) config('refreshglobal.trash_auto_time', '03:45')]) }}</p>
        </div>
    </div>
    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('refreshglobal::messages.trash_now') }}</label>
        <div class="col-sm-6">
            {{-- submits the separate form below (forms can not be nested) --}}
            <button type="submit" form="rg-trash-form" class="btn btn-danger" @if (!$rg_trash) disabled @endif>
                <i class="glyphicon glyphicon-trash"></i> {{ __('refreshglobal::messages.trash_empty_button', ['count' => $rg_trash]) }}
            </button>
            <p class="form-help">{{ __('refreshglobal::messages.trash_empty_help') }}</p>
        </div>
    </div>

    <h3 class="subheader">{{ __('refreshglobal::messages.auto_update') }}</h3>
    @include('refreshglobal::partials.switch', ['key' => \Modules\RefreshGlobal\Services\Update\Updater::OPTION, 'label' => __('refreshglobal::messages.auto_update'),
        'help' => __('refreshglobal::messages.auto_update_help', ['time' => (string) config('refreshglobal.auto_update_time', '03:30')])])

    <div class="form-group">
        <div class="col-sm-6 col-sm-offset-2">
            <button type="submit" class="btn btn-primary">{{ __('refreshglobal::messages.save') }}</button>
        </div>
    </div>
</form>

{{-- "Empty the trash now" (button above, in the settings form); confirmation asked by Public/js/shell.js --}}
<form id="rg-trash-form" method="POST" action="{{ route('refreshglobal.trash.empty') }}" data-rg-confirm="{{ __('refreshglobal::messages.trash_empty_confirm', ['count' => $rg_trash]) }}">
    {{ csrf_field() }}
    <input type="hidden" name="back" value="settings">
</form>

<div class="form-horizontal margin-bottom">
    <div class="form-group">
        <label class="col-sm-2 control-label">RefreshGlobal</label>
        <div class="col-sm-8 form-control-static">
            @include('refreshglobal::partials.update_state', ['back' => 'settings', 'small' => false])
        </div>
    </div>
</div>
