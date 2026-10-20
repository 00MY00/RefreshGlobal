{{--
    Diagnostics: FreeScout Interop (DiagnosticController::interop(), Services/Interop/PeerStatus.php). The module's own
    contract (interop.json) and how it cooperates with Refresh here: native (Refresh speaks the contract), legacy (the
    documented DOM fallbacks of Config/integration.php), standalone (Refresh absent) or unavailable.
--}}
@php
    $rg_p = $interop['refresh'] ?? null;
    $rg_label = function ($value) {
        return ['native' => 'label-success', 'legacy' => 'label-warning', 'standalone' => 'label-info', 'unavailable' => 'label-default',
            'COMPATIBLE' => 'label-success', 'DEGRADED' => 'label-warning', 'UNKNOWN' => 'label-default', 'INCOMPATIBLE' => 'label-danger'][$value] ?? 'label-default';
    };
@endphp
<div class="panel panel-default rg-update rg-interop">
    <div class="panel-body">
        <h4 class="rg-update-title">{{ __('refreshglobal::messages.interop_title') }}</h4>
        <p class="rg-muted">{{ __('refreshglobal::messages.interop_help') }}</p>
        <table class="table table-condensed rg-interop-table">
            <tbody>
                <tr>
                    <th>{{ __('refreshglobal::messages.interop_protocol') }}</th>
                    <td>
                        {{ $interop['protocol'] ?: '—' }}
                        @if ($interop['errors'])
                            <span class="label label-danger">{{ __('refreshglobal::messages.interop_manifest_errors') }}</span>
                        @else
                            <span class="label label-success">{{ __('refreshglobal::messages.interop_manifest_ok') }}</span>
                        @endif
                    </td>
                </tr>
                @foreach ($interop['errors'] as $rg_err)
                    <tr><td></td><td class="text-danger">{{ $rg_err }}</td></tr>
                @endforeach
                @if ($rg_p)
                    <tr>
                        <th>Refresh</th>
                        <td>{{ $rg_p['installed'] ? ($rg_p['version'] !== '' ? $rg_p['version'] : '?') : __('refreshglobal::messages.interop_not_installed') }}@if ($rg_p['installed'] && !$rg_p['active']) ({{ __('refreshglobal::messages.off') }})@endif</td>
                    </tr>
                    <tr>
                        <th>{{ __('refreshglobal::messages.interop_peer_protocol') }}</th>
                        <td>{{ $rg_p['protocol'] !== '' ? $rg_p['protocol'] : __('refreshglobal::messages.interop_not_available') }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('refreshglobal::messages.interop_mode') }}</th>
                        <td><span class="label {{ $rg_label($rg_p['mode']) }}">{{ __('refreshglobal::messages.interop_mode_'.$rg_p['mode']) }}</span></td>
                    </tr>
                    <tr>
                        <th>{{ __('refreshglobal::messages.interop_compatibility') }}</th>
                        <td><span class="label {{ $rg_label($rg_p['compatibility']) }}">{{ $rg_p['compatibility'] }}</span></td>
                    </tr>
                    @foreach ($rg_p['capabilities'] as $rg_cap => $rg_state)
                        <tr>
                            <th class="rg-interop-cap">{{ $rg_cap }}</th>
                            <td><span class="label {{ $rg_label($rg_state) }}">{{ __('refreshglobal::messages.interop_mode_'.$rg_state) }}</span></td>
                        </tr>
                    @endforeach
                    <tr>
                        <th>{{ __('refreshglobal::messages.interop_legacy_fallbacks') }}</th>
                        <td>{{ $rg_p['legacy_fallbacks'] ? __('refreshglobal::messages.on') : __('refreshglobal::messages.off') }}</td>
                    </tr>
                    @foreach ($rg_p['reasons'] as $rg_reason)
                        <tr><td></td><td class="rg-muted">{{ $rg_reason }}</td></tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
</div>
