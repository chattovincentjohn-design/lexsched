<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'LexSched') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Serif:wght@500;600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body>
<header class="top">
    <div class="wrap">
        <a class="brand" href="{{ route('home') }}">
            <div class="seal" aria-hidden="true">§</div>
            <div><b>LexSched</b><span>{{ config('lexsched.office_name') }}</span></div>
        </a>
        @auth
            @php($role = auth()->user()->role)
            <nav class="nav" aria-label="Main">
                @if ($role === \App\Enums\UserRole::Client)
                    <a href="{{ route('client.book') }}" @class(['on' => request()->routeIs('client.book*')])>Book</a>
                    <a href="{{ route('client.appointments.index') }}" @class(['on' => request()->routeIs('client.appointments.*')])>My appointments</a>
                @endif
                @if (in_array($role, [\App\Enums\UserRole::Secretary, \App\Enums\UserRole::Admin], true))
                    <a href="{{ route('staff.appointments.index') }}" @class(['on' => request()->routeIs('staff.appointments.*', 'staff.walk-in.*', 'staff.calendar')])>Appointments</a>
                    <a href="{{ route('staff.register') }}" @class(['on' => request()->routeIs('staff.register*')])>Notarial register</a>
                @endif
                @if ($role === \App\Enums\UserRole::Lawyer)
                    <a href="{{ route('lawyer.schedule') }}" @class(['on' => request()->routeIs('lawyer.*')])>My schedule</a>
                @endif
                @if ($role === \App\Enums\UserRole::Admin)
                    <a href="{{ route('admin.dashboard') }}" @class(['on' => request()->routeIs('admin.dashboard')])>Overview</a>
                    <a href="{{ route('admin.services.index') }}" @class(['on' => request()->routeIs('admin.services.*')])>Services &amp; fees</a>
                    <a href="{{ route('admin.holidays.index') }}" @class(['on' => request()->routeIs('admin.holidays.*')])>Holidays</a>
                    <a href="{{ route('admin.users.index') }}" @class(['on' => request()->routeIs('admin.users.*')])>Users</a>
                    <a href="{{ route('admin.audit') }}" @class(['on' => request()->routeIs('admin.audit')])>Audit log</a>
                @endif
            </nav>
            <div class="who">
                <div><a href="{{ route('profile.edit') }}" style="color:inherit;text-decoration:none"><b>{{ auth()->user()->name }}</b></a><br><span class="role">{{ $role->label() }} · <a href="{{ route('profile.edit') }}">Profile</a></span></div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="linkbtn">Sign out</button></form>
            </div>
        @else
            <nav class="nav">
                <a href="{{ route('login') }}">Sign in</a>
                <a class="btn primary sm" href="{{ route('register') }}">Book a consultation</a>
            </nav>
        @endauth
    </div>
</header>
<main>
    <div class="wrap">
        @if (session('status'))
            <div class="alert ok" role="status">{{ session('status') }}</div>
        @endif
        @error('action')
            <div class="alert bad" role="alert">{{ $message }}</div>
        @enderror
        @yield('content')
    </div>
</main>
<script src="{{ asset('js/app.js') }}?v=1" defer></script>
@stack('scripts')
</body>
</html>
