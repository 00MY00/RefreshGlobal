{{--
    "All mailboxes" page.
    skin "refresh": same HTML structure and classes as Refresh's list page (Modules/Refresh/Resources/views/tickets.blade.php),
                    so Refresh's own stylesheet and scripts (loaded by Refresh on every page) give it the same look.
    skin "native":  FreeScout's standard markup (resources/views/mailboxes/view.blade.php, sidebar_menu_view.blade.php),
                    used when Refresh or one of its files is missing (state "degraded").
    The list itself is always FreeScout's native table (conversations/conversations_table) with a fake folder.
--}}
@extends('layouts.app')

@section('title', __('refreshglobal::messages.title').' ('.$conversations->total().')')

@section('body_attrs')@parent data-rg-page="tickets" data-rg-skin="{{ $skin }}"@endsection

@section('stylesheets')
    @parent
    <link href="{{ asset(\Module::getPublicPath('refreshglobal').'/css/refreshglobal.css') }}?v={{ \Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker::moduleVersion() }}" rel="stylesheet" type="text/css">
@endsection

@php
    $rg_base = \Modules\RefreshGlobal\Services\GlobalTicketQuery::toQueryParams($filters);
    $rg_url = function (array $override = [], array $remove = []) use ($rg_base) {
        $p = array_merge($rg_base, $override);
        foreach ($remove as $k) {
            unset($p[$k]);
        }
        if (!$p) {
            $p = ['reset' => 1];
        }
        return route('refreshglobal.tickets', $p);
    };
    $rg_export = route('refreshglobal.export', $rg_params);
    $rg_title = $active_view ? $active_view->name : __('refreshglobal::messages.title');
    $rg_first = $conversations->firstItem() ?: 0;
    $rg_last = $conversations->lastItem() ?: 0;
    $rg_sel = function ($list, $value) { return in_array((string) $value, array_map('strval', (array) $list), true) ? 'selected' : ''; };
    $rg_single_mb = count($filters['mailboxes']) === 1 ? (int) $filters['mailboxes'][0] : 0;
    $rg_single_status = count($filters['status']) === 1 ? (int) $filters['status'][0] : 0;
    $rg_layout_table = ($_COOKIE['rf_layout'] ?? '') === 'table';
    $rg_filters_closed = !empty($_COOKIE['rf_filters_closed']);
@endphp

@section('sidebar')
    @include('partials/sidebar_menu_toggle')
    @if ($skin === 'refresh')
        {{-- Same structure as Refresh's views panel (Modules/Refresh/Resources/views/core/mailboxes/sidebar_menu_view.blade.php) --}}
        <div class="rf-views rg-views">
            <div class="rf-views-search">
                <i class="rf-i rf-i-search"></i>
                <input type="text" class="rf-views-filter" placeholder="{{ __('refreshglobal::messages.search_views') }}">
            </div>
            <div class="rf-views-sec" data-sec="rg-views">
                <button type="button" class="rf-views-sec-head">{{ __('refreshglobal::messages.my_views') }}<i class="rf-i rf-i-fd-chevron-down rf-i-sm"></i></button>
                <div class="rf-views-list">
                    <a href="{{ route('refreshglobal.tickets', ['reset' => 1]) }}" class="rf-v @if (!$active_view && !$filters_count) active @endif" data-label="{{ mb_strtolower(__('refreshglobal::messages.title')) }}">
                        <i class="rf-i rf-i-fd-all-tickets"></i><span class="rf-v-label">{{ __('refreshglobal::messages.title') }}</span>@if ($total_all)<span class="rf-v-count">{{ $total_all }}</span>@endif
                    </a>
                    @if ($saved_views)
                        @foreach ($saved_views as $sv)
                            <a href="{{ route('refreshglobal.tickets', ['view' => $sv->id]) }}" class="rf-v @if ($active_view && $active_view->id == $sv->id) active @endif" data-label="{{ mb_strtolower($sv->name) }}" title="{{ $sv->name }}">
                                <i class="rf-i rf-i-fd-views"></i><span class="rf-v-label">{{ $sv->name }}</span>@if ($sv->is_default)<span class="rg-default-mark" title="{{ __('refreshglobal::messages.default_view') }}">★</span>@endif
                            </a>
                        @endforeach
                    @endif
                </div>
            </div>
            <div class="rf-views-sec" data-sec="rg-mailboxes">
                <button type="button" class="rf-views-sec-head">{{ __('refreshglobal::messages.mailboxes') }}<i class="rf-i rf-i-fd-chevron-down rf-i-sm"></i></button>
                <div class="rf-views-list">
                    @foreach ($mailboxes as $mb)
                        <a href="{{ $rg_url(['mb' => [$mb->id]], ['page']) }}" class="rf-v @if ($rg_single_mb === (int) $mb->id) active @endif" data-label="{{ mb_strtolower($mb->name) }}" title="{{ $mb->name }}">
                            <i class="rf-i rf-i-inbox"></i><span class="rf-v-label">{{ $mb->name }}</span>@if (!empty($counts_by_mailbox[$mb->id]))<span class="rf-v-count">{{ $counts_by_mailbox[$mb->id] }}</span>@endif
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="rf-views-sec" data-sec="rg-status">
                <button type="button" class="rf-views-sec-head">{{ __('refreshglobal::messages.statuses') }}<i class="rf-i rf-i-fd-chevron-down rf-i-sm"></i></button>
                <div class="rf-views-list">
                    @foreach ($rg_statuses as $code => $label)
                        <a href="{{ $rg_url(['status' => [$code]], ['page']) }}" class="rf-v @if ($rg_single_status === (int) $code) active @endif" data-label="{{ mb_strtolower($label) }}">
                            <i class="rf-i rf-i-fd-status"></i><span class="rf-v-label">{{ $label }}</span>@if (!empty($counts_by_status[$code]))<span class="rf-v-count">{{ $counts_by_status[$code] }}</span>@endif
                        </a>
                    @endforeach
                </div>
            </div>
            @include('refreshglobal::partials.view_actions')
        </div>
    @else
        {{-- FreeScout's standard sidebar markup (resources/views/mailboxes/sidebar_menu_view.blade.php, partials/folders.blade.php) --}}
        <div class="sidebar-title">
            <span class="sidebar-title-real">{{ __('refreshglobal::messages.title') }}</span>
        </div>
        <ul class="sidebar-menu rg-sidebar-menu">
            <li class="@if (!$active_view && !$filters_count) active @endif">
                <a href="{{ route('refreshglobal.tickets', ['reset' => 1]) }}"><i class="glyphicon glyphicon-inbox"></i> <span class="folder-name">{{ __('refreshglobal::messages.all_tickets') }}</span>@if ($total_all)<span class="active-count pull-right">{{ $total_all }}</span>@endif</a>
            </li>
            @if ($saved_views)
                @foreach ($saved_views as $sv)
                    <li class="@if ($active_view && $active_view->id == $sv->id) active @endif">
                        <a href="{{ route('refreshglobal.tickets', ['view' => $sv->id]) }}"><i class="glyphicon glyphicon-filter"></i> <span class="folder-name">{{ $sv->name }}@if ($sv->is_default) ★@endif</span></a>
                    </li>
                @endforeach
            @endif
        </ul>
        <div class="sidebar-title rg-sidebar-subtitle"><span class="sidebar-title-real">{{ __('refreshglobal::messages.mailboxes') }}</span></div>
        <ul class="sidebar-menu rg-sidebar-menu">
            @foreach ($mailboxes as $mb)
                <li class="@if ($rg_single_mb === (int) $mb->id) active @endif">
                    <a href="{{ $rg_url(['mb' => [$mb->id]], ['page']) }}" @if (empty($counts_by_mailbox[$mb->id])) class="no-active" @endif><i class="glyphicon glyphicon-envelope"></i> <span class="folder-name">{{ $mb->name }}</span>@if (!empty($counts_by_mailbox[$mb->id]))<span class="active-count pull-right">{{ $counts_by_mailbox[$mb->id] }}</span>@endif</a>
                </li>
            @endforeach
        </ul>
        <div class="sidebar-title rg-sidebar-subtitle"><span class="sidebar-title-real">{{ __('refreshglobal::messages.statuses') }}</span></div>
        <ul class="sidebar-menu rg-sidebar-menu">
            @foreach ($rg_statuses as $code => $label)
                <li class="@if ($rg_single_status === (int) $code) active @endif">
                    <a href="{{ $rg_url(['status' => [$code]], ['page']) }}" @if (empty($counts_by_status[$code])) class="no-active" @endif><i class="glyphicon glyphicon-flag"></i> <span class="folder-name">{{ $label }}</span>@if (!empty($counts_by_status[$code]))<span class="active-count pull-right">{{ $counts_by_status[$code] }}</span>@endif</a>
                </li>
            @endforeach
        </ul>
        @include('refreshglobal::partials.view_actions')
    @endif
@endsection

@section('content')
    <div class="alerts">
        @include('partials/flash_messages')
        @include('refreshglobal::partials.notices')
    </div>
    <span id="rg-state" hidden data-base="{{ $rg_url([], ['page']) }}" data-sort="{{ $filters['sort'] }}" data-order="{{ $filters['order'] }}"></span>

    @if ($skin === 'refresh')
        {{-- View bar (moved into Refresh's top bar by its script) --}}
        <div class="rf-viewbar">
            <button type="button" class="rf-sqbtn rf-toggle-views" title="{{ __('refreshglobal::messages.views') }}"><i class="rf-i rf-i-fd-views"></i></button>
            <h1 class="rf-viewbar-title">{{ $rg_title }}</h1>
            <span class="rf-pill">{{ $conversations->total() }}</span>
        </div>

        <div class="rf-list-layout rg-list-layout @if ($rg_filters_closed) rf-filters-closed @endif @if ($rg_layout_table) rf-layout-table @endif">
            <div class="rf-list-main">
                <div class="rf-toolbar">
                    <label class="rf-cb-all" title="{{ __('refreshglobal::messages.select_all') }}"><input type="checkbox" class="rf-toggle-all"><span></span></label>
                    <div class="dropdown rf-sort">
                        <span class="rf-sort-label">{{ __('refreshglobal::messages.sort_by') }}</span>
                        <a href="#" class="dropdown-toggle rf-sort-current" data-toggle="dropdown">{{ $sorts[$filters['sort']] }} <i class="rf-i rf-i-fd-chevron-down rf-i-sm"></i></a>
                        <ul class="dropdown-menu">
                            @foreach ($sorts as $key => $label)
                                <li class="@if ($key === $filters['sort']) active @endif"><a href="{{ $rg_url(['sort' => $key], ['page']) }}">{{ $label }}</a></li>
                            @endforeach
                            <li class="divider"></li>
                            <li class="@if ($filters['order'] === 'asc') active @endif"><a href="{{ $rg_url(['order' => 'asc'], ['page']) }}">{{ __('refreshglobal::messages.ascending') }}</a></li>
                            <li class="@if ($filters['order'] === 'desc') active @endif"><a href="{{ $rg_url(['order' => 'desc'], ['page']) }}">{{ __('refreshglobal::messages.descending') }}</a></li>
                        </ul>
                    </div>
                    <div class="rf-toolbar-right">
                        <div class="dropdown rf-sort rf-layout-dd">
                            <span class="rf-sort-label">{{ __('refreshglobal::messages.layout') }}</span>
                            <a href="#" class="dropdown-toggle rf-sort-current" data-toggle="dropdown">{{ $rg_layout_table ? __('refreshglobal::messages.table_view') : __('refreshglobal::messages.card_view') }} <i class="rf-i rf-i-fd-chevron-down rf-i-sm"></i></a>
                            <ul class="dropdown-menu dropdown-menu-right">
                                <li class="@if (!$rg_layout_table) active @endif"><a href="#" class="rf-set-layout" data-layout="card">{{ __('refreshglobal::messages.card_view') }}</a></li>
                                <li class="@if ($rg_layout_table) active @endif"><a href="#" class="rf-set-layout" data-layout="table">{{ __('refreshglobal::messages.table_view') }}</a></li>
                            </ul>
                        </div>
                        <a class="rf-btn rg-export" href="{{ $rg_export }}" title="{{ __('refreshglobal::messages.export_hint', ['max' => $export_max]) }}"><i class="rf-i rf-i-download"></i> {{ __('refreshglobal::messages.export') }}</a>
                        <span class="rf-range">{{ __('refreshglobal::messages.range', ['from' => $rg_first, 'to' => $rg_last, 'total' => $conversations->total()]) }}</span>
                        <span class="rf-pager">
                            <a class="rf-pager-btn @if ($conversations->onFirstPage()) disabled @endif" href="{{ $conversations->onFirstPage() ? '#' : $conversations->previousPageUrl() }}" title="{{ __('refreshglobal::messages.previous_page') }}"><i class="rf-i rf-i-chevron-left rf-i-sm"></i></a><a class="rf-pager-btn @if (!$conversations->hasMorePages()) disabled @endif" href="{{ $conversations->hasMorePages() ? $conversations->nextPageUrl() : '#' }}" title="{{ __('refreshglobal::messages.next_page') }}"><i class="rf-i rf-i-chevron-right rf-i-sm"></i></a>
                        </span>
                        <button type="button" class="rf-btn rf-toggle-filters"><i class="rf-i rf-i-filter"></i> {{ __('refreshglobal::messages.filters') }} @if ($filters_count)({{ $filters_count }})@endif</button>
                    </div>
                </div>

                <div class="rf-list-scroll">
                    @include('conversations/conversations_table', ['folder' => $folder, 'conversations' => $conversations])
                </div>
            </div>

            @include('refreshglobal::partials.filters', ['form_class' => 'rf-filters'])
        </div>
    @else
        <div class="section-heading rg-heading">
            <span class="rg-heading-title">{{ $rg_title }} <small>({{ $conversations->total() }})</small></span>
            <span class="rg-heading-actions">
                <a class="btn btn-default btn-sm rg-toggle-filters" href="#rg-filters"><i class="glyphicon glyphicon-filter"></i> {{ __('refreshglobal::messages.filters') }} @if ($filters_count)({{ $filters_count }})@endif</a>
                <a class="btn btn-default btn-sm rg-export" href="{{ $rg_export }}" title="{{ __('refreshglobal::messages.export_hint', ['max' => $export_max]) }}"><i class="glyphicon glyphicon-download-alt"></i> {{ __('refreshglobal::messages.export') }}</a>
            </span>
        </div>
        @include('refreshglobal::partials.filters', ['form_class' => 'rg-filters-native'])
        @include('conversations/conversations_table', ['folder' => $folder, 'conversations' => $conversations])
        @if ($conversations->hasPages())
            <div class="rg-pagination">{{ $conversations->links() }}</div>
        @endif
    @endif
@endsection

@section('javascripts')
    @parent
    <script src="{{ asset(\Module::getPublicPath('refreshglobal').'/js/refreshglobal.js') }}?v={{ \Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker::moduleVersion() }}" {!! \Helper::cspNonceAttr() !!}></script>
@endsection
