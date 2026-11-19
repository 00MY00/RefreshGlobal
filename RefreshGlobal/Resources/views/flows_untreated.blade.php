{{-- E-mails no flow of Workflow Packs 3.20+ has acted on, temporary flow (Http/Controllers/FlowsController.php, Services/FlowTemp.php). --}}
@extends('layouts.app')

@section('title', __('refreshglobal::flows.untreated_title'))

@section('body_attrs')@parent data-rg-page="flows"@endsection

@section('stylesheets')
    @parent
    <link href="{{ asset(\Module::getPublicPath('refreshglobal').'/css/refreshglobal.css') }}?v={{ \Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker::moduleVersion() }}" rel="stylesheet" type="text/css">
@endsection

@php
    $rg_date = function ($date) {
        try {
            return $date ? \App\User::dateFormat($date) : '';
        } catch (\Throwable $e) {
            return (string) $date;
        }
    };
    $rg_error = session('rg_flow_error');
    $rg_error = in_array($rg_error, \Modules\RefreshGlobal\Services\FlowTemp::ERRORS, true) ? $rg_error : null;
    $rg_edit = \Modules\RefreshGlobal\Services\WorkflowPacksLink::safeUrl(session('rg_flow_edit'));
    $rg_n = count($list['ids']);
    $rg_active = false;
    foreach ($temps as $rg_t) {
        $rg_active = $rg_active || in_array($rg_t['state'], ['running', 'waiting'], true);
    }
@endphp

@section('content')
    <div class="container rg-flows-page" @if ($rg_active) data-rg-reload="15" @endif>
        @include('refreshglobal::partials.flows_header')

        @if ($rg_error)
            <div class="alert alert-danger rg-flows-error" data-error="{{ $rg_error }}">
                {{ __('refreshglobal::flows.error_'.$rg_error, ['max' => $max]) }}
                @if ($rg_edit !== '') <a href="{{ $rg_edit }}">{{ __('refreshglobal::flows.open_draft') }}</a>@endif
            </div>
        @endif

        <form method="GET" action="{{ route('refreshglobal.flows.untreated') }}" class="rg-flows-filters">
            <label>{{ __('refreshglobal::messages.col_mailbox') }}
                <select name="mb" class="form-control">
                    <option value="0">{{ __('refreshglobal::flows.all_mailboxes') }}</option>
                    @foreach ($mailboxes as $mb)
                        <option value="{{ $mb->id }}" @if ($filters['mailbox'] === (int) $mb->id) selected @endif>{{ $mb->name }}</option>
                    @endforeach
                </select>
            </label>
            <fieldset class="rg-flows-statuses">
                <legend>{{ __('refreshglobal::flows.statuses') }}</legend>
                @foreach (\Modules\RefreshGlobal\Services\FlowTemp::STATUSES as $s)
                    <label class="rg-flows-check"><input type="checkbox" name="statuses[]" value="{{ $s }}" @if (in_array($s, $filters['statuses'], true)) checked @endif> {{ __('refreshglobal::flows.status_'.$s) }}</label>
                @endforeach
            </fieldset>
            <label>{{ __('refreshglobal::flows.period') }}
                <input type="number" name="period" min="0" max="{{ \Modules\RefreshGlobal\Services\FlowChanges::MAX_DAYS }}" value="{{ $filters['period'] }}" class="form-control" inputmode="numeric">
            </label>
            <button type="submit" class="btn btn-primary">{{ __('refreshglobal::flows.apply') }}</button>
        </form>
        <p class="rg-muted">{{ __('refreshglobal::flows.untreated_help') }}</p>

        @if (!$list['ok'])
            <div class="alert alert-warning">{{ __('refreshglobal::flows.no_answer') }}</div>
        @else
            <p class="rg-flows-total"><strong>{{ __('refreshglobal::flows.untreated_total', ['count' => $list['total']]) }}</strong>
                @if ($list['truncated']) <span class="rg-muted">{{ __('refreshglobal::flows.untreated_truncated', ['max' => $max]) }}</span>@endif
            </p>

            @if ($is_admin)
                <div class="rg-flows-card rg-flows-temp">
                    <h2 class="rg-flows-h2">{{ __('refreshglobal::flows.temp_title') }}</h2>
                    <p class="rg-muted">{{ __('refreshglobal::flows.temp_help') }}</p>
                    @if ($list['truncated'])
                        <div class="alert alert-warning">{{ __('refreshglobal::flows.error_too_many_tickets', ['max' => $max]) }}</div>
                    @elseif ($rg_n)
                        <form method="POST" action="{{ route('refreshglobal.flows.temp') }}" class="rg-flows-filters">
                            {{ csrf_field() }}
                            <input type="hidden" name="mb" value="{{ $filters['mailbox'] }}">
                            <input type="hidden" name="period" value="{{ $filters['period'] }}">
                            @foreach ($filters['statuses'] as $s)
                                <input type="hidden" name="statuses[]" value="{{ $s }}">
                            @endforeach
                            <label>{{ __('refreshglobal::flows.temp_name') }}
                                <input type="text" name="name" maxlength="100" class="form-control" placeholder="{{ __('refreshglobal::flows.temp_default_name', ['date' => date('Y-m-d')]) }}">
                            </label>
                            <label>{{ __('refreshglobal::flows.temp_from') }}
                                <select name="from_flow" class="form-control">
                                    <option value="0">{{ __('refreshglobal::flows.temp_empty') }}</option>
                                    @foreach ($flows as $id => $name)
                                        <option value="{{ $id }}">{{ $name !== '' ? $name : '#'.$id }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="submit" class="btn btn-primary rg-flows-temp-button">{{ __('refreshglobal::flows.temp_button', ['count' => $rg_n]) }}</button>
                        </form>
                    @endif
                </div>
            @elseif ($rg_n)
                <p class="rg-muted">{{ __('refreshglobal::flows.temp_admins_only') }}</p>
            @endif

            @if ($tickets->count())
                <table class="table rg-flows-table rg-flows-tickets">
                    <thead><tr><th>#</th><th>{{ __('refreshglobal::flows.subject') }}</th><th>{{ __('refreshglobal::messages.col_mailbox') }}</th><th>{{ __('refreshglobal::flows.field_status') }}</th><th>{{ __('refreshglobal::flows.created') }}</th></tr></thead>
                    <tbody>
                        @foreach ($tickets as $c)
                            <tr>
                                <td class="rg-flows-num"><a href="{{ $c->url() }}">#{{ $c->number }}</a></td>
                                <td class="rg-flows-name"><a href="{{ $c->url() }}">{{ $c->subject !== '' && $c->subject !== null ? $c->subject : '—' }}</a></td>
                                <td data-label="{{ __('refreshglobal::messages.col_mailbox') }}">{{ isset($mailboxes[$c->mailbox_id]) ? $mailboxes[$c->mailbox_id]->name : '' }}</td>
                                <td data-label="{{ __('refreshglobal::flows.field_status') }}">{{ \App\Conversation::statusCodeToName($c->status) }}</td>
                                <td data-label="{{ __('refreshglobal::flows.created') }}">{{ $rg_date($c->created_at) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($rg_n > $tickets->count())
                    <p class="rg-muted">{{ __('refreshglobal::flows.shown', ['shown' => $tickets->count(), 'count' => $rg_n]) }}</p>
                @endif
            @endif
        @endif

        @if ($is_admin && $temps)
            <h2 class="rg-flows-h2">{{ __('refreshglobal::flows.temp_list') }}</h2>
            <table class="table rg-flows-table rg-flows-temps">
                <thead><tr><th>{{ __('refreshglobal::flows.flow') }}</th><th class="text-right">{{ __('refreshglobal::flows.tickets') }}</th><th>{{ __('refreshglobal::flows.state') }}</th><th>{{ __('refreshglobal::flows.created') }}</th></tr></thead>
                <tbody>
                    @foreach ($temps as $tf)
                        <tr>
                            <td class="rg-flows-name">@if ($tf['edit_url'] !== '')<a href="{{ $tf['edit_url'] }}">{{ $tf['name'] }}</a>@else{{ $tf['name'] }}@endif</td>
                            <td class="text-right" data-label="{{ __('refreshglobal::flows.tickets') }}">{{ $tf['handled'] !== null ? $tf['handled'].' / ' : '' }}{{ $tf['tickets'] }}</td>
                            <td data-label="{{ __('refreshglobal::flows.state') }}">
                                {{ __('refreshglobal::flows.state_'.(in_array($tf['state'], \Modules\RefreshGlobal\Services\FlowTemp::STATES, true) ? $tf['state'] : 'draft')) }}
                                @if ($tf['state'] === 'running' && $tf['total']) ({{ $tf['done'] }} / {{ $tf['total'] }})@endif
                                @if ($tf['reason'] === 'expired') · {{ __('refreshglobal::flows.reason_expired') }}@endif
                            </td>
                            <td data-label="{{ __('refreshglobal::flows.created') }}">{{ $rg_date($tf['created_at']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
