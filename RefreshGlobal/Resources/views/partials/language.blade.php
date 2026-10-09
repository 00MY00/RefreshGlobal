{{--
    Language switch: the user's FreeScout language (the one Refresh follows too). Options from FreeScout's own list
    (resources/views/partials/locale_options.blade.php); the form submits on change (Public/js/refreshglobal.js).
--}}
@if (view()->exists('partials/locale_options'))
    <form method="post" action="{{ route('refreshglobal.language') }}" class="rg-language" title="{{ __('refreshglobal::messages.language_help') }}">
        {{ csrf_field() }}
        <label for="rg-language" class="rg-language-label">{{ __('refreshglobal::messages.language') }}</label>
        <select id="rg-language" name="locale" class="form-control input-sm rg-language-select">
            @include('partials/locale_options', ['selected' => app()->getLocale()])
        </select>
        <noscript><button type="submit" class="btn btn-default btn-sm">{{ __('refreshglobal::messages.apply') }}</button></noscript>
    </form>
@endif
