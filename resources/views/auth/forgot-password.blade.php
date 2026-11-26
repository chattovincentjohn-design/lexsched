@extends('layouts.app')
@section('title', 'Forgot password')
@section('content')
<div class="auth panel stack">
    <div><h1>Forgot your password?</h1><p class="muted">Enter the email you signed up with and we'll send a link to set a new password.</p></div>
    <form method="POST" action="{{ route('password.email') }}" class="stack">
        @csrf
        <x-field name="email" label="Email"><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"></x-field>
        <button class="btn primary block">Send reset link</button>
    </form>
    <p class="small muted"><a href="{{ route('login') }}">← Back to sign in</a></p>
</div>
@endsection
