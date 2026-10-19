{{-- Dashboard, "Unresolved tickets" card: $by_agent = GlobalDashboard::unresolvedByAgent() (older rows with "n" only still shown) --}}
<div class="rf-card rf-widget">
    <div class="rf-widget-head">
        <div><div class="rf-card-title">{{ __('Unresolved tickets') }}</div><div class="rf-widget-sub">{{ __('refreshglobal::messages.title') }}</div></div>
        <a href="{{ $rg_unresolved }}">{{ __('View details') }}</a>
    </div>
    {{-- open and pending apart, sorted by their total (GlobalDashboard::unresolvedByAgent(), like Refresh 1.5.0) --}}
    @php $rg_open = __('refresh::labels.open'); $rg_pending = __('Pending'); @endphp
    <div class="rf-widget-row rf-widget-th rg-unres-th"><span>{{ __('Agent') }}</span><span class="rg-widget-cols"><span>{{ $rg_open }}</span><span>{{ $rg_pending }}</span></span></div>
    @forelse ($by_agent as $a)
        <div class="rf-widget-row rg-unres-row">
            <a class="rg-unres-name" href="{{ $a['url'] }}">{{ $a['name'] }}</a>
            <span class="rg-widget-cols">
                <a class="rf-widget-n" href="{{ $a['url_open'] ?? $a['url'] }}" data-label="{{ $rg_open }}" title="{{ $a['name'] }} · {{ $rg_open }}">{{ $a['open'] ?? $a['n'] }}</a>
                <a class="rf-widget-n" href="{{ $a['url_pending'] ?? $a['url'] }}" data-label="{{ $rg_pending }}" title="{{ $a['name'] }} · {{ $rg_pending }}">{{ $a['pending'] ?? 0 }}</a>
            </span>
        </div>
    @empty
        <div class="rf-widget-row"><span class="text-help">{{ __('No unresolved tickets') }}</span></div>
    @endforelse
</div>
