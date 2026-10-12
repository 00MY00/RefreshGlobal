{{-- Overview of the automatic folders of Workflow Packs (Http/Controllers/AutoFoldersController.php). --}}
@extends('layouts.app')

@section('title', __('refreshglobal::messages.folders_title'))

@section('body_attrs')@parent data-rg-page="folders"@endsection

@section('stylesheets')
    @parent
    <link href="{{ asset(\Module::getPublicPath('refreshglobal').'/css/refreshglobal.css') }}?v={{ \Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker::moduleVersion() }}" rel="stylesheet" type="text/css">
@endsection

@php
    $rg_list = function (array $params) { return route('refreshglobal.tickets', $params); };
    $rg_delay = function ($c) {
        $unit = in_array($c['metric'], ['i', 'h', 'd'], true) ? $c['metric'] : 'd';
        return __('refreshglobal::messages.delay_'.$unit, ['n' => $c['number']]);
    };
    $rg_date = function ($date) {
        if (!$date) {
            return ['', ''];
        }
        try {
            return [\App\User::dateDiffForHumans($date), \App\User::dateFormat($date)];
        } catch (\Throwable $e) {
            return [(string) $date, (string) $date];
        }
    };
@endphp

@section('content')
    <div class="container rg-folders-page">
        <div class="section-heading">{!! \Modules\RefreshGlobal\Services\AutoFolders::icon() !!} {{ __('refreshglobal::messages.folders_title') }}</div>
        <p class="rg-buttons">
            <a class="btn btn-default btn-sm" href="{{ route('refreshglobal.tickets') }}"><i class="glyphicon glyphicon-inbox"></i> {{ __('refreshglobal::messages.open_page') }}</a>
            @foreach ($wp_links as $link)
                <a class="btn btn-default btn-sm" href="{{ $link['url'] }}"><i class="glyphicon glyphicon-transfer"></i> {{ $link['label'] }}</a>
            @endforeach
        </p>

        @if (!$available)
            <div class="alert alert-info">{{ $installed ? __('refreshglobal::messages.folders_old_wp') : __('refreshglobal::messages.folders_no_wp') }}</div>
        @elseif (!$rows)
            <div class="alert alert-info">{{ __('refreshglobal::messages.folders_empty') }}</div>
        @else
            <p class="rg-muted">{{ __('refreshglobal::messages.folders_intro') }}</p>
            <div class="table-responsive">
                <table class="table table-striped rg-folders-table">
                    <thead>
                        <tr>
                            <th>{{ __('refreshglobal::messages.auto_folder') }}</th>
                            <th class="text-right">{{ __('refreshglobal::messages.folders_tickets') }}</th>
                            <th class="text-right">{{ __('refreshglobal::messages.folders_open') }}</th>
                            <th>{{ __('refreshglobal::messages.mailboxes') }}</th>
                            <th>{{ __('refreshglobal::messages.folders_last') }}</th>
                            <th>{{ __('refreshglobal::messages.folders_cleanup') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php [$rg_ago, $rg_full] = $rg_date($row['last']); @endphp
                            <tr>
                                <td><a href="{{ $rg_list(['wf' => $row['folder']]) }}">@if ($row['depth'])<span class="rg-depth">{!! str_repeat('&nbsp;&nbsp;', $row['depth']) !!}↳ </span>@endif{{ $row['label'] }}</a></td>
                                <td class="text-right"><a href="{{ $rg_list(['wf' => $row['folder']]) }}">{{ $row['total'] }}</a></td>
                                <td class="text-right">@if ($row['open'])<a href="{{ $rg_list(['wf' => $row['folder'], 'status' => [\App\Conversation::STATUS_ACTIVE, \App\Conversation::STATUS_PENDING]]) }}">{{ $row['open'] }}</a>@else 0 @endif</td>
                                <td>
                                    @foreach ($row['mailboxes'] as $mb_id => $n)
                                        <a class="rg-mailbox-badge rg-folder-mb" href="{{ $rg_list(['wf' => $row['folder'], 'mb' => [$mb_id]]) }}">{{ isset($mailboxes[$mb_id]) ? $mailboxes[$mb_id]->name : '#'.$mb_id }} · {{ $n }}</a>
                                    @endforeach
                                </td>
                                <td><span title="{{ $rg_full }}">{{ $rg_ago }}</span></td>
                                <td>
                                    @if ($row['cleanup'])
                                        {{ __('refreshglobal::messages.cleanup_'.($row['cleanup']['mode'] === 'delete' ? 'delete' : 'trash'), ['delay' => $rg_delay($row['cleanup'])]) }}
                                    @else
                                        <span class="rg-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="rg-muted">{{ __('refreshglobal::messages.folders_note') }}</p>
        @endif
    </div>
@endsection
