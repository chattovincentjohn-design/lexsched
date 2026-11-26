@extends('layouts.app')
@section('title', 'My profile')
@section('content')
<div class="pagehead"><div><h1>My profile</h1><p>{{ $user->role->label() }} account · member since {{ $user->created_at->format('F Y') }}</p></div></div>
<div class="grid2">
    <form method="POST" action="{{ route('profile.update') }}" class="panel stack">
        @csrf @method('PUT')
        <h2>Contact details</h2>
        <x-field name="name" label="Full name"><input id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name"></x-field>
        <x-field name="email" label="Email"><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="email"></x-field>
        <x-field name="phone" label="Mobile number" hint="Booking confirmations and reminders are texted here."><input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="09171234567" inputmode="tel" autocomplete="tel"></x-field>
        <div><button class="btn primary">Save details</button></div>
    </form>

    <form method="POST" action="{{ route('profile.password') }}" class="panel stack">
        @csrf @method('PUT')
        <h2>Change password</h2>
        @foreach (['current_password' => 'Current password', 'password' => 'New password', 'password_confirmation' => 'Confirm new password'] as $field => $label)
            <div @class(['field', 'has-error' => $errors->password->has($field)])>
                <label for="{{ $field }}">{{ $label }}</label>
                <input id="{{ $field }}" name="{{ $field }}" type="password" required autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}">
                @if ($errors->password->has($field))<span class="error">{{ $errors->password->first($field) }}</span>@endif
            </div>
        @endforeach
        <p class="hint">At least 8 characters, with letters and numbers. Changing it signs you out on your other devices.</p>
        <div><button class="btn">Change password</button></div>
    </form>
</div>
@endsection
