{{--
    Manage › Settings › RefreshGlobal (included by resources/views/settings/view.blade.php through the "settings.view"
    filter). The form posts to FreeScout's own settings route, which saves the values in its options table.
    Same markup as FreeScout's settings pages and Refresh's (Modules/Refresh/Resources/views/settings.blade.php).
--}}
@php
    $rg_refresh = \App\Module::isActive('refresh');
    $rg_s = '\Modules\RefreshGlobal\Services\Settings';
    $rg_need_refresh = $rg_refresh ? '' : __('refreshglobal::messages.refresh_required');
    $rg_trash = \Modules\RefreshGlobal\Services\Trash::count(auth()->user());
@endphp
<p class="margin-top">{{ __('refreshglobal::messages.settings_intro') }}</p>
<p class="rg-buttons">
    <a class="btn btn-default" href="{{ route('refreshglobal.tickets') }}"><i class="glyphicon glyphicon-inbox"></i> {{ __('refreshglobal::messages.open_page') }}</a>
    <a class="btn btn-default" href="{{ route('refreshglobal.diagnostic') }}"><i class="glyphicon glyphicon-check"></i> {{ __('refreshglobal::messages.diagnostic') }}</a>
</p>
<p class="form-help">
    {{ __('refreshglobal::messages.language_settings_info', ['language' => \Modules\RefreshGlobal\Http\Controllers\LanguageController::name(app()->getLocale())]) }}
</p>
<p class="rg-buttons">
    <a class="btn btn-default btn-sm" href="{{ route('users.profile', ['id' => auth()->id()]) }}"><i class="glyphicon glyphicon-globe"></i> {{ __('refreshglobal::messages.language_profile_link') }}</a>
    <a class="btn btn-default btn-sm" href="{{ route('settings', ['section' => 'general']) }}"><i class="glyphicon glyphicon-cog"></i> {{ __('refreshglobal::messages.language_default_link') }}</a>
</p>

<form class="form-horizontal margin-top margin-bottom" method="POST" action="" autocomplete="off">
    {{ csrf_field() }}

    <h3 class="subheader">{{ __('refreshglobal::messages.navigation') }}</h3>
    @include('refreshglobal::partials.switch', ['key' => $rg_s::REPLACE_REFRESH_TICKETS, 'label' => __('refreshglobal::messages.replace_tickets_label'),
        'help' => __('refreshglobal::messages.replace_tickets').'. '.__('refreshglobal::messages.replace_tickets_help'), 'extra_help' => $rg_need_refresh, 'disabled' => !$rg_refresh])
    {{-- works without Refresh too: then shown above FreeScout's dashboard (Services/GlobalDashboard.php, standalone) --}}
    @include('refreshglobal::partials.switch', ['key' => $rg_s::GLOBAL_DASHBOARD, 'label' => __('refreshglobal::messages.global_dashboard'),
        'help' => __('refreshglobal::messages.global_dashboard_help'), 'extra_help' => $rg_refresh ? '' : __('refreshglobal::messages.global_dashboard_standalone')])
    {{-- service-level targets of the dashboard (Services/Dashboard/Sla.php): Refresh's own settings apply when it is active --}}
    @php $rg_sla = '\Modules\RefreshGlobal\Services\Dashboard\Sla'; $rg_sla_refresh = $rg_sla::source() === 'refresh'; @endphp
    @foreach ([[$rg_sla::FIRST_RESPONSE, 'sla_first_response', $rg_sla::firstResponseHours()], [$rg_sla::RESOLUTION, 'sla_resolution', $rg_sla::resolutionHours()]] as $rg_sla_row)
        <div class="form-group">
            <label for="rg-{{ $rg_sla_row[1] }}" class="col-sm-2 control-label">{{ __('refreshglobal::messages.'.$rg_sla_row[1]) }}</label>
            <div class="col-sm-6">
                <div class="input-group" style="max-width: 220px;">
                    <input type="number" id="rg-{{ $rg_sla_row[1] }}" name="settings[{{ $rg_sla_row[0] }}]" value="{{ (int) ($settings[$rg_sla_row[0]] ?? $rg_sla_row[2]) }}" min="1" max="{{ $rg_sla::MAX_HOURS }}" step="1" class="form-control" @if ($rg_sla_refresh) disabled @endif>
                    <span class="input-group-addon">{{ __('refreshglobal::messages.sla_unit') }}</span>
                </div>
                @if ($loop->last)
                    <p class="form-help">{{ __('refreshglobal::messages.sla_help', ['overdue' => __('refreshglobal::messages.dash_overdue'), 'due_today' => __('refreshglobal::messages.dash_due_today'), 'sla' => __('refreshglobal::messages.dash_sla')]) }}@if ($rg_sla_refresh) {{ __('refreshglobal::messages.sla_from_refresh', ['first' => $rg_sla::firstResponseHours(), 'resolution' => $rg_sla::resolutionHours()]) }}@endif</p>
                @endif
            </div>
        </div>
    @endforeach
    @include('refreshglobal::partials.switch', ['key' => $rg_s::SHOW_MAILBOX, 'label' => __('refreshglobal::messages.show_mailbox'),
        'help' => __('refreshglobal::messages.show_mailbox_help')])
    <div class="form-group">
        <label for="rg-auto-refresh" class="col-sm-2 control-label">{{ __('refreshglobal::messages.auto_refresh') }}</label>
        <div class="col-sm-6">
            <div class="input-group" style="max-width: 220px;">
                <input type="number" id="rg-auto-refresh" name="settings[{{ $rg_s::AUTO_REFRESH }}]" value="{{ (int) ($settings[$rg_s::AUTO_REFRESH] ?? 0) }}" min="0" max="{{ $rg_s::AUTO_REFRESH_MAX }}" step="1" class="form-control">
                <span class="input-group-addon">{{ __('refreshglobal::messages.auto_refresh_unit') }}</span>
            </div>
            <p class="form-help">{{ __('refreshglobal::messages.auto_refresh_help', ['min' => $rg_s::AUTO_REFRESH_MIN]) }}</p>
        </div>
    </div>

    {{-- Remote images of the e-mails (tracking pixels), Services/RemoteImages.php --}}
    <h3 class="subheader">{{ __('refreshglobal::messages.remote_images_title') }}</h3>
    <div class="form-group">
        <label for="rg-remote-images" class="col-sm-2 control-label">{{ __('refreshglobal::messages.remote_images_default') }}</label>
        <div class="col-sm-6">
            <select id="rg-remote-images" name="settings[{{ $rg_s::REMOTE_IMAGES }}]" class="form-control">
                @foreach (['show', 'ask', 'block'] as $rg_mode)
                    <option value="{{ $rg_mode }}" @if (($settings[$rg_s::REMOTE_IMAGES] ?? 'ask') === $rg_mode) selected @endif>{{ __('refreshglobal::messages.remote_images_'.$rg_mode) }}</option>
                @endforeach
            </select>
            <p class="form-help">{{ __('refreshglobal::messages.remote_images_help') }}</p>
        </div>
    </div>
    <div class="form-group">
        <label for="rg-remote-images-rules" class="col-sm-2 control-label">{{ __('refreshglobal::messages.remote_images_rules') }}</label>
        <div class="col-sm-6">
            <textarea id="rg-remote-images-rules" name="settings[{{ $rg_s::REMOTE_IMAGES_RULES }}]" rows="5" maxlength="5000" class="form-control rg-mono" placeholder="classified_spam = block&#10;inbox = ask&#10;folder:clients-* = show">{{ $settings[$rg_s::REMOTE_IMAGES_RULES] ?? '' }}</textarea>
            <p class="form-help">{{ __('refreshglobal::messages.remote_images_rules_help') }}</p>
        </div>
    </div>

    {{-- E-mails of a ticket moved to a folder of the mail server, Services/MailMove.php --}}
    <h3 class="subheader">{{ __('refreshglobal::messages.move_settings_title') }}</h3>
    @include('refreshglobal::partials.switch', ['key' => $rg_s::MAIL_MOVE, 'label' => __('refreshglobal::messages.move_setting'),
        'help' => __('refreshglobal::messages.move_setting_help')])

    {{-- Saved views shared with everyone (Services/SavedViews.php) --}}
    <h3 class="subheader">{{ __('refreshglobal::messages.saved_views_title') }}</h3>
    @include('refreshglobal::partials.switch', ['key' => $rg_s::VIEWS_GLOBAL_ADMINS, 'label' => __('refreshglobal::messages.views_global_admins'),
        'help' => __('refreshglobal::messages.views_global_admins_help')])
    <div class="form-group">
        <div class="col-sm-6 col-sm-offset-2"><a href="{{ route('refreshglobal.views') }}">{{ __('refreshglobal::messages.manage_views') }}</a></div>
    </div>

    <h3 class="subheader">{{ __('refreshglobal::messages.ticket_deletion') }}</h3>
    @include('refreshglobal::partials.switch', ['key' => $rg_s::DELETE_GOES_NEXT, 'label' => __('refreshglobal::messages.delete_goes_next'),
        'help' => __('refreshglobal::messages.delete_goes_next_help')])
    @include('refreshglobal::partials.switch', ['key' => $rg_s::KEEP_POSITION, 'label' => __('refreshglobal::messages.keep_position'),
        'help' => __('refreshglobal::messages.keep_position_help')])
    @include('refreshglobal::partials.switch', ['key' => $rg_s::DELETE_PERMANENTLY, 'label' => __('refreshglobal::messages.delete_permanently'),
        'help' => __('refreshglobal::messages.delete_permanently_help')])

    <h3 class="subheader">{{ __('refreshglobal::messages.trash') }}</h3>
    <div class="form-group">
        <label for="rg-trash-days" class="col-sm-2 control-label">{{ __('refreshglobal::messages.trash_auto') }}</label>
        <div class="col-sm-6">
            <div class="input-group" style="max-width: 220px;">
                <input type="number" id="rg-trash-days" name="settings[{{ $rg_s::TRASH_AUTO_DAYS }}]" value="{{ (int) ($settings[$rg_s::TRASH_AUTO_DAYS] ?? 0) }}" min="0" max="{{ $rg_s::TRASH_AUTO_DAYS_MAX }}" step="1" class="form-control">
                <span class="input-group-addon">{{ __('refreshglobal::messages.trash_days') }}</span>
            </div>
            <p class="form-help">{{ __('refreshglobal::messages.trash_auto_help', ['time' => (string) config('refreshglobal.trash_auto_time', '03:45')]) }}</p>
        </div>
    </div>
    @include('refreshglobal::partials.switch', ['key' => $rg_s::SERVER_TRASH, 'label' => __('refreshglobal::messages.server_trash'),
        'help' => __('refreshglobal::messages.server_trash_help')])
    <div class="form-group">
        <label for="rg-server-trash-folder" class="col-sm-2 control-label">{{ __('refreshglobal::messages.server_trash_folder') }}</label>
        <div class="col-sm-6">
            <input type="text" id="rg-server-trash-folder" name="settings[{{ $rg_s::SERVER_TRASH_FOLDER }}]" value="{{ $settings[$rg_s::SERVER_TRASH_FOLDER] ?? '' }}" maxlength="200" class="form-control" style="max-width: 320px;" placeholder="{{ __('refreshglobal::messages.server_trash_folder_auto') }}">
            <p class="form-help">{{ __('refreshglobal::messages.server_trash_folder_help') }}</p>
            @php
                $rg_mt = \Modules\RefreshGlobal\Services\MailServerTrash::counts();
                $rg_mt_err = \Modules\RefreshGlobal\Services\MailServerTrash::lastError();
            @endphp
            @if (array_sum($rg_mt))
                <p class="form-help">{{ __('refreshglobal::messages.server_trash_status', [
                    'pending' => $rg_mt['pending'] ?? 0, 'moved' => $rg_mt['moved'] ?? 0,
                    'not_found' => $rg_mt['not_found'] ?? 0, 'failed' => ($rg_mt['failed'] ?? 0) + ($rg_mt['skipped'] ?? 0),
                ]) }}</p>
            @endif
            @if ($rg_mt_err && (($rg_mt['pending'] ?? 0) || ($rg_mt['failed'] ?? 0)))
                <p class="form-help text-danger">{{ __('refreshglobal::messages.server_trash_last_error', ['error' => $rg_mt_err->error]) }}</p>
            @endif
        </div>
    </div>
    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('refreshglobal::messages.trash_now') }}</label>
        <div class="col-sm-6">
            {{-- submits the separate form below (forms can not be nested) --}}
            <button type="submit" form="rg-trash-form" class="btn btn-danger" @if (!$rg_trash) disabled @endif>
                <i class="glyphicon glyphicon-trash"></i> {{ __('refreshglobal::messages.trash_empty_button', ['count' => $rg_trash]) }}
            </button>
            <p class="form-help">{{ __('refreshglobal::messages.trash_empty_help') }}</p>
        </div>
    </div>

    {{-- Origin tags of the fetched e-mails and spam left on the mail server (Services/MailTags.php) --}}
    @php
        $rg_tags_on = \Modules\RefreshGlobal\Services\MailTags::tableExists();
        $rg_recalc = \Modules\RefreshGlobal\Services\MailTags::recalcStatus();
        $rg_tags_module = \Modules\RefreshGlobal\Services\MailTags::tagsModule();
    @endphp
    <h3 class="subheader">{{ __('refreshglobal::messages.mail_tags_title') }}</h3>
    @include('refreshglobal::partials.switch', ['key' => $rg_s::MAIL_TAGS, 'label' => __('refreshglobal::messages.mail_tags_on'),
        'help' => __('refreshglobal::messages.mail_tags_on_help'),
        'extra_help' => $rg_tags_module ? __('refreshglobal::messages.mail_tags_module_on') : __('refreshglobal::messages.mail_tags_module_off')])
    @include('refreshglobal::partials.switch', ['key' => $rg_s::SKIP_SPAM, 'label' => __('refreshglobal::messages.skip_spam'),
        'help' => __('refreshglobal::messages.skip_spam_help')])
    @include('refreshglobal::partials.switch', ['key' => $rg_s::SHOW_MAIL_TAGS, 'label' => __('refreshglobal::messages.show_mail_tags'),
        'help' => __('refreshglobal::messages.show_mail_tags_help')])
    <div class="form-group">
        <label for="rg-mail-tags-folders" class="col-sm-2 control-label">{{ __('refreshglobal::messages.mail_tags_folders') }}</label>
        <div class="col-sm-6">
            <textarea id="rg-mail-tags-folders" name="settings[{{ $rg_s::MAIL_TAGS_FOLDERS }}]" rows="3" maxlength="5000" class="form-control" placeholder="Mes pourriels = spam&#10;Clients = customers">{{ $settings[$rg_s::MAIL_TAGS_FOLDERS] ?? '' }}</textarea>
            <p class="form-help">{{ __('refreshglobal::messages.mail_tags_folders_help') }}</p>
        </div>
    </div>
    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('refreshglobal::messages.mail_tags_recalc') }}</label>
        <div class="col-sm-6">
            {{-- submits the separate form below (forms can not be nested) --}}
            <button type="submit" form="rg-mail-tags-form" class="btn btn-default" @if (!$rg_tags_on || in_array($rg_recalc['state'], ['requested', 'running'], true)) disabled @endif>
                <i class="glyphicon glyphicon-tags"></i> {{ __('refreshglobal::messages.mail_tags_recalc') }}
            </button>
            <p class="form-help">{{ __('refreshglobal::messages.mail_tags_recalc_help') }}</p>
            @if ($rg_recalc['state'] === 'requested' || $rg_recalc['state'] === 'running')
                <p class="form-help text-warning">{{ __('refreshglobal::messages.mail_tags_recalc_'.$rg_recalc['state']) }}</p>
            @elseif ($rg_recalc['state'] === 'done' || $rg_recalc['state'] === 'failed')
                <p class="form-help @if ($rg_recalc['state'] === 'failed') text-danger @endif">{{ __('refreshglobal::messages.mail_tags_recalc_done', [
                    'tagged' => (int) ($rg_recalc['tagged'] ?? 0), 'seen' => (int) ($rg_recalc['seen'] ?? 0),
                    'time' => !empty($rg_recalc['finished_at']) ? \Carbon\Carbon::parse($rg_recalc['finished_at'])->format('Y-m-d H:i') : '',
                ]) }}@if (!empty($rg_recalc['error'])) — {{ $rg_recalc['error'] }}@endif</p>
            @endif
        </div>
    </div>

    <div class="form-group">
        <div class="col-sm-6 col-sm-offset-2">
            <button type="submit" class="btn btn-primary">{{ __('refreshglobal::messages.save') }}</button>
        </div>
    </div>
</form>

{{-- "Recalculate the tags" (button above): queued for FreeScout's scheduler --}}
<form id="rg-mail-tags-form" method="POST" action="{{ route('refreshglobal.mail_tags.recalc') }}">
    {{ csrf_field() }}
</form>

{{-- "Empty the trash now" (button above, in the settings form); confirmation asked by Public/js/shell.js --}}
<form id="rg-trash-form" method="POST" action="{{ route('refreshglobal.trash.empty') }}" data-rg-confirm="{{ __('refreshglobal::messages.trash_empty_confirm', ['count' => $rg_trash]) }}">
    {{ csrf_field() }}
    <input type="hidden" name="back" value="settings">
</form>

<div class="form-horizontal margin-bottom">
    <h3 class="subheader">{{ __('refreshglobal::messages.updates_title') }}</h3>
    <div class="form-group">
        <label class="col-sm-2 control-label">RefreshGlobal</label>
        <div class="col-sm-8 form-control-static">
            @include('refreshglobal::partials.updates', ['small' => false])
        </div>
    </div>
</div>
