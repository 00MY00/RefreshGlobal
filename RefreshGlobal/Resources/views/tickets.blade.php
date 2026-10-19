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
    {{-- container queries of the list: not in FreeScout's CSS bundle (its minifier breaks an @container inside an @media) --}}
    <link href="{{ asset(\Module::getPublicPath('refreshglobal').'/css/refreshglobal-layout.css') }}?v={{ \Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker::moduleVersion() }}" rel="stylesheet" type="text/css">
@endsection

@php
    $rg_base = \Modules\RefreshGlobal\Services\GlobalTicketQuery::toQueryParams($filters);
    // an open saved view stays open when a filter is changed, marked "unsaved changes" (?vm=1)
    $rg_keep = $active_view ? ['view' => $active_view->id, 'vm' => 1] : [];
    $rg_url = function (array $override = [], array $remove = []) use ($rg_base, $rg_keep) {
        $p = array_merge($rg_base, $rg_keep, $override);
        foreach ($remove as $k) {
            unset($p[$k]);
        }
        if (!$p) {
            $p = ['reset' => 1];
        }
        return route('refreshglobal.tickets', $p);
    };
    $rg_export = route('refreshglobal.export', $rg_params);
    $rg_title = $active_view ? $active_view->name.($rg_view && $rg_view['modified'] ? ' *' : '') : ($filters['rview'] !== ''
        ? \Modules\RefreshGlobal\Services\GlobalDashboard::viewLabel($filters['rview']).' · '.__('refreshglobal::messages.title')
        : ($filters['wfolder'] !== '' ? $rg_folder_label.' · '.__('refreshglobal::messages.title') : __('refreshglobal::messages.title')));
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
            @include('refreshglobal::partials.views_sections')
            <div class="rf-views-sec" data-sec="rg-mailboxes">
                <button type="button" class="rf-views-sec-head">{{ __('refreshglobal::messages.mailboxes') }}<i class="rf-i rf-i-fd-chevron-down rf-i-sm"></i></button>
                {{-- Refresh hides the section heads in its phone drawer: same title, shown on phones only --}}
                <div class="rg-m-sectitle">{{ __('refreshglobal::messages.mailboxes') }}</div>
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
                {{-- Refresh hides the section heads in its phone drawer: same title, shown on phones only --}}
                <div class="rg-m-sectitle">{{ __('refreshglobal::messages.statuses') }}</div>
                <div class="rf-views-list">
                    @foreach ($rg_statuses as $code => $label)
                        <a href="{{ $rg_url(['status' => [$code]], ['page']) }}" class="rf-v @if ($rg_single_status === (int) $code) active @endif" data-label="{{ mb_strtolower($label) }}">
                            <i class="rf-i rf-i-fd-status"></i><span class="rf-v-label">{{ $label }}</span>@if (!empty($counts_by_status[$code]))<span class="rf-v-count">{{ $counts_by_status[$code] }}</span>@endif
                        </a>
                    @endforeach
                </div>
            </div>
            @if ($rg_folders)
                {{-- Automatic folders of Workflow Packs (Services/AutoFolders.php): the list filtered on a folder, sub-folders included --}}
                <div class="rf-views-sec rg-folders" data-sec="rg-folders">
                    <button type="button" class="rf-views-sec-head">{{ __('refreshglobal::messages.auto_folders') }}<i class="rf-i rf-i-fd-chevron-down rf-i-sm"></i></button>
                    {{-- Refresh hides the section heads in its phone drawer: same title, shown on phones only --}}
                    <div class="rg-m-sectitle">{{ __('refreshglobal::messages.auto_folders') }}</div>
                    <div class="rf-views-list">
                        @foreach ($rg_folders as $node)
                            <a href="{{ $rg_url(['wf' => $node['folder']], ['page']) }}" class="rf-v @if ($node['depth']) rg-folder-sub rg-depth-{{ min(5, $node['depth']) }} @endif @if ($filters['wfolder'] === $node['folder']) active @endif" data-label="{{ mb_strtolower($node['label']) }}" title="{{ $node['label'] }}">
                                <i class="rg-folder-icon">{!! \Modules\RefreshGlobal\Services\AutoFolders::icon() !!}</i><span class="rf-v-label">{{ $node['name'] }}</span>@if ($node['count'])<span class="rf-v-count">{{ $node['count'] }}</span>@endif
                            </a>
                        @endforeach
                        <a href="{{ route('refreshglobal.folders') }}" class="rf-v rg-folders-all" data-label="{{ mb_strtolower(__('refreshglobal::messages.folders_overview')) }}">
                            <i class="rf-i rf-i-fd-views"></i><span class="rf-v-label">{{ __('refreshglobal::messages.folders_overview') }}</span>
                        </a>
                    </div>
                </div>
            @endif
            @if ($rg_mail_tags)
                {{-- Origin tags of the e-mails (Services/MailTags.php): the list filtered on a tag --}}
                <div class="rf-views-sec rg-mail-tags" data-sec="rg-mail-tags">
                    <button type="button" class="rf-views-sec-head">{{ __('refreshglobal::messages.mail_tags') }}<i class="rf-i rf-i-fd-chevron-down rf-i-sm"></i></button>
                    <div class="rg-m-sectitle">{{ __('refreshglobal::messages.mail_tags') }}</div>
                    <div class="rf-views-list">
                        @foreach ($rg_mail_tags as $rg_tag => $rg_n)
                            <a href="{{ $rg_url(['mt' => $rg_tag], ['page']) }}" class="rf-v @if ($filters['mailtag'] === $rg_tag) active @endif" data-label="{{ $rg_tag }}" title="{{ $rg_tag }}">
                                <i class="rg-tag-icon @if ($rg_tag === \Modules\RefreshGlobal\Services\MailTags::SPAM) rg-tag-icon-spam @endif">#</i><span class="rf-v-label">{{ $rg_tag }}</span><span class="rf-v-count">{{ $rg_n }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
            @include('refreshglobal::partials.view_actions')
        </div>
    @else
        {{-- FreeScout's standard sidebar markup (resources/views/mailboxes/sidebar_menu_view.blade.php, partials/folders.blade.php) --}}
        <div class="sidebar-title">
            <span class="sidebar-title-real">{{ __('refreshglobal::messages.title') }}</span>
        </div>
        @include('refreshglobal::partials.views_sections')
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
        @if ($rg_folders)
            <div class="sidebar-title rg-sidebar-subtitle"><span class="sidebar-title-real">{{ __('refreshglobal::messages.auto_folders') }}</span></div>
            <ul class="sidebar-menu rg-sidebar-menu rg-folders">
                @foreach ($rg_folders as $node)
                    <li class="@if ($node['depth']) rg-folder-sub rg-depth-{{ min(5, $node['depth']) }} @endif @if ($filters['wfolder'] === $node['folder']) active @endif">
                        <a href="{{ $rg_url(['wf' => $node['folder']], ['page']) }}" title="{{ $node['label'] }}" @if (!$node['count']) class="no-active" @endif><i class="glyphicon glyphicon-folder-close"></i> <span class="folder-name">{{ $node['name'] }}</span>@if ($node['count'])<span class="active-count pull-right">{{ $node['count'] }}</span>@endif</a>
                    </li>
                @endforeach
                <li><a href="{{ route('refreshglobal.folders') }}"><i class="glyphicon glyphicon-th-list"></i> <span class="folder-name">{{ __('refreshglobal::messages.folders_overview') }}</span></a></li>
            </ul>
        @endif
        @if ($rg_mail_tags)
            <div class="sidebar-title rg-sidebar-subtitle"><span class="sidebar-title-real">{{ __('refreshglobal::messages.mail_tags') }}</span></div>
            <ul class="sidebar-menu rg-sidebar-menu rg-mail-tags">
                @foreach ($rg_mail_tags as $rg_tag => $rg_n)
                    <li class="@if ($filters['mailtag'] === $rg_tag) active @endif">
                        <a href="{{ $rg_url(['mt' => $rg_tag], ['page']) }}" title="{{ $rg_tag }}"><i class="glyphicon glyphicon-tag"></i> <span class="folder-name">{{ $rg_tag }}</span><span class="active-count pull-right">{{ $rg_n }}</span></a>
                    </li>
                @endforeach
            </ul>
        @endif
        @include('refreshglobal::partials.view_actions')
    @endif
@endsection

@section('content')
    <div class="alerts">
        @include('partials/flash_messages')
        @include('refreshglobal::partials.notices')
    </div>
    <span id="rg-state" hidden data-base="{{ $rg_url([], ['page']) }}" data-sort="{{ $filters['sort'] }}" data-order="{{ $filters['order'] }}" data-keep-position="{{ \Modules\RefreshGlobal\Services\Settings::keepPosition() ? 1 : 0 }}"
        data-refresh="{{ \Modules\RefreshGlobal\Services\Settings::autoRefresh() }}" data-fp="{{ $rg_fp }}"
        data-mailbox="{{ $rg_single_mb }}" data-back-url="{{ $rg_back['url'] }}" data-back-label="{{ $rg_back['label'] }}" data-back-count="{{ $rg_back['count'] }}" data-state-url="{{ \Modules\RefreshGlobal\Http\Controllers\GlobalTicketsController::stateUrl($rg_params) }}"></span>

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
                                @if (\Modules\RefreshGlobal\Services\Settings::refreshHoverCards())
                                    {{-- Refresh 1.6.0's hover previews (its Public/js/hovercards.js works on this list too): same on / off
                                         entry as in Refresh's Layout menu, handled by Refresh's script (rf_hover cookie) --}}
                                    <li class="divider"></li>
                                    <li class="rf-hover-toggle @if (($_COOKIE['rf_hover'] ?? '') !== 'off') active @endif"><a href="#">{{ __('Hover previews') }} <i class="rf-i rf-i-fd-check rf-i-sm rf-hover-check"></i></a></li>
                                @endif
                            </ul>
                        </div>
                        {{-- icon only (circular arrow, text in the tooltip): the toolbar has no room for one more label --}}
                        @include('refreshglobal::partials.syncnow_button', ['class' => 'rf-btn rg-sync-icon-only', 'label' => false])
                        <a class="rf-btn rg-export" href="{{ $rg_export }}" title="{{ __('refreshglobal::messages.export_hint', ['max' => $export_max]) }}"><i class="rf-i rf-i-download"></i><span class="rg-btn-text"> {{ __('refreshglobal::messages.export') }}</span></a>
                        <span class="rf-range">{{ __('refreshglobal::messages.range', ['from' => $rg_first, 'to' => $rg_last, 'total' => $conversations->total()]) }}</span>
                        <span class="rf-pager">
                            <a class="rf-pager-btn @if ($conversations->onFirstPage()) disabled @endif" href="{{ $conversations->onFirstPage() ? '#' : $conversations->previousPageUrl() }}" title="{{ __('refreshglobal::messages.previous_page') }}"><i class="rf-i rf-i-chevron-left rf-i-sm"></i></a><a class="rf-pager-btn @if (!$conversations->hasMorePages()) disabled @endif" href="{{ $conversations->hasMorePages() ? $conversations->nextPageUrl() : '#' }}" title="{{ __('refreshglobal::messages.next_page') }}"><i class="rf-i rf-i-chevron-right rf-i-sm"></i></a>
                        </span>
                        <button type="button" class="rf-btn rf-toggle-filters @if ($filters_count) rg-has-filters @endif" aria-controls="rg-filters"><i class="rf-i rf-i-filter"></i> {{ __('refreshglobal::messages.filters') }} @if ($filters_count)({{ $filters_count }})@endif</button>
                    </div>
                </div>
                @include('refreshglobal::partials.view_banner')
                @include('refreshglobal::partials.folder_banner')
                @include('refreshglobal::partials.active_filters')

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
                @include('refreshglobal::partials.syncnow_button', ['class' => 'btn btn-default btn-sm', 'label' => true])
                <a class="btn btn-default btn-sm rg-export" href="{{ $rg_export }}" title="{{ __('refreshglobal::messages.export_hint', ['max' => $export_max]) }}"><i class="glyphicon glyphicon-download-alt"></i> {{ __('refreshglobal::messages.export') }}</a>
            </span>
        </div>
        @include('refreshglobal::partials.view_banner')
        @include('refreshglobal::partials.folder_banner')
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
