{{-- Compatibility banner (state degraded / warning) and discreet notes about the filters. --}}
@php
    $rg_degraded = array_values(array_filter($notices, function ($r) { return $r['severity'] === 'degraded'; }));
@endphp
@if ($is_admin && $notices)
    <div class="alert @if ($rg_degraded) alert-warning @else alert-info @endif rg-notice">
        <strong>{{ $rg_degraded ? __('refreshglobal::messages.degraded_title') : __('refreshglobal::messages.warning_title') }}</strong>
        @foreach ($notices as $r)
            @include('refreshglobal::partials.result', ['r' => $r, 'fallback' => false])
        @endforeach
        <a href="{{ route('refreshglobal.diagnostic') }}">{{ __('refreshglobal::messages.open_diagnostic') }}</a>
    </div>
@elseif ($rg_degraded)
    <div class="alert alert-warning rg-notice">
        {{ __('refreshglobal::messages.degraded_short') }}
    </div>
@endif
@if ($dropped_mailboxes)
    <p class="rg-muted rg-dropped">{{ trans_choice('refreshglobal::messages.dropped_mailboxes', $dropped_mailboxes, ['count' => $dropped_mailboxes]) }}</p>
@endif
@if ($ignored_params)
    <p class="rg-muted">{{ __('refreshglobal::messages.ignored_params') }}</p>
@endif
