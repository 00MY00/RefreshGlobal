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
        [__('Resolved'), $s['resolved'][0], $s['resolved'][1], (string) $s['resolved'][0], true],
        [__('Received'), $s['received'][0], $s['received'][1], (string) $s['received'][0], false],
        [__('Average first response time'), $s['frt'][0], $s['frt'][1], $D::duration($s['frt'][0]), false],
        [__('Resolved within SLA'), $s['sla'][0], $s['sla'][1], $s['sla'][0] === null ? '--' : $s['sla'][0].'%', true],
    ];
    $rg_unresolved = route('refreshglobal.tickets', ['rv' => 'unresolved']);
@endphp
<link href="{{ asset(\Module::getPublicPath('refreshglobal').'/css/refreshglobal.css') }}?v={{ \Modules\RefreshGlobal\Services\Compatibility\CompatibilityChecker::moduleVersion() }}" rel="stylesheet" type="text/css">
<div class="rf-dash rg-dash">
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
            <div class="rf-card-title">{{ __('Today\'s trends') }} · {{ __('refreshglobal::messages.title') }}</div>
            {!! $D::chart($stats['h_today'], $stats['h_yest'], $stats['now_h']) !!}
            <div class="rf-chart-legend"><span class="rf-lg-today">{{ __('Today') }}</span><span class="rf-lg-yest">{{ __('Yesterday') }}</span></div>
            <div class="rf-chart-axis">{{ __('Date created - Hour of the day') }}</div>
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
        <div class="rf-card rf-widget">
            <div class="rf-widget-head">
                <div><div class="rf-card-title">{{ __('Unresolved tickets') }}</div><div class="rf-widget-sub">{{ __('refreshglobal::messages.title') }}</div></div>
                <a href="{{ $rg_unresolved }}">{{ __('View details') }}</a>
            </div>
            <div class="rf-widget-row rf-widget-th"><span>{{ __('Agent') }}</span><span>{{ __('refresh::labels.open') }}</span></div>
            @forelse ($stats['by_agent'] as $a)
                <a class="rf-widget-row" href="{{ $a['url'] }}"><span>{{ $a['name'] }}</span><span class="rf-widget-n">{{ $a['n'] }}</span></a>
            @empty
                <div class="rf-widget-row"><span class="text-help">{{ __('No unresolved tickets') }}</span></div>
            @endforelse
        </div>
        <div class="rf-card rf-widget">
            <div class="rf-widget-head">
                <div><div class="rf-card-title">{{ __('Undelivered e-mails') }}</div><div class="rf-widget-sub">{{ __('refreshglobal::messages.title') }}</div></div>
            </div>
            <div class="rf-widget-row rf-widget-th"><span>{{ __('refreshglobal::messages.col_mailbox') }}</span><span>{{ __('Undelivered') }}</span></div>
            @forelse ($undelivered as $u)
                <a class="rf-widget-row" href="{{ $u['url'] }}"><span>{{ $u['name'] }}</span><span class="rf-widget-n">{{ $u['n'] }}</span></a>
            @empty
                <div class="rf-widget-row"><span>{{ __('refreshglobal::messages.title') }}</span><span class="rf-widget-n">0</span></div>
            @endforelse
        </div>
    </div>

    <div class="rf-card rf-dash-list">
        <div class="rf-dash-list-heading">
            <h3>{{ __('Unresolved tickets') }} <small>{{ $conversations->total() }}</small></h3>
            <a href="{{ $rg_unresolved }}" class="rf-btn">{{ __('View all tickets') }}</a>
        </div>
        @include('conversations/conversations_table', ['conversations' => $conversations, 'folder' => $folder, 'params' => []])
    </div>
</div>
