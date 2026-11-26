@extends('layouts.app')
@section('title', 'Set a new password')
@section('content')
<div class="auth panel stack">
    <div><h1>Set a new password</h1></div>
    <form method="POST" action="{{ route('password.update') }}" class="stack">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" label="Email"><input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username"></x-field>
        <x-field name="password" label="New password" hint="At least 8 characters, with letters and numbers."><input id="password" name="password" type="password" required autocomplete="new-password"></x-field>
        <x-field name="password_confirmation" label="Confirm new password"><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></x-field>
        <button class="btn primary block">Save new password</button>
    </form>
</div>
@endsection
