{{--
    Manage › Settings › RefreshGlobal (included by resources/views/settings/view.blade.php through the "settings.view"
    filter). The form posts to FreeScout's own settings route, which saves the values in its options table.
    Same markup as FreeScout's settings pages and Refresh's (Modules/Refresh/Resources/views/settings.blade.php).
--}}
@php
    $rg_update = \Modules\RefreshGlobal\Services\Update\Updater::status();
    $rg_current = \Modules\RefreshGlobal\Services\Update\Updater::currentVersion();
    $rg_requested = \Modules\RefreshGlobal\Services\Update\Updater::pendingRequest();
    $rg_refresh = \App\Module::isActive('refresh');
    $rg_k_replace = \Modules\RefreshGlobal\Services\Settings::REPLACE_REFRESH_TICKETS;
    $rg_k_auto = \Modules\RefreshGlobal\Services\Update\Updater::OPTION;
@endphp
<p class="margin-top">{{ __('refreshglobal::messages.settings_intro') }}</p>
<p>
    <a href="{{ route('refreshglobal.tickets') }}">{{ __('refreshglobal::messages.open_page') }}</a>
    · <a href="{{ route('refreshglobal.diagnostic') }}">{{ __('refreshglobal::messages.diagnostic') }}</a>
</p>

<form class="form-horizontal margin-top margin-bottom" method="POST" action="" autocomplete="off">
    {{ csrf_field() }}

    <h3 class="subheader">{{ __('refreshglobal::messages.navigation') }}</h3>
    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('refreshglobal::messages.menu') }}</label>
        <div class="col-sm-8">
            <input type="hidden" name="settings[{{ $rg_k_replace }}]" value="0">
            <label class="checkbox">
                <input type="checkbox" name="settings[{{ $rg_k_replace }}]" value="1" @if (!empty($settings[$rg_k_replace])) checked @endif @if (!$rg_refresh) disabled @endif>
                {{ __('refreshglobal::messages.replace_tickets') }}
            </label>
            <p class="form-help">{{ __('refreshglobal::messages.replace_tickets_help') }}@if (!$rg_refresh) {{ __('refreshglobal::messages.refresh_required') }}@endif</p>
        </div>
    </div>

    <h3 class="subheader">{{ __('refreshglobal::messages.auto_update') }}</h3>
    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('refreshglobal::messages.auto_update') }}</label>
        <div class="col-sm-8">
            <input type="hidden" name="settings[{{ $rg_k_auto }}]" value="0">
            <label class="checkbox">
                <input type="checkbox" name="settings[{{ $rg_k_auto }}]" value="1" @if (!empty($settings[$rg_k_auto])) checked @endif>
                {{ __('refreshglobal::messages.turn_on') }}
            </label>
            <p class="form-help">{{ __('refreshglobal::messages.auto_update_help', ['time' => (string) config('refreshglobal.auto_update_time', '03:30')]) }}</p>
        </div>
    </div>

    <div class="form-group">
        <div class="col-sm-6 col-sm-offset-2">
            <button type="submit" class="btn btn-primary">{{ __('refreshglobal::messages.save') }}</button>
        </div>
    </div>
</form>

<div class="form-horizontal margin-bottom">
    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('refreshglobal::messages.installed_version') }}</label>
        <div class="col-sm-8">
            <p class="form-control-static">
                <strong>{{ $rg_current }}</strong>
                @if (!empty($rg_update['latest']))
                    · {{ __('refreshglobal::messages.latest_version') }} <strong>{{ $rg_update['latest'] }}</strong>
                    @if (version_compare($rg_update['latest'], $rg_current, '>'))<span class="label label-info">{{ __('refreshglobal::messages.update_available') }}</span>@endif
                @endif
            </p>
            @if (!empty($rg_update['last_result']))
                <p class="form-help @if (in_array($rg_update['last_result'], ['rolled_back', 'failed'])) text-danger @endif">
                    {{ __('refreshglobal::messages.last_update') }}
                    {{ __('refreshglobal::messages.update_result_'.$rg_update['last_result']) }}
                    @if (!empty($rg_update['last_to']))({{ $rg_update['last_from'] ?? '' }} → {{ $rg_update['last_to'] }})@endif
                    — {{ $rg_update['last_run_at'] ?? '' }}
                    @if (!empty($rg_update['last_reason']))<br><small>{{ $rg_update['last_reason'] }}</small>@endif
                </p>
            @endif
            <form method="POST" action="{{ route('refreshglobal.update_now') }}">
                {{ csrf_field() }}
                <input type="hidden" name="back" value="settings">
                <button type="submit" class="btn btn-default" @if ($rg_requested !== '') disabled @endif>{{ __('refreshglobal::messages.update_now') }}</button>
            </form>
            <p class="form-help">
                @if ($rg_requested !== '')
                    {{ __('refreshglobal::messages.update_requested', ['time' => $rg_requested]) }}
                @else
                    {{ __('refreshglobal::messages.update_now_help') }}
                @endif
            </p>
        </div>
    </div>
</div>
