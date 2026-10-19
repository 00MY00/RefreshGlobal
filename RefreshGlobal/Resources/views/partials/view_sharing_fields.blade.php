{{--
    Visibility and sharing of a view (save form, "Share" form of the open view, management page).
    Parameters: $rg_f (prefix of the ids), $rg_vis (current visibility), $rg_users (ids shared with), $rg_editors (ids
    that may edit), $rg_dup (users may duplicate), $rg_scope (default for others), $rg_can_global, $is_admin,
    $rg_shareable (users that can be chosen). Stacked fields: usable on a phone.
--}}
@php
    $rg_users = $rg_users ?? [];
    $rg_editors = $rg_editors ?? [];
@endphp
<fieldset class="rg-vis" data-rg-vis>
    <legend class="rg-label">{{ __('refreshglobal::messages.view_visibility') }}</legend>
    <label class="rg-check"><input type="radio" name="visibility" value="private" @if ($rg_vis === 'private') checked @endif> {{ __('refreshglobal::messages.vis_private') }}</label>
    <label class="rg-check"><input type="radio" name="visibility" value="users" @if ($rg_vis === 'users') checked @endif> {{ __('refreshglobal::messages.vis_users') }}</label>
    @if ($rg_can_global || $rg_vis === 'global')
        <label class="rg-check"><input type="radio" name="visibility" value="global" @if ($rg_vis === 'global') checked @endif @if (!$rg_can_global) disabled @endif> {{ __('refreshglobal::messages.vis_global') }}</label>
    @endif
    <div class="rg-share-users" data-rg-show-for="users">
        <label class="rg-label" for="{{ $rg_f }}-ufilter">{{ __('refreshglobal::messages.view_users') }}</label>
        @if (count($rg_shareable))
            <input type="text" id="{{ $rg_f }}-ufilter" class="form-control input-sm rg-user-filter" placeholder="{{ __('refreshglobal::messages.view_users_search') }}" autocomplete="off">
            <div class="rg-user-list">
                @foreach ($rg_shareable as $rg_u)
                    <div class="rg-user-item" data-label="{{ mb_strtolower($rg_u->getFullName().' '.$rg_u->email) }}">
                        <label class="rg-check"><input type="checkbox" name="users[]" value="{{ $rg_u->id }}" @if (in_array((int) $rg_u->id, $rg_users, true)) checked @endif> {{ $rg_u->getFullName() }}</label>
                        @if ($is_admin)
                            <label class="rg-check rg-can-edit" title="{{ __('refreshglobal::messages.view_can_edit_help') }}"><input type="checkbox" name="can_edit[]" value="{{ $rg_u->id }}" @if (in_array((int) $rg_u->id, $rg_editors, true)) checked @endif> {{ __('refreshglobal::messages.view_can_edit') }}</label>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p class="rg-muted">{{ __('refreshglobal::messages.view_no_users') }}</p>
        @endif
    </div>
    <div data-rg-show-for="users global">
        <input type="hidden" name="allow_duplicate" value="0">
        <label class="rg-check"><input type="checkbox" name="allow_duplicate" value="1" @if ($rg_dup) checked @endif> {{ __('refreshglobal::messages.view_allow_duplicate') }}</label>
        @if ($is_admin)
            <label class="rg-label" for="{{ $rg_f }}-scope">{{ __('refreshglobal::messages.view_default_for') }}</label>
            <select id="{{ $rg_f }}-scope" name="default_scope" class="form-control input-sm">
                <option value="" @if ($rg_scope === '') selected @endif>{{ __('refreshglobal::messages.view_default_none') }}</option>
                <option value="shared" @if ($rg_scope === 'shared') selected @endif>{{ __('refreshglobal::messages.view_default_shared') }}</option>
                <option value="all" @if ($rg_scope === 'all') selected @endif>{{ __('refreshglobal::messages.view_default_all') }}</option>
            </select>
            <p class="rg-muted">{{ __('refreshglobal::messages.view_default_for_help') }}</p>
        @endif
    </div>
</fieldset>
