{{--
    Saved views: save the filters shown (private, shared with users, for everyone), and the open view's actions (pin,
    my default, duplicate, export, rename, share, delete) according to the user's rights (Services/SavedViews.php).
    Plain forms + CSRF token: everything works without a script; stacked fields for phones.
--}}
<div class="rg-view-actions">
    {{-- Phones: Refresh hides the list toolbar (Export button), the export stays reachable from the drawer --}}
    <a class="rg-m-only rg-m-export" href="{{ $rg_export }}">{{ __('refreshglobal::messages.export') }} (CSV)</a>
    {{-- phones: the list toolbar is hidden by Refresh, the button is in the drawer --}}
    @include('refreshglobal::partials.syncnow_button', ['class' => 'btn btn-default btn-sm rg-m-only rg-m-sync', 'label' => true])
    @if (!$saved_views_ok)
        <p class="rg-muted">[RG-DB-05] {{ __('refreshglobal::messages.views_unavailable') }}</p>
    @else
        <details class="rg-details" id="rg-save-view">
            <summary>{{ __('refreshglobal::messages.save_view') }}</summary>
            <form method="post" action="{{ route('refreshglobal.views.store') }}" class="rg-form rg-view-form">
                {{ csrf_field() }}
                @include('refreshglobal::partials.view_filter_fields')
                <label class="rg-label" for="rg-view-name">{{ __('refreshglobal::messages.view_name') }}</label>
                <input type="text" id="rg-view-name" name="name" maxlength="100" required class="form-control input-sm"
                    @if ($active_view && !empty($rg_view['modified'])) value="{{ __('refreshglobal::messages.view_copy_name', ['name' => $active_view->name]) }}" @endif>
                @include('refreshglobal::partials.view_sharing_fields', ['rg_f' => 'rg-sv-new', 'rg_vis' => 'private', 'rg_users' => [], 'rg_editors' => [],
                    'rg_dup' => true, 'rg_scope' => '', 'rg_can_global' => \Modules\RefreshGlobal\Services\SavedViews::canPublishGlobal(auth()->user()), 'rg_shareable' => $rg_shareable])
                <label class="rg-check"><input type="checkbox" name="is_default" value="1"> {{ __('refreshglobal::messages.make_default') }}</label>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('refreshglobal::messages.save') }}</button>
            </form>
        </details>
        @if ($active_view && $rg_view)
            <details class="rg-details" id="rg-manage-view">
                <summary>{{ __('refreshglobal::messages.manage_view', ['name' => $active_view->name]) }}</summary>
                <p class="rg-muted rg-view-about">
                    {{ __('refreshglobal::messages.vis_'.$rg_view['visibility']) }}@if (!$rg_view['own'] && $rg_view['owner_name'] !== '') · {{ __('refreshglobal::messages.view_by', ['name' => $rg_view['owner_name']]) }}@endif
                    @if ($rg_view['shared_with']) · {{ __('refreshglobal::messages.view_shared_with', ['names' => implode(', ', array_column($rg_view['shared_with'], 'name'))]) }}@endif
                </p>
                <div class="rg-view-buttons">
                    <form method="post" action="{{ route('refreshglobal.views.pin', ['id' => $active_view->id]) }}" class="rg-inline">
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-default btn-sm">{{ $rg_view['pinned'] ? __('refreshglobal::messages.view_unpin') : __('refreshglobal::messages.view_pin') }}</button>
                    </form>
                    <form method="post" action="{{ route('refreshglobal.views.default', ['id' => $active_view->id]) }}" class="rg-inline">
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-default btn-sm">{{ $rg_view['my_default'] ? __('refreshglobal::messages.unset_default') : __('refreshglobal::messages.set_default') }}</button>
                    </form>
                    @if ($rg_view['can_duplicate'])
                        <form method="post" action="{{ route('refreshglobal.views.duplicate', ['id' => $active_view->id]) }}" class="rg-inline">
                            {{ csrf_field() }}
                            <button type="submit" class="btn btn-default btn-sm">{{ __('refreshglobal::messages.view_duplicate') }}</button>
                        </form>
                    @endif
                    <a class="btn btn-default btn-sm" href="{{ route('refreshglobal.views.export', ['id' => $active_view->id]) }}">{{ __('refreshglobal::messages.view_export') }}</a>
                </div>
                @if ($rg_view['can_edit'] && !$rg_view['can_manage'])
                    <form method="post" action="{{ route('refreshglobal.views.rename', ['id' => $active_view->id]) }}" class="rg-form">
                        {{ csrf_field() }}
                        <label class="rg-label" for="rg-view-rename">{{ __('refreshglobal::messages.rename') }}</label>
                        <input type="text" id="rg-view-rename" name="name" maxlength="100" required value="{{ $active_view->name }}" class="form-control input-sm">
                        <button type="submit" class="btn btn-default btn-sm">{{ __('refreshglobal::messages.rename') }}</button>
                    </form>
                @endif
                @if ($rg_view['can_manage'])
                    <form method="post" action="{{ route('refreshglobal.views.share', ['id' => $active_view->id]) }}" class="rg-form rg-view-form">
                        {{ csrf_field() }}
                        <label class="rg-label" for="rg-view-rename">{{ __('refreshglobal::messages.view_name') }}</label>
                        <input type="text" id="rg-view-rename" name="name" maxlength="100" required value="{{ $active_view->name }}" class="form-control input-sm">
                        @include('refreshglobal::partials.view_sharing_fields', ['rg_f' => 'rg-sv-edit', 'rg_vis' => $rg_view['visibility'],
                            'rg_users' => array_column($rg_view['shared_with'], 'id'),
                            'rg_editors' => array_column(array_filter($rg_view['shared_with'], function ($s) { return $s['can_edit']; }), 'id'),
                            'rg_dup' => $rg_view['allow_duplicate'], 'rg_scope' => $rg_view['default_scope'], 'rg_can_global' => $rg_view['can_global'], 'rg_shareable' => $rg_shareable])
                        <button type="submit" class="btn btn-default btn-sm">{{ __('refreshglobal::messages.save') }}</button>
                    </form>
                    <form method="post" action="{{ route('refreshglobal.views.destroy', ['id' => $active_view->id]) }}" class="rg-form rg-confirm" data-confirm="{{ __('refreshglobal::messages.confirm_delete_view', ['name' => $active_view->name]) }}">
                        {{ csrf_field() }}
                        {{ method_field('DELETE') }}
                        <button type="submit" class="btn btn-link btn-sm rg-danger">{{ __('refreshglobal::messages.delete_view') }}</button>
                    </form>
                @endif
            </details>
        @endif
        <a class="rg-manage-views" href="{{ route('refreshglobal.views') }}"><i class="glyphicon glyphicon-cog"></i> {{ __('refreshglobal::messages.manage_views') }}</a>
    @endif
    {{-- Empty the trash of the user's mailboxes (admins / users allowed to delete conversations), with confirmation --}}
    @if (\Modules\RefreshGlobal\Services\Trash::userCanEmpty(auth()->user()))
        @php $rg_trash = \Modules\RefreshGlobal\Services\Trash::count(auth()->user()); @endphp
        <form method="POST" action="{{ route('refreshglobal.trash.empty') }}" class="rg-trash" data-rg-confirm="{{ __('refreshglobal::messages.trash_empty_confirm', ['count' => $rg_trash]) }}">
            {{ csrf_field() }}
            <button type="submit" class="btn btn-default btn-sm rg-trash-btn" @if (!$rg_trash) disabled @endif title="{{ __('refreshglobal::messages.trash_empty_help') }}">
                <i class="glyphicon glyphicon-trash"></i> {{ __('refreshglobal::messages.trash_empty_button', ['count' => $rg_trash]) }}
            </button>
        </form>
    @endif
    @include('refreshglobal::partials.language')
    @if ($is_admin)
        <a class="btn btn-default btn-sm rg-diag-link" href="{{ route('refreshglobal.diagnostic') }}"><i class="glyphicon glyphicon-check"></i> {{ __('refreshglobal::messages.diagnostic') }}</a>
    @endif
</div>
