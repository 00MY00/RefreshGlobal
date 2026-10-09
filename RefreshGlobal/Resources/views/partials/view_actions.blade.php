{{-- Saved views: save the current filters, rename / default / delete the open view. Plain forms + CSRF token. --}}
<div class="rg-view-actions">
    {{-- Phones: Refresh hides the list toolbar (Export button), the export stays reachable from the drawer --}}
    <a class="rg-m-only rg-m-export" href="{{ $rg_export }}">{{ __('refreshglobal::messages.export') }} (CSV)</a>
    @if (!$saved_views_ok)
        <p class="rg-muted">[RG-DB-05] {{ __('refreshglobal::messages.views_unavailable') }}</p>
    @else
        <details class="rg-details">
            <summary>{{ __('refreshglobal::messages.save_view') }}</summary>
            <form method="post" action="{{ route('refreshglobal.views.store') }}" class="rg-form">
                {{ csrf_field() }}
                @foreach ($filters['mailboxes'] as $id)<input type="hidden" name="mb[]" value="{{ $id }}">@endforeach
                @foreach ($filters['status'] as $code)<input type="hidden" name="status[]" value="{{ $code }}">@endforeach
                <input type="hidden" name="assignee" value="{{ $filters['assignee'] }}">
                <input type="hidden" name="q" value="{{ $filters['q'] }}">
                @if ($filters['rview'] !== '')<input type="hidden" name="rv" value="{{ $filters['rview'] }}">@endif
                <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                <input type="hidden" name="order" value="{{ $filters['order'] }}">
                <label class="rg-label" for="rg-view-name">{{ __('refreshglobal::messages.view_name') }}</label>
                <input type="text" id="rg-view-name" name="name" maxlength="100" required class="form-control input-sm">
                <label class="rg-check"><input type="checkbox" name="is_default" value="1"> {{ __('refreshglobal::messages.make_default') }}</label>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('refreshglobal::messages.save') }}</button>
            </form>
        </details>
        @if ($active_view)
            <details class="rg-details">
                <summary>{{ __('refreshglobal::messages.manage_view', ['name' => $active_view->name]) }}</summary>
                <form method="post" action="{{ route('refreshglobal.views.rename', ['id' => $active_view->id]) }}" class="rg-form">
                    {{ csrf_field() }}
                    <label class="rg-label" for="rg-view-rename">{{ __('refreshglobal::messages.rename') }}</label>
                    <input type="text" id="rg-view-rename" name="name" maxlength="100" required value="{{ $active_view->name }}" class="form-control input-sm">
                    <button type="submit" class="btn btn-default btn-sm">{{ __('refreshglobal::messages.rename') }}</button>
                </form>
                <form method="post" action="{{ route('refreshglobal.views.default', ['id' => $active_view->id]) }}" class="rg-form">
                    {{ csrf_field() }}
                    <button type="submit" class="btn btn-default btn-sm">{{ $active_view->is_default ? __('refreshglobal::messages.unset_default') : __('refreshglobal::messages.set_default') }}</button>
                </form>
                <form method="post" action="{{ route('refreshglobal.views.destroy', ['id' => $active_view->id]) }}" class="rg-form rg-confirm" data-confirm="{{ __('refreshglobal::messages.confirm_delete_view', ['name' => $active_view->name]) }}">
                    {{ csrf_field() }}
                    {{ method_field('DELETE') }}
                    <button type="submit" class="btn btn-link btn-sm rg-danger">{{ __('refreshglobal::messages.delete_view') }}</button>
                </form>
            </details>
        @endif
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
