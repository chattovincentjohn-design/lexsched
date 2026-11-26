@extends('layouts.app')
@section('title', 'Create account')
@section('content')
<div class="auth panel stack">
    <div><h1>Create your account</h1><p class="muted">You'll use this to book and track consultations.</p></div>
    <form method="POST" action="{{ route('register') }}" class="stack">
        @csrf
        <x-field name="name" label="Full name"><input id="name" name="name" value="{{ old('name') }}" required autocomplete="name"></x-field>
        <x-field name="email" label="Email"><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></x-field>
        <x-field name="phone" label="Mobile number" hint="We text booking confirmations and reminders here."><input id="phone" name="phone" value="{{ old('phone') }}" placeholder="09171234567" required inputmode="tel" autocomplete="tel"></x-field>
        <x-field name="password" label="Password" hint="At least 8 characters, with letters and numbers."><input id="password" name="password" type="password" required autocomplete="new-password"></x-field>
        <x-field name="password_confirmation" label="Confirm password"><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></x-field>
        <label class="consent"><input type="checkbox" name="consent" value="1" @checked(old('consent'))><span>I agree that {{ config('lexsched.office_name') }} may collect and process my personal information to handle my consultations, in line with the Data Privacy Act of 2012 (RA 10173).</span></label>
        @error('consent')<span class="error">{{ $message }}</span>@enderror
        <button class="btn primary block">Create account</button>
    </form>
    <p class="small muted">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
</div>
@endsection
