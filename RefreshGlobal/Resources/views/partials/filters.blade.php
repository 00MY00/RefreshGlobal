{{-- Filters (GET: the filtered list has its own address, can be bookmarked or shared, Back works). --}}
@php
    $rg_is_refresh = $skin === 'refresh';
@endphp
<form class="{{ $form_class }}" id="rg-filters" method="get" action="{{ route('refreshglobal.tickets') }}">
    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
    <input type="hidden" name="order" value="{{ $filters['order'] }}">
    @if ($rg_is_refresh)
        <div class="rf-filters-head">
            <span class="rf-filters-title">{{ __('refreshglobal::messages.filters') }}</span>
            @if ($filters_count)<a href="{{ route('refreshglobal.tickets', ['reset' => 1]) }}" class="rf-filters-reset">{{ __('refreshglobal::messages.clear_filters') }}</a>@endif
        </div>
    @endif
    <div class="@if ($rg_is_refresh) rf-filters-body @else rg-filters-row @endif">
        <div class="@if ($rg_is_refresh) rf-f @else form-group rg-f @endif">
            @if ($rg_is_refresh)
                <div class="rf-search"><i class="rf-i rf-i-search"></i><input type="text" name="q" value="{{ $filters['q'] }}" maxlength="200" placeholder="{{ __('refreshglobal::messages.search_placeholder') }}" class="rf-f-q"></div>
            @else
                <label for="rg-q">{{ __('refreshglobal::messages.search') }}</label>
                <input type="text" id="rg-q" name="q" value="{{ $filters['q'] }}" maxlength="200" placeholder="{{ __('refreshglobal::messages.search_placeholder') }}" class="form-control">
            @endif
        </div>
        <div class="@if ($rg_is_refresh) rf-f @else form-group rg-f @endif">
            <label for="rg-mb">{{ __('refreshglobal::messages.mailboxes') }}</label>
            <select id="rg-mb" name="mb[]" multiple class="@if ($rg_is_refresh) rf-multi @else form-control @endif rg-multi" data-placeholder="{{ __('refreshglobal::messages.any_mailbox') }}">
                @foreach ($mailboxes as $mb)
                    <option value="{{ $mb->id }}" {{ $rg_sel($filters['mailboxes'], $mb->id) }}>{{ $mb->name }}@if (isset($counts_by_mailbox[$mb->id])) ({{ $counts_by_mailbox[$mb->id] }})@endif</option>
                @endforeach
            </select>
        </div>
        <div class="@if ($rg_is_refresh) rf-f @else form-group rg-f @endif">
            <label for="rg-status">{{ __('refreshglobal::messages.status') }}</label>
            <select id="rg-status" name="status[]" multiple class="@if ($rg_is_refresh) rf-multi @else form-control @endif rg-multi" data-placeholder="{{ __('refreshglobal::messages.any_status_but_spam') }}">
                @foreach ($rg_statuses as $code => $label)
                    <option value="{{ $code }}" {{ $rg_sel($filters['status'], $code) }}>{{ $label }}@if (isset($counts_by_status[$code])) ({{ $counts_by_status[$code] }})@endif</option>
                @endforeach
            </select>
        </div>
        <div class="@if ($rg_is_refresh) rf-f @else form-group rg-f @endif">
            <label for="rg-assignee">{{ __('refreshglobal::messages.assigned_to') }}</label>
            <select id="rg-assignee" name="assignee" class="@if ($rg_is_refresh) rf-select @else form-control @endif">
                <option value="" @if ($filters['assignee'] === '') selected @endif>{{ __('refreshglobal::messages.anyone') }}</option>
                <option value="me" @if ($filters['assignee'] === 'me') selected @endif>{{ __('refreshglobal::messages.me') }}</option>
                @if (!$only_assigned)
                    <option value="none" @if ($filters['assignee'] === 'none') selected @endif>{{ __('refreshglobal::messages.unassigned') }}</option>
                    @foreach ($rg_users as $u)
                        <option value="{{ $u->id }}" @if ($filters['assignee'] === (string) $u->id) selected @endif>{{ $u->getFullName() }}</option>
                    @endforeach
                @endif
            </select>
        </div>
        @if (!$rg_is_refresh)
            <div class="form-group rg-f">
                <label for="rg-sort">{{ __('refreshglobal::messages.sort_by') }}</label>
                <select id="rg-sort" class="form-control rg-sort-select">
                    @foreach ($sorts as $key => $label)
                        <option value="{{ $key }}" @if ($key === $filters['sort']) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
                <select id="rg-order" class="form-control rg-order-select">
                    <option value="desc" @if ($filters['order'] === 'desc') selected @endif>{{ __('refreshglobal::messages.descending') }}</option>
                    <option value="asc" @if ($filters['order'] === 'asc') selected @endif>{{ __('refreshglobal::messages.ascending') }}</option>
                </select>
            </div>
        @endif
    </div>
    <div class="@if ($rg_is_refresh) rf-filters-foot @else rg-filters-foot @endif">
        <button type="submit" class="@if ($rg_is_refresh) rf-btn-primary @else btn btn-primary @endif">{{ __('refreshglobal::messages.apply') }}</button>
        @if (!$rg_is_refresh && $filters_count)
            <a href="{{ route('refreshglobal.tickets', ['reset' => 1]) }}" class="btn btn-link">{{ __('refreshglobal::messages.clear_filters') }}</a>
        @endif
    </div>
</form>
