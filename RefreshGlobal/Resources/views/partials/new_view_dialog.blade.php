{{--
    1.22.0: "New view" dialog of the "All mailboxes" page, opened by every ".rg-new-view-open" button ("+ New view" of
    "My views", "Save as a view" of the active filters bar, "Save as new" of an open view, the management page through
    #rg-new-view). A view keeps the filters SHOWN (partials/view_filter_fields): their summary is listed ($rg_chips of
    tickets.blade.php), "Change the filters first" opens the filters panel. Only the name is required; sharing is
    folded. Posted to refreshglobal.views.store (rights checked there, Services/SavedViews.php).
    In the content (not the views panel): the phone drawer of Refresh would clip a fixed dialog.
--}}
@if ($saved_views_ok)
    <div class="rg-dialog" id="rg-new-view" hidden>
        <div class="rg-dialog-panel" role="dialog" aria-modal="true" aria-labelledby="rg-new-view-title">
            <div class="rg-dialog-head">
                <h2 class="rg-dialog-title" id="rg-new-view-title">{{ __('refreshglobal::messages.new_view') }}</h2>
                <button type="button" class="rg-dialog-x" data-rg-dialog-close aria-label="{{ __('refreshglobal::messages.cancel') }}">&times;</button>
            </div>
            <form method="post" action="{{ route('refreshglobal.views.store') }}" class="rg-form rg-view-form rg-new-view-form">
                {{ csrf_field() }}
                @include('refreshglobal::partials.view_filter_fields')
                <p class="rg-muted rg-new-view-help">{{ __('refreshglobal::messages.new_view_help') }}</p>
                <div class="rg-new-view-filters">
                    <div class="rg-label">{{ __('refreshglobal::messages.new_view_filters') }}</div>
                    @if ($rg_chips || $rg_default_status)
                        <ul class="rg-new-view-chips">
                            @if ($rg_default_status)
                                <li><span class="rg-af-key">{{ __('refreshglobal::messages.status') }}</span> {{ implode(', ', array_map(function ($c) use ($rg_statuses) { return $rg_statuses[$c] ?? $c; }, $filters['status'])) }}</li>
                            @endif
                            @foreach ($rg_chips as $rg_chip)
                                <li><span class="rg-af-key">{{ $rg_chip[0] }}</span> {{ $rg_chip[1] }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="rg-new-view-none">{{ __('refreshglobal::messages.new_view_no_filters') }}</p>
                    @endif
                    <button type="button" class="btn btn-link btn-sm rg-new-view-edit">{{ __('refreshglobal::messages.new_view_edit_filters') }}</button>
                </div>
                <label class="rg-label" for="rg-view-name">{{ __('refreshglobal::messages.view_name') }}</label>
                <input type="text" id="rg-view-name" name="name" maxlength="100" required class="form-control" autocomplete="off"
                    placeholder="{{ __('refreshglobal::messages.new_view_name_hint') }}"
                    @if ($active_view && !empty($rg_view['modified'])) value="{{ __('refreshglobal::messages.view_copy_name', ['name' => $active_view->name]) }}" @endif>
                <label class="rg-check"><input type="checkbox" name="is_default" value="1"> {{ __('refreshglobal::messages.make_default') }}</label>
                <details class="rg-details rg-new-view-share">
                    <summary>{{ __('refreshglobal::messages.new_view_sharing') }}</summary>
                    @include('refreshglobal::partials.view_sharing_fields', ['rg_f' => 'rg-sv-new', 'rg_vis' => 'private', 'rg_users' => [], 'rg_editors' => [],
                        'rg_dup' => true, 'rg_scope' => '', 'rg_can_global' => \Modules\RefreshGlobal\Services\SavedViews::canPublishGlobal(auth()->user()), 'rg_shareable' => $rg_shareable])
                </details>
                <div class="rg-dialog-actions">
                    <button type="submit" class="btn btn-primary rg-new-view-save">{{ __('refreshglobal::messages.save') }}</button>
                    <button type="button" class="btn btn-default" data-rg-dialog-close>{{ __('refreshglobal::messages.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
@endif
