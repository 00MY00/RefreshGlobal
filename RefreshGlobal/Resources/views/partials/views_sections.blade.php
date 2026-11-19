{{--
    Saved views in the views panel: pinned (per user), my views (with "All mailboxes" first), shared with me, for
    everyone. $saved_views = SavedViews::visibleFor() rows. Two markups: Refresh's panel ($skin refresh) and FreeScout's
    sidebar menu (native).
    1.22.0: "My views" ends with "+ New view" (dialog partials/new_view_dialog) and, with two views or more, "Reorder"
    (drag and drop or arrows, Public/js/refreshglobal.js viewOrder(); own views carry data-rg-view-id).
--}}
@php
    $rg_sv = collect($saved_views ?: []);
    $rg_sections = [
        'pinned' => [__('refreshglobal::messages.views_pinned'), $rg_sv->where('pinned', true)->values()],
        'mine'   => [__('refreshglobal::messages.my_views'), $rg_sv->where('section', 'mine')->values()],
        'shared' => [__('refreshglobal::messages.views_shared'), $rg_sv->where('section', 'shared')->values()],
        'global' => [__('refreshglobal::messages.views_global'), $rg_sv->where('section', 'global')->values()],
    ];
    $rg_active_id = $active_view ? (int) $active_view->id : 0;
    $rg_modified = $rg_view && $rg_view['modified'];
    $rg_vis_icon = function ($v) {
        return $v['visibility'] === 'global' ? 'glyphicon-globe' : ($v['visibility'] === 'users' ? 'glyphicon-user' : '');
    };
    $rg_tip = function ($v) {
        $t = $v['name'];
        if ($v['section'] !== 'mine' && $v['owner_name'] !== '') {
            $t .= ' — '.__('refreshglobal::messages.view_by', ['name' => $v['owner_name']]);
        }
        return $t;
    };
@endphp
@foreach ($rg_sections as $rg_key => $rg_sec)
    @if ($rg_key !== 'mine' && !count($rg_sec[1]))
        @continue
    @endif
    @if ($skin === 'refresh')
        <div class="rf-views-sec rg-views-{{ $rg_key }}" data-sec="rg-views-{{ $rg_key }}">
            <button type="button" class="rf-views-sec-head">{{ $rg_sec[0] }}<i class="rf-i rf-i-fd-chevron-down rf-i-sm"></i></button>
            {{-- Refresh hides the section heads in its phone drawer: same title, shown on phones only --}}
            <div class="rg-m-sectitle">{{ $rg_sec[0] }}</div>
            <div class="rf-views-list">
                @if ($rg_key === 'mine')
                    <a href="{{ route('refreshglobal.tickets', ['reset' => 1]) }}" class="rf-v @if (!$active_view && !$filters_count) active @endif" data-label="{{ mb_strtolower(__('refreshglobal::messages.title')) }}">
                        <i class="rf-i rf-i-fd-all-tickets"></i><span class="rf-v-label">{{ __('refreshglobal::messages.title') }}</span>@if ($total_all)<span class="rf-v-count">{{ $total_all }}</span>@endif
                    </a>
                @endif
                @foreach ($rg_sec[1] as $sv)
                    <a href="{{ route('refreshglobal.tickets', ['view' => $sv['id']]) }}" class="rf-v @if ($rg_active_id === $sv['id']) active @endif" data-label="{{ mb_strtolower($sv['name']) }}" title="{{ $rg_tip($sv) }}" @if ($rg_key === 'mine') data-rg-view-id="{{ $sv['id'] }}" data-rg-view-name="{{ $sv['name'] }}" @endif>
                        <i class="rf-i rf-i-fd-views"></i><span class="rf-v-label">{{ $sv['name'] }}@if ($rg_active_id === $sv['id'] && $rg_modified) *@endif</span>
                        @if ($rg_vis_icon($sv))<i class="glyphicon {{ $rg_vis_icon($sv) }} rg-vis-icon"></i>@endif
                        @if ($sv['pinned'] && $rg_key !== 'pinned')<i class="glyphicon glyphicon-pushpin rg-pin-mark" title="{{ __('refreshglobal::messages.views_pinned') }}"></i>@endif
                        @if ($sv['my_default'])<span class="rg-default-mark" title="{{ __('refreshglobal::messages.default_view') }}">★</span>@endif
                    </a>
                @endforeach
                @if ($rg_key === 'mine' && $saved_views_ok)
                    <div class="rg-views-tools">
                        <button type="button" class="rf-v rg-new-view-open rg-views-new" data-label="{{ mb_strtolower(__('refreshglobal::messages.new_view')) }}">
                            <i class="glyphicon glyphicon-plus rg-views-tool-icon" aria-hidden="true"></i><span class="rf-v-label">{{ __('refreshglobal::messages.new_view') }}</span>
                        </button>
                        @if (count($rg_sec[1]) > 1)
                            <button type="button" class="rf-v rg-views-order" data-url="{{ route('refreshglobal.views.order') }}" data-t-done="{{ __('refreshglobal::messages.views_reorder_done') }}" data-t-help="{{ __('refreshglobal::messages.views_reorder_help') }}" data-t-up="{{ __('refreshglobal::messages.view_move_up') }}" data-t-down="{{ __('refreshglobal::messages.view_move_down') }}" data-t-handle="{{ __('refreshglobal::messages.views_reorder_handle') }}" data-t-saved="{{ __('refreshglobal::messages.views_order_saved') }}" data-t-error="{{ __('refreshglobal::messages.views_order_error') }}" data-label="{{ mb_strtolower(__('refreshglobal::messages.views_reorder')) }}">
                                <i class="glyphicon glyphicon-sort rg-views-tool-icon" aria-hidden="true"></i><span class="rf-v-label">{{ __('refreshglobal::messages.views_reorder') }}</span>
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @else
        @if ($rg_key !== 'mine')
            <div class="sidebar-title rg-sidebar-subtitle"><span class="sidebar-title-real">{{ $rg_sec[0] }}</span></div>
        @endif
        <ul class="sidebar-menu rg-sidebar-menu rg-views-{{ $rg_key }}">
            @if ($rg_key === 'mine')
                <li class="@if (!$active_view && !$filters_count) active @endif">
                    <a href="{{ route('refreshglobal.tickets', ['reset' => 1]) }}"><i class="glyphicon glyphicon-inbox"></i> <span class="folder-name">{{ __('refreshglobal::messages.all_tickets') }}</span>@if ($total_all)<span class="active-count pull-right">{{ $total_all }}</span>@endif</a>
                </li>
            @endif
            @foreach ($rg_sec[1] as $sv)
                <li class="@if ($rg_active_id === $sv['id']) active @endif" @if ($rg_key === 'mine') data-rg-view-id="{{ $sv['id'] }}" data-rg-view-name="{{ $sv['name'] }}" @endif>
                    <a href="{{ route('refreshglobal.tickets', ['view' => $sv['id']]) }}" title="{{ $rg_tip($sv) }}"><i class="glyphicon {{ $rg_vis_icon($sv) ?: 'glyphicon-filter' }}"></i> <span class="folder-name">{{ $sv['name'] }}@if ($rg_active_id === $sv['id'] && $rg_modified) *@endif @if ($sv['my_default'])★@endif</span></a>
                </li>
            @endforeach
            @if ($rg_key === 'mine' && $saved_views_ok)
                <li class="rg-views-tools">
                    <a href="#rg-new-view" class="rg-new-view-open rg-views-new"><i class="glyphicon glyphicon-plus"></i> <span class="folder-name">{{ __('refreshglobal::messages.new_view') }}</span></a>
                </li>
                @if (count($rg_sec[1]) > 1)
                    <li class="rg-views-tools">
                        <a href="#" class="rg-views-order" role="button" data-url="{{ route('refreshglobal.views.order') }}" data-t-done="{{ __('refreshglobal::messages.views_reorder_done') }}" data-t-help="{{ __('refreshglobal::messages.views_reorder_help') }}" data-t-up="{{ __('refreshglobal::messages.view_move_up') }}" data-t-down="{{ __('refreshglobal::messages.view_move_down') }}" data-t-handle="{{ __('refreshglobal::messages.views_reorder_handle') }}" data-t-saved="{{ __('refreshglobal::messages.views_order_saved') }}" data-t-error="{{ __('refreshglobal::messages.views_order_error') }}"><i class="glyphicon glyphicon-sort"></i> <span class="folder-name">{{ __('refreshglobal::messages.views_reorder') }}</span></a>
                    </li>
                @endif
            @endif
        </ul>
    @endif
@endforeach
