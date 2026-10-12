{{-- The list is limited to an automatic folder of Workflow Packs: always shown, with the way out of the folder. --}}
@if ($filters['wfolder'] !== '')
    <div class="rg-folder-banner" role="status">
        {!! \Modules\RefreshGlobal\Services\AutoFolders::icon() !!}
        <span class="rg-folder-banner-text">{!! __('refreshglobal::messages.in_folder', ['folder' => '<strong>'.e($rg_folder_label).'</strong>', 'count' => (int) $conversations->total()]) !!}</span>
        <a class="rg-folder-banner-exit" href="{{ $rg_url([], ['wf', 'page']) }}">{{ __('refreshglobal::messages.leave_folder') }}</a>
    </div>
@endif
