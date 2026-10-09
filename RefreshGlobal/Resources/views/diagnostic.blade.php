{{-- Admin page: full compatibility report (same checks as php artisan refreshglobal:check). --}}
@extends('layouts.app')

@section('title', __('refreshglobal::messages.diagnostic'))

@section('body_attrs')@parent data-rg-page="diagnostic"@endsection

@section('stylesheets')
    @parent
    <link href="{{ asset(\Module::getPublicPath('refreshglobal').'/css/refreshglobal.css') }}" rel="stylesheet" type="text/css">
@endsection

@php
    $rg_M = '\Modules\RefreshGlobal\Services\Compatibility\Messages';
    $rg_state_class = ['ok' => 'success', 'warning' => 'info', 'degraded' => 'warning', 'blocking' => 'danger'][$report['state']];
@endphp

@section('content')
    <div class="container rg-diagnostic">
        <div class="section-heading">{{ __('refreshglobal::messages.diagnostic') }}</div>
        <div class="alert alert-{{ $rg_state_class }}">
            <strong>{{ __('refreshglobal::messages.diag_state') }} {{ __('refreshglobal::messages.state_'.$report['state']) }}</strong>
            <br>{{ __('refreshglobal::messages.state_help_'.$report['state']) }}
        </div>
        <p class="rg-muted">
            RefreshGlobal {{ $report['versions']['module'] }} · FreeScout {{ $report['versions']['freescout'] }} ·
            Refresh {{ $report['versions']['refresh'] ?: '—' }}@if ($report['versions']['refresh'] && !$report['versions']['refresh_active']) ({{ __('refreshglobal::messages.inactive') }})@endif ·
            PHP {{ $report['versions']['php'] }} · {{ $report['generated_at'] }}
        </p>
        <p>
            <a class="btn btn-default btn-sm" href="{{ route('refreshglobal.diagnostic', ['refresh' => 1]) }}">{{ __('refreshglobal::messages.run_again') }}</a>
            <a class="btn btn-link btn-sm" href="{{ route('refreshglobal.tickets') }}">{{ __('refreshglobal::messages.title') }}</a>
        </p>

        @include('partials/flash_messages')

        {{-- Navigation (Refresh only) --}}
        <div class="panel panel-default rg-update">
            <div class="panel-body">
                <h4 class="rg-update-title">{{ __('refreshglobal::messages.navigation') }}</h4>
                <p>{{ __('refreshglobal::messages.replace_tickets') }}
                    <span class="label {{ $replace_tickets ? 'label-success' : 'label-default' }}">{{ $replace_tickets ? __('refreshglobal::messages.on') : __('refreshglobal::messages.off') }}</span>
                </p>
                <p class="rg-muted">{{ __('refreshglobal::messages.replace_tickets_help') }}</p>
                @if ($refresh_active)
                    <form method="post" action="{{ route('refreshglobal.navigation') }}" class="rg-form">
                        {{ csrf_field() }}
                        <input type="hidden" name="replace_refresh_tickets" value="{{ $replace_tickets ? 0 : 1 }}">
                        <button type="submit" class="btn btn-sm {{ $replace_tickets ? 'btn-default' : 'btn-primary' }}">{{ $replace_tickets ? __('refreshglobal::messages.turn_off') : __('refreshglobal::messages.turn_on') }}</button>
                    </form>
                @else
                    <p class="rg-muted">[RG-REF-01] {{ __('refreshglobal::compat.ref_present.label') }}</p>
                @endif
            </div>
        </div>

        {{-- Automatic update --}}
        <div class="panel panel-default rg-update">
            <div class="panel-body">
                <h4 class="rg-update-title">{{ __('refreshglobal::messages.auto_update') }}
                    <span class="label {{ $update_enabled ? 'label-success' : 'label-default' }}">{{ $update_enabled ? __('refreshglobal::messages.on') : __('refreshglobal::messages.off') }}</span>
                </h4>
                <p class="rg-muted">{{ __('refreshglobal::messages.auto_update_help', ['time' => $update_time]) }}</p>
                <p>
                    {{ __('refreshglobal::messages.installed_version') }} <strong>{{ $current }}</strong>
                    @if (!empty($update['latest']))
                        · {{ __('refreshglobal::messages.latest_version') }} <strong>{{ $update['latest'] }}</strong>
                        @if (version_compare($update['latest'], $current, '>'))<span class="label label-info">{{ __('refreshglobal::messages.update_available') }}</span>@endif
                        <small class="rg-muted">({{ $update['last_check_at'] ?? '' }})</small>
                    @endif
                </p>
                @if (!empty($update['last_result']))
                    <p class="@if ($update['last_result'] === 'rolled_back' || $update['last_result'] === 'failed') text-danger @endif">
                        {{ __('refreshglobal::messages.last_update') }}
                        <strong>{{ __('refreshglobal::messages.update_result_'.$update['last_result']) }}</strong>
                        @if (!empty($update['last_to'])) ({{ $update['last_from'] ?? '' }} → {{ $update['last_to'] }})@endif
                        — {{ $update['last_run_at'] ?? '' }}
                        @if (!empty($update['last_reason']))<br><small>{{ $update['last_reason'] }}</small>@endif
                    </p>
                @endif
                <form method="post" action="{{ route('refreshglobal.auto_update') }}" class="rg-form">
                    {{ csrf_field() }}
                    <input type="hidden" name="enabled" value="{{ $update_enabled ? 0 : 1 }}">
                    <button type="submit" class="btn btn-sm {{ $update_enabled ? 'btn-default' : 'btn-primary' }}">{{ $update_enabled ? __('refreshglobal::messages.turn_off') : __('refreshglobal::messages.turn_on') }}</button>
                </form>
                <p class="rg-muted">{{ __('refreshglobal::messages.update_cli') }} <code>sudo -u www-data php artisan refreshglobal:update</code></p>
            </div>
        </div>

        @foreach ($rg_M::failed($report) as $r)
            @include('refreshglobal::partials.result', ['r' => $r, 'fallback' => false])
        @endforeach

        <div class="table-responsive">
            <table class="table table-striped table-condensed rg-diag-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>{{ __('refreshglobal::messages.diag_status') }}</th>
                        <th>{{ __('refreshglobal::messages.diag_severity') }}</th>
                        <th>{{ __('refreshglobal::messages.diag_check') }}</th>
                        <th>{{ __('refreshglobal::messages.expected') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['results'] as $r)
                        <tr class="rg-row-{{ $r['status'] }}">
                            <td><code>{{ $r['code'] }}</code></td>
                            <td>
                                @if ($r['status'] === 'ok')<span class="label label-success">OK</span>
                                @elseif ($r['status'] === 'skipped')<span class="label label-default">{{ __('refreshglobal::messages.skipped') }}</span>
                                @else<span class="label label-{{ $r['severity'] === 'blocking' ? 'danger' : ($r['severity'] === 'degraded' ? 'warning' : 'info') }}">{{ __('refreshglobal::messages.failed') }}</span>@endif
                            </td>
                            <td>{{ __('refreshglobal::messages.severity_'.$r['severity']) }}</td>
                            <td>{{ $rg_M::check($r) }}@if (!empty($r['details']))<br><small class="rg-muted">{{ $r['details'] }}</small>@endif</td>
                            <td><small>{{ $r['expected'] }}</small></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="rg-muted">{{ __('refreshglobal::messages.diag_cli') }} <code>php artisan refreshglobal:check</code></p>
    </div>
@endsection
