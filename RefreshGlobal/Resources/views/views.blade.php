{{--
    Management page of the saved views (Http/Controllers/SavedViewsController.php@index): name, owner, visibility,
    shared with, dates, actions (open, edit / share, duplicate, pin, my default, order, export, delete), import; the
    audit of the shared views for administrators. A table on a computer, one card per view on a phone (CSS).
--}}
@extends('layouts.app')

@section('title', __('refreshglobal::messages.views_manage_title'))

@section('body_attrs')@parent data-rg-page="views"@endsection

@section('stylesheets')
    @parent
    <link href="{{ asset(\Module::getPublicPath('refreshglobal').'/css/refreshglobal.css') }}?v={{ \Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker::moduleVersion() }}" rel="stylesheet" type="text/css">
@endsection

@php
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
    $rg_sections = ['mine' => __('refreshglobal::messages.my_views'), 'shared' => __('refreshglobal::messages.views_shared'), 'global' => __('refreshglobal::messages.views_global')];
@endphp

@section('content')
    <div class="container rg-views-page">
        <div class="alerts">@include('partials/flash_messages')</div>
        <div class="section-heading"><i class="glyphicon glyphicon-filter"></i> {{ __('refreshglobal::messages.views_manage_title') }}</div>
        <p class="rg-buttons">
            <a class="btn btn-default btn-sm" href="{{ route('refreshglobal.tickets') }}"><i class="glyphicon glyphicon-inbox"></i> {{ __('refreshglobal::messages.open_page') }}</a>
        </p>
        <p class="rg-muted">{{ __('refreshglobal::messages.views_manage_help') }}</p>

        @if (!$available)
            <div class="alert alert-warning">[RG-DB-05] {{ __('refreshglobal::messages.views_unavailable') }}</div>
        @else
            @foreach ($rg_sections as $rg_key => $rg_title)
                @php $rg_rows = array_values(array_filter($rows, function ($r) use ($rg_key) { return $r['section'] === $rg_key; })); @endphp
                <h3 class="rg-views-h">{{ $rg_title }} <small>{{ count($rg_rows) }}</small></h3>
                @if (!$rg_rows)
                    <p class="rg-muted">{{ __('refreshglobal::messages.views_none') }}</p>
                    @continue
                @endif
                <table class="table rg-views-table">
                    <thead>
                        <tr>
                            <th>{{ __('refreshglobal::messages.view_name') }}</th>
                            <th>{{ __('refreshglobal::messages.view_owner') }}</th>
                            <th>{{ __('refreshglobal::messages.view_visibility') }}</th>
                            <th>{{ __('refreshglobal::messages.view_users') }}</th>
                            <th>{{ __('refreshglobal::messages.view_updated') }}</th>
                            <th>{{ __('refreshglobal::messages.view_created') }}</th>
                            <th class="rg-views-actions-h">{{ __('refreshglobal::messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rg_rows as $r)
                            @php $rg_u = $rg_date($r['updated_at']); $rg_c = $rg_date($r['created_at']); @endphp
                            <tr class="rg-view-row" id="rg-view-{{ $r['id'] }}">
                                <td data-label="{{ __('refreshglobal::messages.view_name') }}" class="rg-view-cell-name">
                                    <a href="{{ route('refreshglobal.tickets', ['view' => $r['id']]) }}">{{ $r['name'] }}</a>
                                    @if ($r['pinned'])<i class="glyphicon glyphicon-pushpin rg-pin-mark" title="{{ __('refreshglobal::messages.views_pinned') }}"></i>@endif
                                    @if ($r['my_default'])<span class="rg-default-mark" title="{{ __('refreshglobal::messages.default_view') }}">★</span>@endif
                                    @if ($r['default_scope'] !== '')<span class="rg-badge" title="{{ __('refreshglobal::messages.view_default_for') }}">{{ $r['default_scope'] === 'all' ? __('refreshglobal::messages.view_default_all') : __('refreshglobal::messages.view_default_shared') }}</span>@endif
                                </td>
                                <td data-label="{{ __('refreshglobal::messages.view_owner') }}">{{ $r['owner_deleted'] ? __('refreshglobal::messages.view_owner_deleted') : $r['owner_name'] }}</td>
                                <td data-label="{{ __('refreshglobal::messages.view_visibility') }}">{{ __('refreshglobal::messages.vis_'.$r['visibility']) }}@if ($r['share_edit']) · {{ __('refreshglobal::messages.view_can_edit') }}@endif</td>
                                <td data-label="{{ __('refreshglobal::messages.view_users') }}">{{ $r['visibility'] === 'global' ? __('refreshglobal::messages.vis_global_short') : (implode(', ', array_map(function ($s) { return $s['name'].($s['can_edit'] ? ' ✎' : ''); }, $r['shared_with'])) ?: '—') }}</td>
                                <td data-label="{{ __('refreshglobal::messages.view_updated') }}" title="{{ $rg_u[1] }}">{{ $rg_u[0] }}</td>
                                <td data-label="{{ __('refreshglobal::messages.view_created') }}" title="{{ $rg_c[1] }}">{{ $rg_c[0] }}</td>
                                <td class="rg-views-actions">
                                    <a class="btn btn-default btn-xs" href="{{ route('refreshglobal.tickets', ['view' => $r['id']]) }}">{{ __('refreshglobal::messages.view_open') }}</a>
                                    @if ($r['can_duplicate'])
                                        <form method="post" action="{{ route('refreshglobal.views.duplicate', ['id' => $r['id']]) }}" class="rg-inline">{{ csrf_field() }}<button type="submit" class="btn btn-default btn-xs">{{ __('refreshglobal::messages.view_duplicate') }}</button></form>
                                    @endif
                                    <form method="post" action="{{ route('refreshglobal.views.pin', ['id' => $r['id']]) }}" class="rg-inline">{{ csrf_field() }}<input type="hidden" name="back" value="manage"><button type="submit" class="btn btn-default btn-xs">{{ $r['pinned'] ? __('refreshglobal::messages.view_unpin') : __('refreshglobal::messages.view_pin') }}</button></form>
                                    <form method="post" action="{{ route('refreshglobal.views.default', ['id' => $r['id']]) }}" class="rg-inline">{{ csrf_field() }}<input type="hidden" name="back" value="manage"><button type="submit" class="btn btn-default btn-xs">{{ $r['my_default'] ? __('refreshglobal::messages.unset_default') : __('refreshglobal::messages.set_default') }}</button></form>
                                    @if ($r['can_move'])
                                        <form method="post" action="{{ route('refreshglobal.views.move', ['id' => $r['id']]) }}" class="rg-inline">{{ csrf_field() }}<input type="hidden" name="direction" value="up"><button type="submit" class="btn btn-default btn-xs" title="{{ __('refreshglobal::messages.view_move_up') }}" aria-label="{{ __('refreshglobal::messages.view_move_up') }}">↑</button></form>
                                        <form method="post" action="{{ route('refreshglobal.views.move', ['id' => $r['id']]) }}" class="rg-inline">{{ csrf_field() }}<input type="hidden" name="direction" value="down"><button type="submit" class="btn btn-default btn-xs" title="{{ __('refreshglobal::messages.view_move_down') }}" aria-label="{{ __('refreshglobal::messages.view_move_down') }}">↓</button></form>
                                    @endif
                                    <a class="btn btn-default btn-xs" href="{{ route('refreshglobal.views.export', ['id' => $r['id']]) }}">{{ __('refreshglobal::messages.view_export') }}</a>
                                    @if ($r['can_manage'] || $r['can_edit'])
                                        <details class="rg-details rg-view-edit">
                                            <summary class="btn btn-default btn-xs">{{ $r['can_manage'] ? __('refreshglobal::messages.view_edit_share') : __('refreshglobal::messages.rename') }}</summary>
                                            @if ($r['can_manage'])
                                                <form method="post" action="{{ route('refreshglobal.views.share', ['id' => $r['id']]) }}" class="rg-form rg-view-form">
                                                    {{ csrf_field() }}
                                                    <input type="hidden" name="back" value="manage">
                                                    <label class="rg-label" for="rg-vn-{{ $r['id'] }}">{{ __('refreshglobal::messages.view_name') }}</label>
                                                    <input type="text" id="rg-vn-{{ $r['id'] }}" name="name" maxlength="100" required value="{{ $r['name'] }}" class="form-control input-sm">
                                                    @include('refreshglobal::partials.view_sharing_fields', ['rg_f' => 'rg-sv-'.$r['id'], 'rg_vis' => $r['visibility'],
                                                        'rg_users' => array_column($r['shared_with'], 'id'),
                                                        'rg_editors' => array_column(array_filter($r['shared_with'], function ($s) { return $s['can_edit']; }), 'id'),
                                                        'rg_dup' => $r['allow_duplicate'], 'rg_scope' => $r['default_scope'], 'rg_can_global' => $can_global, 'rg_shareable' => $shareable])
                                                    <button type="submit" class="btn btn-primary btn-sm">{{ __('refreshglobal::messages.save') }}</button>
                                                </form>
                                            @else
                                                <form method="post" action="{{ route('refreshglobal.views.rename', ['id' => $r['id']]) }}" class="rg-form">
                                                    {{ csrf_field() }}
                                                    <input type="hidden" name="back" value="manage">
                                                    <input type="text" name="name" maxlength="100" required value="{{ $r['name'] }}" class="form-control input-sm" aria-label="{{ __('refreshglobal::messages.view_name') }}">
                                                    <button type="submit" class="btn btn-default btn-sm">{{ __('refreshglobal::messages.rename') }}</button>
                                                </form>
                                            @endif
                                        </details>
                                    @endif
                                    @if ($r['can_manage'])
                                        <form method="post" action="{{ route('refreshglobal.views.destroy', ['id' => $r['id']]) }}" class="rg-inline rg-confirm" data-confirm="{{ __('refreshglobal::messages.confirm_delete_view', ['name' => $r['name']]) }}">
                                            {{ csrf_field() }}{{ method_field('DELETE') }}<input type="hidden" name="back" value="manage">
                                            <button type="submit" class="btn btn-link btn-xs rg-danger">{{ __('refreshglobal::messages.delete_view') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach

            <h3 class="rg-views-h">{{ __('refreshglobal::messages.view_import') }}</h3>
            <form method="post" action="{{ route('refreshglobal.views.import') }}" enctype="multipart/form-data" class="rg-form rg-import-form">
                {{ csrf_field() }}
                <p class="rg-muted">{{ __('refreshglobal::messages.view_import_help') }}</p>
                <input type="file" name="file" accept="application/json,.json" aria-label="{{ __('refreshglobal::messages.view_import') }}">
                <button type="submit" class="btn btn-default btn-sm">{{ __('refreshglobal::messages.view_import') }}</button>
            </form>

            @if ($is_admin && $audit)
                <h3 class="rg-views-h">{{ __('refreshglobal::messages.views_audit') }}</h3>
                <table class="table rg-views-table rg-audit-table">
                    <tbody>
                        @foreach ($audit as $a)
                            @php $rg_at = $rg_date($a['at']); @endphp
                            <tr>
                                <td data-label="{{ __('refreshglobal::messages.view_updated') }}" title="{{ $rg_at[1] }}">{{ $rg_at[0] }}</td>
                                <td>{{ $a['user'] }}</td>
                                <td>{{ __('refreshglobal::messages.audit_'.$a['action']) }} « {{ $a['view'] }} »@if ($a['details'] !== '') ({{ $a['details'] }})@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
    </div>
@endsection

@section('javascripts')
    @parent
    <script src="{{ asset(\Module::getPublicPath('refreshglobal').'/js/refreshglobal.js') }}?v={{ \Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker::moduleVersion() }}" {!! \Helper::cspNonceAttr() !!}></script>
@endsection
