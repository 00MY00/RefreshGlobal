<div id="rg-mailboxes" class="rg-mailbox-links">
    <h3>{{ __('refreshglobal::messages.open_my_mailboxes') }}</h3>
    @if ($mailboxes)
        <ul>
            @foreach ($mailboxes as $mb)
                <li><a href="{{ $mb['url'] }}">{{ $mb['name'] }}</a></li>
            @endforeach
        </ul>
    @else
        <p><a href="{{ $home_url }}">{{ __('refreshglobal::messages.dashboard') }}</a></p>
    @endif
</div>
