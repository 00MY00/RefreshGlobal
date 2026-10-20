{{-- Dashboard, "Unresolved tickets" card: $by_agent = GlobalDashboard::unresolvedByAgent() (older rows with "n" only still shown) --}}
<div class="rf-card rf-widget">
    <div class="rf-widget-head">
        <div><div class="rf-card-title">{{ __('refreshglobal::messages.dash_unresolved_tickets') }}</div><div class="rf-widget-sub">{{ __('refreshglobal::messages.title') }}</div></div>
        <a href="{{ $rg_unresolved }}">{{ __('refreshglobal::messages.dash_view_details') }}</a>
    </div>
    {{-- open and pending apart, sorted by their total (GlobalDashboard::unresolvedByAgent(), like Refresh 1.5.0) --}}
    @php $rg_open = __('refreshglobal::messages.dash_open'); $rg_pending = __('refreshglobal::messages.dash_pending'); @endphp
    <div class="rf-widget-row rf-widget-th rg-unres-th"><span>{{ __('refreshglobal::messages.dash_agent') }}</span><span class="rg-widget-cols"><span>{{ $rg_open }}</span><span>{{ $rg_pending }}</span></span></div>
    @forelse ($by_agent as $a)
        <div class="rf-widget-row rg-unres-row">
            <a class="rg-unres-name" href="{{ $a['url'] }}">{{ $a['name'] }}</a>
            <span class="rg-widget-cols">
                <a class="rf-widget-n" href="{{ $a['url_open'] ?? $a['url'] }}" data-label="{{ $rg_open }}" title="{{ $a['name'] }} · {{ $rg_open }}">{{ $a['open'] ?? $a['n'] }}</a>
                <a class="rf-widget-n" href="{{ $a['url_pending'] ?? $a['url'] }}" data-label="{{ $rg_pending }}" title="{{ $a['name'] }} · {{ $rg_pending }}">{{ $a['pending'] ?? 0 }}</a>
            </span>
        </div>
    @empty
        <div class="rf-widget-row"><span class="text-help">{{ __('refreshglobal::messages.dash_no_unresolved') }}</span></div>
    @endforelse
</div>
