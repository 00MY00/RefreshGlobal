{{-- Statistics of the flows of Workflow Packs 3.20+ (Http/Controllers/FlowsController.php index(), Services/FlowChanges.php). --}}
@extends('layouts.app')

@section('title', __('refreshglobal::flows.stats_title'))

@section('body_attrs')@parent data-rg-page="flows"@endsection

@section('stylesheets')
    @parent
    <link href="{{ asset(\Module::getPublicPath('refreshglobal').'/css/refreshglobal.css') }}?v={{ \Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker::moduleVersion() }}" rel="stylesheet" type="text/css">
@endsection

@php
    $rg_types = array_merge(\Modules\RefreshGlobal\Services\FlowChanges::FIELDS, ['other']);
    $rg_date = function ($date) {
        try {
            return $date ? \App\User::dateFormat($date) : '';
        } catch (\Throwable $e) {
            return (string) $date;
        }
    };
    $rg_mb = function ($id) use ($mailboxes) {
        return isset($mailboxes[$id]) ? $mailboxes[$id]->name : '#'.$id;
    };
    $t = $stats['totals'];
    $rg_running = in_array($backfill['state'], ['requested', 'running'], true);
@endphp

@section('content')
    <div class="container rg-flows-page">
        @include('refreshglobal::partials.flows_header')

        <form method="GET" action="{{ route('refreshglobal.flows') }}" class="rg-flows-filters">
            <label>{{ __('refreshglobal::flows.days') }}
                <input type="number" name="days" min="1" max="{{ \Modules\RefreshGlobal\Services\FlowChanges::MAX_DAYS }}" value="{{ $filters['days'] }}" class="form-control" list="rg-flows-days" inputmode="numeric">
                <datalist id="rg-flows-days"><option value="1"><option value="7"><option value="30"><option value="90"><option value="365"></datalist>
            </label>
            <label>{{ __('refreshglobal::messages.col_mailbox') }}
                <select name="mb" class="form-control">
                    <option value="0">{{ __('refreshglobal::flows.all_mailboxes') }}</option>
                    @foreach ($mailboxes as $mb)
                        <option value="{{ $mb->id }}" @if ($filters['mailbox'] === (int) $mb->id) selected @endif>{{ $mb->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('refreshglobal::flows.flow') }}
                <select name="flow" class="form-control">
                    <option value="0">{{ __('refreshglobal::flows.all_flows') }}</option>
                    @foreach ($flows as $id => $name)
                        <option value="{{ $id }}" @if ($filters['flow'] === (int) $id) selected @endif>{{ $name !== '' ? $name : '#'.$id }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('refreshglobal::flows.origin') }}
                <select name="origin" class="form-control">
                    <option value="">{{ __('refreshglobal::flows.all_origins') }}</option>
                    @foreach (\Modules\RefreshGlobal\Services\FlowChanges::ORIGINS as $o)
                        <option value="{{ $o }}" @if ($filters['origin'] === $o) selected @endif>{{ __('refreshglobal::flows.origin_'.$o) }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="btn btn-primary">{{ __('refreshglobal::flows.apply') }}</button>
        </form>

        <p class="rg-muted rg-flows-since">
            @if ($stats['since'])
                {{ __('refreshglobal::flows.since', ['date' => $rg_date($stats['since'])]) }}
            @else
                {{ __('refreshglobal::flows.no_data') }}
            @endif
        </p>

        <div class="rg-flows-tiles">
            <div class="rg-flows-tile"><span class="rg-flows-tile-label">{{ __('refreshglobal::flows.tickets') }}</span><span class="rg-flows-tile-n">{{ $t['tickets'] }}</span></div>
            <div class="rg-flows-tile rg-flows-tile-closed"><span class="rg-flows-tile-label">{{ __('refreshglobal::flows.closed') }}</span><span class="rg-flows-tile-n">{{ $t['closed'] }}</span></div>
            <div class="rg-flows-tile"><span class="rg-flows-tile-label">{{ __('refreshglobal::flows.changes') }}</span><span class="rg-flows-tile-n">{{ $t['changes'] }}</span></div>
            <div class="rg-flows-tile"><span class="rg-flows-tile-label">{{ __('refreshglobal::flows.flows') }}</span><span class="rg-flows-tile-n">{{ count($stats['flows']) }}</span></div>
        </div>

        <h2 class="rg-flows-h2">{{ __('refreshglobal::flows.per_flow') }}</h2>
        @if (!$stats['flows'])
            <div class="alert alert-info">{{ __('refreshglobal::flows.empty') }}</div>
        @else
            <table class="table rg-flows-table rg-flows-table-flows">
                <thead>
                    <tr>
                        <th>{{ __('refreshglobal::flows.flow') }}</th>
                        <th class="text-right">{{ __('refreshglobal::flows.tickets') }}</th>
                        <th class="text-right">{{ __('refreshglobal::flows.closed') }}</th>
                        @foreach ($rg_types as $type)
                            <th class="text-right">{{ __('refreshglobal::flows.field_'.$type) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stats['flows'] as $row)
                        <tr>
                            <td class="rg-flows-name">
                                <a href="{{ route('refreshglobal.flows', ['days' => $filters['days'], 'mb' => $filters['mailbox'] ?: null, 'flow' => $row['flow_id']]) }}">{{ $row['name'] !== '' ? $row['name'] : '#'.$row['flow_id'] }}</a>
                                @if (!$row['exists'] && !$row['temporary'])<span class="rg-flows-badge rg-flows-badge-deleted">{{ __('refreshglobal::flows.deleted_flow') }}</span>@endif
                                @if ($row['temporary'])<span class="rg-flows-badge rg-flows-badge-temp">{{ __('refreshglobal::flows.temporary') }}</span>@endif
                            </td>
                            <td class="text-right" data-label="{{ __('refreshglobal::flows.tickets') }}">{{ $row['tickets'] }}</td>
                            <td class="text-right" data-label="{{ __('refreshglobal::flows.closed') }}">{{ $row['closed'] }}</td>
                            @foreach ($rg_types as $type)
                                <td class="text-right @if (!$row['types'][$type]) rg-flows-zero @endif" data-label="{{ __('refreshglobal::flows.field_'.$type) }}">{{ $row['types'][$type] }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($stats['mailboxes'])
            <h2 class="rg-flows-h2">{{ __('refreshglobal::flows.per_mailbox') }}</h2>
            <table class="table rg-flows-table">
                <thead><tr><th>{{ __('refreshglobal::messages.col_mailbox') }}</th><th class="text-right">{{ __('refreshglobal::flows.tickets') }}</th><th class="text-right">{{ __('refreshglobal::flows.closed') }}</th><th class="text-right">{{ __('refreshglobal::flows.changes') }}</th></tr></thead>
                <tbody>
                    @foreach ($stats['mailboxes'] as $id => $row)
                        <tr>
                            <td class="rg-flows-name"><a href="{{ route('refreshglobal.flows', ['days' => $filters['days'], 'mb' => $id, 'flow' => $filters['flow'] ?: null, 'origin' => $filters['origin'] ?: null]) }}">{{ $rg_mb($id) }}</a></td>
                            <td class="text-right" data-label="{{ __('refreshglobal::flows.tickets') }}">{{ $row['tickets'] }}</td>
                            <td class="text-right" data-label="{{ __('refreshglobal::flows.closed') }}">{{ $row['closed'] }}</td>
                            <td class="text-right" data-label="{{ __('refreshglobal::flows.changes') }}">{{ $row['changes'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($stats['origins'])
            <h2 class="rg-flows-h2">{{ __('refreshglobal::flows.per_origin') }}</h2>
            <table class="table rg-flows-table">
                <thead><tr><th>{{ __('refreshglobal::flows.origin') }}</th><th class="text-right">{{ __('refreshglobal::flows.tickets') }}</th><th class="text-right">{{ __('refreshglobal::flows.closed') }}</th><th class="text-right">{{ __('refreshglobal::flows.changes') }}</th></tr></thead>
                <tbody>
                    @foreach ($stats['origins'] as $origin => $row)
                        <tr>
                            <td class="rg-flows-name">{{ in_array($origin, \Modules\RefreshGlobal\Services\FlowChanges::ORIGINS, true) ? __('refreshglobal::flows.origin_'.$origin) : $origin }}</td>
                            <td class="text-right" data-label="{{ __('refreshglobal::flows.tickets') }}">{{ $row['tickets'] }}</td>
                            <td class="text-right" data-label="{{ __('refreshglobal::flows.closed') }}">{{ $row['closed'] }}</td>
                            <td class="text-right" data-label="{{ __('refreshglobal::flows.changes') }}">{{ $row['changes'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p class="rg-muted">{{ __('refreshglobal::flows.note') }}</p>

        @if ($is_admin)
            <div class="rg-flows-card rg-flows-backfill" @if ($rg_running) data-rg-reload="5" @endif>
                <h2 class="rg-flows-h2">{{ __('refreshglobal::flows.backfill_title') }}</h2>
                <p class="rg-muted">{{ __('refreshglobal::flows.backfill_help') }}</p>
                @if ($backfill['state'] !== '')
                    @php $rg_pct = $backfill['total'] ? min(100, (int) floor(100 * $backfill['done'] / $backfill['total'])) : ($backfill['state'] === 'done' ? 100 : 0); @endphp
                    <div class="rg-flows-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $rg_pct }}"><span style="width: {{ $rg_pct }}%"></span></div>
                    <p class="rg-flows-backfill-state">{{ __('refreshglobal::flows.backfill_'.(in_array($backfill['state'], ['requested', 'running', 'done', 'failed'], true) ? $backfill['state'] : 'done'), ['done' => (int) $backfill['done'], 'total' => (int) $backfill['total'], 'recorded' => (int) $backfill['recorded']]) }}</p>
                @endif
                <form method="POST" action="{{ route('refreshglobal.flows.backfill') }}" class="rg-flows-filters">
                    {{ csrf_field() }}
                    <label>{{ __('refreshglobal::flows.days') }}
                        <input type="number" name="days" min="1" max="{{ \Modules\RefreshGlobal\Services\FlowChanges::MAX_DAYS }}" value="{{ $filters['days'] }}" class="form-control" required inputmode="numeric">
                    </label>
                    <label>{{ __('refreshglobal::messages.col_mailbox') }}
                        <select name="mb" class="form-control">
                            <option value="0">{{ __('refreshglobal::flows.all_mailboxes') }}</option>
                            @foreach ($mailboxes as $mb)
                                <option value="{{ $mb->id }}" @if ($filters['mailbox'] === (int) $mb->id) selected @endif>{{ $mb->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn btn-default" @if ($rg_running) disabled @endif>{{ __('refreshglobal::flows.backfill_button') }}</button>
                </form>
            </div>
        @endif
    </div>
@endsection
