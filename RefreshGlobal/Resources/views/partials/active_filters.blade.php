{{--
    Active filters, above the list, when the filters panel is hidden (folded, or not open over the list on a narrow
    screen): one removable chip per filter ($rg_chips, built by tickets.blade.php), "Edit" (opens the panel), "Save as a
    view" and "Clear filters". Shown by CSS only.
--}}
@if ($filters_count)
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
        @if ($saved_views_ok && (!$active_view || ($rg_view && $rg_view['modified'])))
            {{-- 1.22.0: the filters shown, saved as a view (Resources/views/partials/new_view_dialog.blade.php) --}}
            <button type="button" class="rg-af-save rg-new-view-open">{{ __('refreshglobal::messages.save_as_view') }}</button>
        @endif
        <a href="{{ route('refreshglobal.tickets', ['reset' => 1]) }}" class="rg-af-clear">{{ __('refreshglobal::messages.clear_filters') }}</a>
    </div>
@endif
