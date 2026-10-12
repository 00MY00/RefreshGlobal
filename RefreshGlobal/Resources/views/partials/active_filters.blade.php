{{--
    Active filters, above the list, when the filters panel is hidden (folded, or not open over the list on a narrow
    screen): one removable chip per filter, "Edit" (opens the panel) and "Clear filters". Shown by CSS only.
--}}
@if ($filters_count)
    @php
        $rg_minus = function ($key, $value) use ($rg_base, $rg_url) {
            $left = array_values(array_filter((array) ($rg_base[$key] ?? []), function ($v) use ($value) {
                return (string) $v !== (string) $value;
            }));
            return $left ? $rg_url([$key => $left], ['page']) : $rg_url([], [$key, 'page']);
        };
        $rg_chips = [];
        if ($filters['q'] !== '') {
            $rg_chips[] = [__('refreshglobal::messages.search'), '« '.$filters['q'].' »', $rg_url([], ['q', 'page']), ''];
        }
        foreach ($filters['mailboxes'] as $rg_id) {
            $rg_mb = $mailboxes->first(function ($m) use ($rg_id) { return (int) $m->id === (int) $rg_id; });
            $rg_chips[] = [__('refreshglobal::messages.mailboxes'), $rg_mb ? $rg_mb->name : '#'.$rg_id, $rg_minus('mb', $rg_id), ''];
        }
        foreach ($filters['status'] as $rg_code) {
            $rg_chips[] = [__('refreshglobal::messages.status'), $rg_statuses[$rg_code] ?? $rg_code, $rg_minus('status', $rg_code), ''];
        }
        if ($filters['assignee'] !== '') {
            if ($filters['assignee'] === 'me') {
                $rg_who = __('refreshglobal::messages.me');
            } elseif ($filters['assignee'] === 'none') {
                $rg_who = __('refreshglobal::messages.unassigned');
            } else {
                $rg_u = $rg_users->first(function ($u) use ($filters) { return (string) $u->id === $filters['assignee']; });
                $rg_who = $rg_u ? $rg_u->getFullName() : '#'.$filters['assignee'];
            }
            $rg_chips[] = [__('refreshglobal::messages.assigned_to'), $rg_who, $rg_url([], ['assignee', 'page']), ''];
        }
        if ($filters['rview'] !== '') {
            $rg_chips[] = [__('refreshglobal::messages.refresh_view'), \Modules\RefreshGlobal\Services\GlobalDashboard::viewLabel($filters['rview']), $rg_url([], ['rv', 'page']), ''];
        }
        if ($filters['wfolder'] !== '') {
            $rg_chips[] = [__('refreshglobal::messages.auto_folder'), $rg_folder_label, $rg_url([], ['wf', 'page']), 'folder'];
        }
    @endphp
    <div class="rg-active-filters" role="region" aria-label="{{ __('refreshglobal::messages.active_filters') }}">
        <span class="rg-af-title"><i class="rf-i rf-i-filter"></i> {{ __('refreshglobal::messages.active_filters') }}</span>
        @foreach ($rg_chips as $rg_chip)
            <span class="rg-af-chip" title="{{ $rg_chip[0] }} : {{ $rg_chip[1] }}">
                <span class="rg-af-key">{{ $rg_chip[0] }}</span>
                @if ($rg_chip[3] === 'folder'){!! \Modules\RefreshGlobal\Services\AutoFolders::icon() !!}@endif
                <span class="rg-af-val">{{ $rg_chip[1] }}</span>
                <a href="{{ $rg_chip[2] }}" class="rg-af-x" title="{{ __('refreshglobal::messages.remove') }}" aria-label="{{ __('refreshglobal::messages.remove') }} : {{ $rg_chip[1] }}">&times;</a>
            </span>
        @endforeach
        <button type="button" class="rg-af-edit">{{ __('refreshglobal::messages.edit_filters') }}</button>
        <a href="{{ route('refreshglobal.tickets', ['reset' => 1]) }}" class="rg-af-clear">{{ __('refreshglobal::messages.clear_filters') }}</a>
    </div>
@endif
