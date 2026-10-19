{{--
    Open saved view, above the list: its name, whose it is / who sees it, "unsaved changes" when the filters were
    changed after opening it (Update view: only with the edit right; Save as new; Reset), and what the viewer gets
    without (mailbox deleted or not his, agent deleted, folder / tag no longer available).
--}}
@if ($active_view && $rg_view)
    <div class="rg-view-banner @if ($rg_view['modified']) rg-view-modified @endif" role="status">
        <span class="rg-vb-name" @if ($rg_view['modified']) title="{{ __('refreshglobal::messages.view_unsaved_help') }}" @endif>
            <span class="rg-vb-key">{{ __('refreshglobal::messages.view_label') }}</span>
            {{ $active_view->name }}@if ($rg_view['modified'])<span class="rg-vb-star" aria-hidden="true">*</span>@endif
        </span>
        <span class="rg-vb-vis rg-vb-vis-{{ $rg_view['visibility'] }}">{{ __('refreshglobal::messages.vis_'.$rg_view['visibility']) }}@if (!$rg_view['own'] && $rg_view['owner_name'] !== '') · {{ __('refreshglobal::messages.view_by', ['name' => $rg_view['owner_name']]) }}@endif</span>
        @if ($rg_view['modified'])
            <span class="rg-vb-unsaved">{{ __('refreshglobal::messages.view_unsaved') }}</span>
            <span class="rg-vb-actions">
                @if ($rg_view['can_edit'])
                    <form method="post" action="{{ route('refreshglobal.views.update', ['id' => $active_view->id]) }}" class="rg-inline rg-confirm"
                        @if ($rg_view['visibility'] !== 'private') data-confirm="{{ __('refreshglobal::messages.view_update_shared_confirm', ['name' => $active_view->name]) }}" @endif>
                        {{ csrf_field() }}
                        @include('refreshglobal::partials.view_filter_fields')
                        <button type="submit" class="btn btn-primary btn-xs">{{ $rg_view['visibility'] === 'private' ? __('refreshglobal::messages.view_update') : __('refreshglobal::messages.view_update_shared') }}</button>
                    </form>
                @endif
                <a href="#rg-save-view" class="btn btn-default btn-xs rg-save-as-new">{{ __('refreshglobal::messages.view_save_as_new') }}</a>
                <a href="{{ $rg_view['reset_url'] }}" class="btn btn-link btn-xs">{{ __('refreshglobal::messages.view_reset') }}</a>
            </span>
        @endif
    </div>
    @foreach ($rg_view['issues'] as $rg_issue => $rg_n)
        <p class="rg-muted rg-view-issue @if ($rg_issue === 'view_issue_mailbox_hidden') rg-dropped @endif">{{ trans_choice('refreshglobal::messages.'.$rg_issue, $rg_n, ['count' => $rg_n]) }}</p>
    @endforeach
@endif
