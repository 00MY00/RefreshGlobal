{{-- One check result in the mandatory format ([CODE] title / Expected / Effect / Action / In the meantime). --}}
@php
    $rg_M = '\Modules\RefreshGlobal\Services\Compatibility\Messages';
@endphp
<div class="rg-result rg-result-{{ $r['severity'] }}">
    <div class="rg-result-title"><code>[{{ $r['code'] }}]</code> {{ $rg_M::title($r) }}</div>
    <div><span class="rg-result-key">{{ __('refreshglobal::messages.expected') }}</span> {{ $r['expected'] }}</div>
    @if (!empty($r['details']))
        <div><span class="rg-result-key">{{ __('refreshglobal::messages.found') }}</span> {{ $r['details'] }}</div>
    @endif
    <div><span class="rg-result-key">{{ __('refreshglobal::messages.effect') }}</span> {{ $rg_M::effect($r) }}</div>
    <div><span class="rg-result-key">{{ __('refreshglobal::messages.action') }}</span> {{ $rg_M::action($r) }}</div>
    @if (!empty($fallback))
        <div><span class="rg-result-key">{{ __('refreshglobal::messages.meanwhile') }}</span> <a href="#rg-mailboxes">[{{ __('refreshglobal::messages.open_my_mailboxes') }}]</a></div>
    @endif
</div>
