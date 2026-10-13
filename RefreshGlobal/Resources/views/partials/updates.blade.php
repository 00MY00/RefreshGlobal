{{--
    Who updates RefreshGlobal (Services/Updates.php): the UpdateGuard module when it is active, otherwise FreeScout's
    Modules page, with UpdateGuard recommended. Same block on Manage › Settings › RefreshGlobal and on the diagnostic
    page. Parameter: small (bool: btn-sm buttons).
--}}
@php
    $rg_up = \Modules\RefreshGlobal\Services\Updates::info();
    $rg_btn = !empty($small) ? 'btn btn-default btn-sm' : 'btn btn-default';
@endphp
<div class="rg-updates" data-rg-updates="{{ $rg_up['manager'] }}">
    <p class="rg-update-line">{{ __('refreshglobal::messages.installed_version') }} <strong>{{ $rg_up['version'] }}</strong></p>
    @if ($rg_up['manager'] === 'updateguard')
        <p>
            <span class="label label-success">UpdateGuard</span>
            {{ __('refreshglobal::messages.updates_by_updateguard') }}
        </p>
        <p><a class="{{ $rg_btn }}" href="{{ $rg_up['url'] }}"><i class="glyphicon glyphicon-refresh"></i> {{ __('refreshglobal::messages.updates_open_updateguard') }}</a></p>
    @else
        <p>{{ __('refreshglobal::messages.updates_by_freescout') }}</p>
        @if ($rg_up['url'] !== '')
            <p><a class="{{ $rg_btn }}" href="{{ $rg_up['url'] }}"><i class="glyphicon glyphicon-th-large"></i> {{ __('refreshglobal::messages.updates_open_modules') }}</a></p>
        @endif
        <div class="alert alert-info rg-updates-advice">
            <strong>{{ __('refreshglobal::messages.updates_advice_title') }}</strong>
            {{ __('refreshglobal::messages.updates_advice') }}
            <a href="{{ $rg_up['recommend'] }}" target="_blank" rel="noopener noreferrer">{{ $rg_up['recommend'] }}</a>
        </div>
    @endif
</div>
