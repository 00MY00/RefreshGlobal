{{--
    "My dashboard" of Refresh for ALL the user's mailboxes. Same markup and classes as Refresh's view
    (Modules/Refresh/Resources/views/dashboard.blade.php), so Refresh's stylesheet gives the same look; the chart and
    the number formats come from Refresh itself (Services\Dashboard::chart / delta / duration).
    Differences: figures over every mailbox; links lead to the "All mailboxes" page; undelivered e-mails per mailbox;
    "Mailbox" column in the list.
--}}
@php
    $D = '\Modules\Refresh\Services\Dashboard';
    $s = $stats['stats'];
    $kpis = [
        [__('refreshglobal::messages.dash_resolved'), $s['resolved'][0], $s['resolved'][1], (string) $s['resolved'][0], true],
        [__('refreshglobal::messages.dash_received'), $s['received'][0], $s['received'][1], (string) $s['received'][0], false],
        [__('refreshglobal::messages.dash_frt'), $s['frt'][0], $s['frt'][1], $D::duration($s['frt'][0]), false],
        [__('refreshglobal::messages.dash_sla'), $s['sla'][0], $s['sla'][1], $s['sla'][0] === null ? '--' : $s['sla'][0].'%', true],
    ];
    $rg_unresolved = route('refreshglobal.tickets', ['rv' => 'unresolved']);
@endphp
<link href="{{ asset(\Module::getPublicPath('refreshglobal').'/css/refreshglobal.css') }}?v={{ \Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker::moduleVersion() }}" rel="stylesheet" type="text/css">
<div class="rf-dash rg-dash" data-refresh="{{ \Modules\RefreshGlobal\Services\Settings::autoRefresh() }}" data-fp="{{ $rg_fp ?? '' }}" data-state-url="{{ route('refreshglobal.state', ['reset' => 1]) }}">
    <div class="rf-tiles">
        @foreach ($tiles as $tile)
            <a href="{{ $tile['url'] }}" class="rf-tile rf-tile-{{ $tile['key'] }} @if (!$tile['count']) rf-tile-zero @endif">
                <span class="rf-tile-label">{{ $tile['label'] }}</span>
                <span class="rf-tile-count">{{ $tile['count'] }}</span>
            </a>
        @endforeach
    </div>

    <div class="rf-card rf-trends">
        <div class="rf-trends-chart">
            <div class="rf-card-title">{{ __('refreshglobal::messages.dash_trends') }} · {{ __('refreshglobal::messages.title') }}</div>
            {!! $D::chart($stats['h_today'], $stats['h_yest'], $stats['now_h']) !!}
            <div class="rf-chart-legend"><span class="rf-lg-today">{{ __('refreshglobal::messages.dash_today') }}</span><span class="rf-lg-yest">{{ __('refreshglobal::messages.dash_yesterday') }}</span></div>
            <div class="rf-chart-axis">{{ __('refreshglobal::messages.dash_axis') }}</div>
        </div>
        <div class="rf-trends-kpis">
            @foreach ($kpis as $k)
                @php
                    list($dtxt, $dir) = $D::delta($k[1], $k[2]);
                    $good = $dir === null ? null : (($dir === 'up') === $k[4]);
                @endphp
                <div class="rf-kpi">
                    <div class="rf-kpi-label">{{ $k[0] }}</div>
                    <div class="rf-kpi-value">{{ $k[3] }}@if ($dtxt)<span class="rf-kpi-delta {{ $good ? 'good' : 'bad' }}"><i class="rf-kpi-arrow {{ $dir }}"></i>{{ $dtxt }}</span>@endif</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rf-widgets">
        @include('refreshglobal::partials.unresolved_card', ['by_agent' => $stats['by_agent']])
        <div class="rf-card rf-widget">
            <div class="rf-widget-head">
                <div><div class="rf-card-title">{{ __('refreshglobal::messages.dash_undelivered_title') }}</div><div class="rf-widget-sub">{{ __('refreshglobal::messages.title') }}</div></div>
            </div>
            <div class="rf-widget-row rf-widget-th"><span>{{ __('refreshglobal::messages.col_mailbox') }}</span><span>{{ __('refreshglobal::messages.dash_undelivered') }}</span></div>
            @forelse ($undelivered as $u)
                <a class="rf-widget-row" href="{{ $u['url'] }}"><span>{{ $u['name'] }}</span><span class="rf-widget-n">{{ $u['n'] }}</span></a>
            @empty
                <div class="rf-widget-row"><span>{{ __('refreshglobal::messages.title') }}</span><span class="rf-widget-n">0</span></div>
            @endforelse
        </div>
    </div>

    <div class="rf-card rf-dash-list">
        <div class="rf-dash-list-heading">
            <h3>{{ __('refreshglobal::messages.dash_unresolved_tickets') }} <small>{{ $conversations->total() }}</small></h3>
            <a href="{{ $rg_unresolved }}" class="rf-btn">{{ __('refreshglobal::messages.dash_view_all') }}</a>
        </div>
        @include('conversations/conversations_table', ['conversations' => $conversations, 'folder' => $folder, 'params' => []])
    </div>
</div>
