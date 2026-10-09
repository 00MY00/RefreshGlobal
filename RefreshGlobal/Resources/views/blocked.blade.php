{{-- State "blocking": the list is NOT shown (no risk of showing an unauthorised ticket). Explicit message + native mailboxes. --}}
@extends('layouts.app')

@section('title', __('refreshglobal::messages.title'))

@section('body_attrs')@parent data-rg-page="blocked"@endsection

@section('stylesheets')
    @parent
    <link href="{{ asset(\Module::getPublicPath('refreshglobal').'/css/refreshglobal.css') }}" rel="stylesheet" type="text/css">
@endsection

@section('content')
    <div class="container rg-blocked">
        <div class="alert alert-danger">
            <strong>{{ __('refreshglobal::messages.blocked_title') }}</strong>
            @if ($is_admin)
                @foreach ($failed as $r)
                    @include('refreshglobal::partials.result', ['r' => $r, 'fallback' => true])
                @endforeach
                @if ($diagnostic)
                    <p><a href="{{ $diagnostic }}">{{ __('refreshglobal::messages.open_diagnostic') }}</a></p>
                @endif
            @else
                <p>{{ __('refreshglobal::messages.blocked_short') }}</p>
                <p class="rg-muted">{{ implode(', ', array_map(function ($r) { return $r['code']; }, $failed)) }}</p>
            @endif
        </div>
        @include('refreshglobal::partials.mailbox_links')
    </div>
@endsection
