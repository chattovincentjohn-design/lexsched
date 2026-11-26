<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user->id)],
            'phone' => [$user->role->value === 'client' ? 'required' : 'nullable', 'string', 'regex:/^(09|\+639)\d{9}$/'],
        ], ['phone.regex' => 'Use a mobile number like 09171234567.']);

        $user->update($data);
        AuditLogger::log('profile_updated', "{$user->name} updated their profile", $user);

        return back()->with('status', 'Profile saved. New bookings will use these contact details.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], ['current_password.current_password' => 'Your current password is incorrect.']);

        $user = $request->user();
        $user->update(['password' => $data['password']]);
        Auth::logoutOtherDevices($data['password']);
        AuditLogger::log('password_changed', "{$user->name} changed their password", $user);

        return back()->with('status', 'Password changed. Other devices were signed out.');
    }
}
