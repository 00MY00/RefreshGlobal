{{-- Header of the flow pages (Http/Controllers/FlowsController.php): way back to "All mailboxes", tabs. $tab: stats | untreated --}}
<div class="rg-flows-head">
    <a class="btn btn-default rg-flows-back" href="{{ route('refreshglobal.tickets') }}"><i class="glyphicon glyphicon-arrow-left"></i> <span>{{ __('refreshglobal::messages.title') }}</span></a>
    <h1 class="rg-flows-title">{{ $tab === 'stats' ? __('refreshglobal::flows.stats_title') : __('refreshglobal::flows.untreated_title') }}</h1>
</div>
<nav class="rg-flows-tabs" aria-label="{{ __('refreshglobal::flows.section') }}">
    <a href="{{ route('refreshglobal.flows') }}" class="rg-flows-tab @if ($tab === 'stats') active @endif" @if ($tab === 'stats') aria-current="page" @endif>{{ __('refreshglobal::flows.stats_title') }}</a>
    <a href="{{ route('refreshglobal.flows.untreated') }}" class="rg-flows-tab @if ($tab === 'untreated') active @endif" @if ($tab === 'untreated') aria-current="page" @endif>{{ __('refreshglobal::flows.untreated_title') }}</a>
</nav>
