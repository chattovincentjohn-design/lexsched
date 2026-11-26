@extends('layouts.app')
@section('title', 'Sign in')
@section('content')
<div class="auth panel stack">
    <div><h1>Sign in</h1><p class="muted">Clients, staff and lawyers all sign in here.</p></div>
    <form method="POST" action="{{ route('login') }}" class="stack">
        @csrf
        <x-field name="email" label="Email"><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"></x-field>
        <x-field name="password" label="Password"><input id="password" name="password" type="password" required autocomplete="current-password"></x-field>
        <div class="row-flex between"><label class="chk"><input type="checkbox" name="remember" value="1"> Keep me signed in</label><a class="small" href="{{ route('password.request') }}">Forgot password?</a></div>
        <button class="btn primary block">Sign in</button>
    </form>
    <p class="small muted">New client? <a href="{{ route('register') }}">Create an account</a> to book a consultation.</p>
</div>
@endsection
