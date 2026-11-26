@extends('layouts.app')
@section('title', $user->exists ? 'Edit '.$user->name : 'Add user')
@section('content')
<p style="margin-bottom:14px"><a href="{{ route('admin.users.index') }}">← Users</a></p>
@php($lawyer = $user->lawyer)
<form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="panel stack" style="max-width:760px">
    @csrf
    @if ($user->exists) @method('PUT') @endif
    <h1>{{ $user->exists ? 'Edit '.$user->name : 'Add staff or lawyer' }}</h1>
    <div class="form-grid">
        <x-field name="name" label="Full name"><input id="name" name="name" value="{{ old('name', $user->name) }}" required></x-field>
        <x-field name="role" label="Role">
            <select id="role" name="role" onchange="document.getElementById('lawyer-fields').hidden = this.value !== 'lawyer'">
                @foreach (\App\Enums\UserRole::cases() as $case)<option value="{{ $case->value }}" @selected(old('role', $user->role?->value ?? 'secretary') === $case->value)>{{ $case->label() }}</option>@endforeach
            </select>
        </x-field>
        <x-field name="email" label="Email"><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required></x-field>
        <x-field name="phone" label="Mobile number"><input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="09171234567"></x-field>
        <x-field name="password" :label="$user->exists ? 'New password (leave blank to keep)' : 'Password'"><input id="password" name="password" type="password" autocomplete="new-password"></x-field>
        <x-field name="password_confirmation" label="Confirm password"><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"></x-field>
    </div>

    <fieldset id="lawyer-fields" class="stack" style="border:1px solid var(--line);border-radius:10px;padding:16px" @if (old('role', $user->role?->value ?? 'secretary') !== 'lawyer') hidden @endif>
        <legend class="label" style="padding:0 6px">Lawyer profile</legend>
        <div class="form-grid">
            <x-field name="roll_number" label="Roll of Attorneys no."><input id="roll_number" name="roll_number" value="{{ old('roll_number', $lawyer?->roll_number) }}"></x-field>
            <x-field name="ibp_chapter" label="IBP chapter"><input id="ibp_chapter" name="ibp_chapter" value="{{ old('ibp_chapter', $lawyer?->ibp_chapter) }}"></x-field>
            <x-field name="years_of_practice" label="Years of practice"><input id="years_of_practice" name="years_of_practice" type="number" min="0" value="{{ old('years_of_practice', $lawyer?->years_of_practice) }}"></x-field>
            <div></div>
            <x-field name="notarial_commission_no" label="Notarial commission no." hint="Leave blank if not a notary public."><input id="notarial_commission_no" name="notarial_commission_no" value="{{ old('notarial_commission_no', $lawyer?->notarial_commission_no) }}"></x-field>
            <x-field name="commission_expires_on" label="Commission valid until"><input id="commission_expires_on" name="commission_expires_on" type="date" value="{{ old('commission_expires_on', $lawyer?->commission_expires_on?->toDateString()) }}"></x-field>
        </div>
        <div class="field"><span class="lbl">Services handled</span>
            <div class="radio-row">@foreach ($services as $service)
                <label><input type="checkbox" name="services[]" value="{{ $service->id }}" @checked(in_array($service->id, old('services', $lawyer?->services->pluck('id')->all() ?? [])))> {{ $service->name }}</label>
            @endforeach</div>
        </div>
    </fieldset>

    @if ($user->exists && ! $user->is(auth()->user()))
        <label class="chk"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> Account is active (unticked accounts cannot sign in)</label>
    @endif
    <div><button class="btn primary">{{ $user->exists ? 'Save changes' : 'Create account' }}</button></div>
</form>
@endsection
