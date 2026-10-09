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
