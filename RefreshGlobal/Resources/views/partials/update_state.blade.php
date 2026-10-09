{{--
    Version and update state, same block on Manage › Settings › RefreshGlobal and on the diagnostic page.
    States (Updater::state()): running | requested | available | up_to_date | unknown.
    - up to date (e.g. right after a successful update): "Up to date" only, no "latest version" to avoid confusion;
    - requested / running: said clearly, buttons disabled, the page reloads itself until it is over.
    Parameters: back ('settings' or ''), small (bool: btn-sm buttons).
--}}
@php
    $rg_U = '\Modules\RefreshGlobal\Services\Update\Updater';
    $rg_status = $rg_U::status();
    $rg_state = $rg_U::state();
    $rg_installed = $rg_U::currentVersion();
    $rg_requested_at = $rg_U::pendingRequest();
    $rg_busy = in_array($rg_state, ['running', 'requested'], true);
    $rg_btn = !empty($small) ? 'btn btn-default btn-sm' : 'btn btn-default';
    $rg_time = function ($iso) {
        try {
            return $iso ? \Carbon\Carbon::parse($iso)->format('Y-m-d H:i') : '';
        } catch (\Exception $e) {
            return (string) $iso;
        }
    };
@endphp
<div class="rg-update-state" data-rg-update-state="{{ $rg_state }}">
    <p class="rg-update-line">
        {{ __('refreshglobal::messages.installed_version') }} <strong>{{ $rg_installed }}</strong>
        @if ($rg_state === 'running')
            <span class="label label-warning">{{ __('refreshglobal::messages.update_state_running', ['version' => $rg_status['running_to'] ?? '']) }}</span>
        @elseif ($rg_state === 'requested')
            <span class="label label-warning">{{ __('refreshglobal::messages.update_state_requested') }}</span>
        @elseif ($rg_state === 'available')
            · {{ __('refreshglobal::messages.update_new_version') }} <strong>{{ $rg_status['latest'] }}</strong>@if (($rg_status['source'] ?? '') === 'branch') <small>{{ __('refreshglobal::messages.update_source_branch') }}</small>@endif
            <span class="label label-info">{{ __('refreshglobal::messages.update_available') }}</span>
        @elseif ($rg_state === 'up_to_date')
            <span class="label label-success">{{ __('refreshglobal::messages.update_state_up_to_date') }}</span>
        @endif
        @if (!$rg_busy && !empty($rg_status['last_check_at']))
            <small class="rg-muted">({{ __('refreshglobal::messages.last_check', ['time' => $rg_time($rg_status['last_check_at'])]) }})</small>
        @endif
    </p>

    @if ($rg_busy)
        <p class="text-warning">
            {{ $rg_state === 'running'
                ? __('refreshglobal::messages.update_state_running_help')
                : __('refreshglobal::messages.update_requested', ['time' => $rg_time($rg_requested_at)]) }}
            {{ __('refreshglobal::messages.update_auto_reload') }}
        </p>
        {{-- reload until the update is over (state shown from the server on every load) --}}
        <script {!! \Helper::cspNonceAttr() !!}>setTimeout(function () { window.location.reload(); }, 10000);</script>
    @elseif (!empty($rg_status['last_result']))
        <p class="@if (in_array($rg_status['last_result'], ['rolled_back', 'failed'])) text-danger @else rg-muted @endif">
            {{ __('refreshglobal::messages.last_update') }}
            <strong>{{ __('refreshglobal::messages.update_result_'.$rg_status['last_result']) }}</strong>
            @if (!empty($rg_status['last_to']) && ($rg_status['last_from'] ?? '') !== $rg_status['last_to'])({{ $rg_status['last_from'] ?? '' }} → {{ $rg_status['last_to'] }})@endif
            — {{ $rg_time($rg_status['last_run_at'] ?? '') }}
            @if (!empty($rg_status['last_reason']))<br><small>{{ $rg_status['last_reason'] }}</small>@endif
        </p>
    @endif

    {{-- one form, two buttons side by side (each posts to its own route through "formaction"); works on FreeScout's
         settings page too, where the module's stylesheet is not loaded --}}
    <form method="POST" action="{{ route('refreshglobal.check_update') }}" class="margin-bottom-10">
        {{ csrf_field() }}
        @if (!empty($back))<input type="hidden" name="back" value="{{ $back }}">@endif
        <button type="submit" class="{{ $rg_btn }}" @if ($rg_busy) disabled @endif><i class="glyphicon glyphicon-search"></i> {{ __('refreshglobal::messages.check_update') }}</button>
        &nbsp;
        <button type="submit" formaction="{{ route('refreshglobal.update_now') }}" class="{{ $rg_state === 'available' ? str_replace('btn-default', 'btn-primary', $rg_btn) : $rg_btn }}" @if ($rg_busy) disabled @endif><i class="glyphicon glyphicon-download-alt"></i> {{ __('refreshglobal::messages.update_now') }}</button>
    </form>
    <p class="form-help rg-muted">{{ __('refreshglobal::messages.check_update_help') }} {{ __('refreshglobal::messages.update_now_help') }}</p>
</div>
