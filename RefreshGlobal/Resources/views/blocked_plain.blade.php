{{-- Last resort when even FreeScout's layout is unavailable (RG-VIEW-01): self-contained page, no external file. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('refreshglobal::messages.title') }}</title>
</head>
<body style="font-family: sans-serif; max-width: 760px; margin: 32px auto; padding: 0 16px; color: #1e293b;">
    <h1 style="font-size: 20px;">{{ __('refreshglobal::messages.blocked_title') }}</h1>
    @foreach ($failed as $r)
        @if ($is_admin)
            <pre style="white-space: pre-wrap; background: #fef2f2; border: 1px solid #fecaca; padding: 12px;">{{ \Modules\RefreshGlobal\Services\Compatibility\Messages::block($r) }}</pre>
        @endif
    @endforeach
    @if (!$is_admin)
        <p>{{ __('refreshglobal::messages.blocked_short') }}</p>
    @endif
    @include('refreshglobal::partials.mailbox_links')
</body>
</html>
