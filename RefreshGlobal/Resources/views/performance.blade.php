{{-- Performance page (administrators): sensors, machine, server, module parts (Http/Controllers/PerformanceController.php). --}}
@extends('layouts.app')

@section('title', __('refreshglobal::perf.title'))

@section('body_attrs')@parent data-rg-page="performance"@endsection

@section('stylesheets')
    @parent
    <link href="{{ asset(\Module::getPublicPath('refreshglobal').'/css/refreshglobal.css') }}?v={{ \Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker::moduleVersion() }}" rel="stylesheet" type="text/css">
@endsection

@php
    $rg_class = ['ok' => 'success', 'warn' => 'warning', 'bad' => 'danger', 'info' => 'default'];
    $rg_share = $summary['share'];
    $rg_kinds = ['sql', 'php', 'wait', 'browser'];
    $rg_total = 1;
    $rg_main = 'sql';
    if ($rg_share) {
        $rg_total = max(1, $rg_share['sql'] + $rg_share['php'] + $rg_share['wait'] + $rg_share['browser']);
        foreach ($rg_kinds as $k) {
            $rg_main = $rg_share[$k] > $rg_share[$rg_main] ? $k : $rg_main;
        }
    }
    $rg_ms = function ($v) {
        return $v === null ? '—' : $v.' ms';
    };
@endphp

@section('content')
    <div class="container rg-perf">
        <div class="section-heading">{{ __('refreshglobal::perf.title') }}</div>
        @include('partials/flash_messages')
        <p class="rg-buttons">
            <a class="btn btn-default btn-sm" href="{{ route('refreshglobal.diagnostic') }}"><i class="glyphicon glyphicon-chevron-left"></i> {{ __('refreshglobal::messages.diagnostic') }}</a>
            <a class="btn btn-default btn-sm" data-rg-busy href="{{ route('refreshglobal.performance') }}"><i class="glyphicon glyphicon-refresh"></i> {{ __('refreshglobal::messages.run_again') }}</a>
        </p>

        <h3 class="rg-views-h">{{ __('refreshglobal::perf.sensors') }}</h3>
        <p class="rg-muted">{{ __('refreshglobal::perf.sensors_help') }}</p>
        @if (!$available)
            <div class="alert alert-warning">[RG-DB-18] {{ __('refreshglobal::perf.unavailable') }}</div>
        @else
            <div class="rg-perf-actions">
                @if ($until)
                    <span class="label label-success rg-perf-on">{{ __('refreshglobal::perf.recording', ['time' => \App\User::dateFormat(date('Y-m-d H:i:s', $until), 'H:i')]) }}</span>
                @endif
                <form method="post" action="{{ route('refreshglobal.performance.record') }}" class="rg-inline">
                    {{ csrf_field() }}
                    @if ($until)
                        <input type="hidden" name="minutes" value="0">
                        <button type="submit" class="btn btn-default btn-sm">{{ __('refreshglobal::perf.stop') }}</button>
                    @else
                        <select name="minutes" class="form-control input-sm rg-perf-minutes" aria-label="{{ __('refreshglobal::perf.duration') }}">
                            @foreach ([15, 60, 240] as $m)
                                <option value="{{ $m }}" @if ($m === 60) selected @endif>{{ __('refreshglobal::perf.minutes', ['n' => $m]) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm rg-perf-start">{{ __('refreshglobal::perf.start') }}</button>
                    @endif
                </form>
                @if ($summary['n'])
                    <form method="post" action="{{ route('refreshglobal.performance.clear') }}" class="rg-inline">
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-link btn-sm">{{ __('refreshglobal::perf.clear') }}</button>
                    </form>
                @endif
            </div>
            @if ($rg_share)
                <div class="alert alert-{{ $rg_total < 800 ? 'success' : 'warning' }} rg-perf-verdict"><strong>{{ __('refreshglobal::perf.verdict_'.($rg_total < 800 ? 'fast' : $rg_main)) }}</strong></div>
                <table class="table table-condensed rg-perf-share">
                    <tbody>
                        @foreach ($rg_kinds as $k)
                            <tr>
                                <td>{{ __('refreshglobal::perf.share_'.$k) }}</td>
                                <td class="text-right"><strong>{{ $rg_share[$k] }} ms</strong></td>
                                <td class="text-right">{{ (int) round(100 * $rg_share[$k] / $rg_total) }} %</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="rg-muted">{{ __('refreshglobal::perf.too_few') }}</p>
            @endif
            @if ($summary['n'])
                <div class="table-responsive">
                    <table class="table table-striped table-condensed rg-perf-pages">
                        <thead>
                            <tr>
                                <th>{{ __('refreshglobal::perf.page') }}</th>
                                <th class="text-right">n</th>
                                @foreach (['median', 'p95', 'sql', 'sql_time', 'repeats', 'first_byte', 'complete'] as $c)
                                    <th class="text-right">{{ __('refreshglobal::perf.'.$c) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary['pages'] as $p)
                                <tr>
                                    <td><code>{{ $p['route'] }}</code></td>
                                    <td class="text-right">{{ $p['n'] }}</td>
                                    <td class="text-right"><strong>{{ $rg_ms($p['median']) }}</strong></td>
                                    <td class="text-right">{{ $rg_ms($p['p95']) }}</td>
                                    <td class="text-right">{{ $p['sql_n'] }}</td>
                                    <td class="text-right">{{ $rg_ms($p['sql_ms']) }}</td>
                                    <td class="text-right">{{ $p['repeats'] }}</td>
                                    <td class="text-right">{{ $rg_ms($p['b_ttfb']) }}</td>
                                    <td class="text-right">{{ $rg_ms($p['b_load']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <table class="table table-condensed rg-perf-modules">
                    <thead><tr><th>{{ __('refreshglobal::perf.module') }}</th><th class="text-right">{{ __('refreshglobal::perf.sql_per_request') }}</th><th class="text-right">{{ __('refreshglobal::perf.sql_time') }}</th></tr></thead>
                    <tbody>
                        @foreach ($summary['modules'] as $m)
                            <tr><td>{{ $m['module'] }}</td><td class="text-right">{{ $m['sql_n'] }}</td><td class="text-right">{{ $m['sql_ms'] }} ms</td></tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($summary['slow'])
                    <p><strong>{{ __('refreshglobal::perf.slow') }}</strong></p>
                    @foreach ($summary['slow'] as $q)
                        <p class="rg-perf-slow"><span class="label label-warning">{{ $q['max'] }} ms</span> {{ $q['module'] }} ×{{ $q['n'] }} <code>{{ $q['sql'] }}</code></p>
                    @endforeach
                @endif
            @endif
        @endif

        @foreach (['hardware' => $hardware, 'server' => $server] as $rg_block => $rg_items)
            <h3 class="rg-views-h">{{ __('refreshglobal::perf.'.$rg_block) }}</h3>
            <table class="table table-condensed rg-perf-items rg-perf-{{ $rg_block }}">
                <tbody>
                    @foreach ($rg_items as $i)
                        <tr data-key="{{ $i['key'] }}" data-level="{{ $i['level'] }}">
                            <td>{{ __('refreshglobal::perf.item_'.$i['key']) }}@if (in_array($i['level'], ['warn', 'bad'], true))<br><small class="rg-muted">{{ __('refreshglobal::perf.advice_'.$i['key']) }}</small>@endif</td>
                            <td><span class="label label-{{ $rg_class[$i['level']] }}">{{ $i['value'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach

        <h3 class="rg-views-h">{{ __('refreshglobal::perf.parts') }}</h3>
        <table class="table table-condensed rg-perf-items rg-perf-parts">
            <tbody>
                @foreach ($parts as $p)
                    <tr><td>{{ __('refreshglobal::perf.part_'.$p['key']) }}</td><td><span class="label label-{{ $rg_class[$p['level']] }}">{{ $rg_ms($p['ms']) }}</span></td></tr>
                @endforeach
            </tbody>
        </table>

        <h3 class="rg-views-h">{{ __('refreshglobal::perf.report') }}</h3>
        <p class="rg-muted">{{ __('refreshglobal::perf.report_help') }}</p>
        <textarea class="form-control rg-perf-report" rows="10" readonly aria-label="{{ __('refreshglobal::perf.report') }}">{{ $text }}</textarea>
    </div>
@endsection
