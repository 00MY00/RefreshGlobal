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
    $rg_s = '\Modules\RefreshGlobal\Services\Settings';
    $rg_need_refresh = $rg_refresh ? '' : __('refreshglobal::messages.refresh_required');
@endphp
<p class="margin-top">{{ __('refreshglobal::messages.settings_intro') }}</p>
<p>
    <a href="{{ route('refreshglobal.tickets') }}">{{ __('refreshglobal::messages.open_page') }}</a>
    · <a href="{{ route('refreshglobal.diagnostic') }}">{{ __('refreshglobal::messages.diagnostic') }}</a>
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
    @include('refreshglobal::partials.switch', ['key' => $rg_s::DELETE_PERMANENTLY, 'label' => __('refreshglobal::messages.delete_permanently'),
        'help' => __('refreshglobal::messages.delete_permanently_help')])

    <h3 class="subheader">{{ __('refreshglobal::messages.auto_update') }}</h3>
    @include('refreshglobal::partials.switch', ['key' => \Modules\RefreshGlobal\Services\Update\Updater::OPTION, 'label' => __('refreshglobal::messages.auto_update'),
        'help' => __('refreshglobal::messages.auto_update_help', ['time' => (string) config('refreshglobal.auto_update_time', '03:30')])])

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
            @if (!empty($rg_update['last_check_at']))
                <p class="form-help">{{ __('refreshglobal::messages.last_check', ['time' => \Carbon\Carbon::parse($rg_update['last_check_at'])->format('Y-m-d H:i')]) }}</p>
            @endif
            <form method="POST" action="{{ route('refreshglobal.check_update') }}" class="margin-bottom-10">
                {{ csrf_field() }}
                <input type="hidden" name="back" value="settings">
                <button type="submit" class="btn btn-default">{{ __('refreshglobal::messages.check_update') }}</button>
            </form>
            <p class="form-help">{{ __('refreshglobal::messages.check_update_help') }}</p>
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
