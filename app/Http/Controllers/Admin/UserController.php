<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $role = UserRole::tryFrom((string) $request->query('role'));
        $search = trim((string) $request->query('q'));

        return view('admin.users.index', [
            'users' => User::query()
                ->when($role, fn (Builder $query) => $query->where('role', $role->value))
                ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
                ->with('lawyer.services')
                ->orderBy('role')->orderBy('name')
                ->paginate(20)->withQueryString(),
            'role' => $role,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User, 'services' => Service::query()->orderBy('id')->get()]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user->load('lawyer.services'), 'services' => Service::query()->orderBy('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
                'role' => $data['role'], 'password' => $data['password'], 'is_active' => true,
            ]);
            $this->syncLawyer($user, $data);

            return $user;
        });

        AuditLogger::log('user_created', "Created {$user->role->label()} account for {$user->name}", $user);

        return redirect()->route('admin.users.index')->with('status', "{$user->name} can now sign in.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        DB::transaction(function () use ($user, $data, $request): void {
            $user->fill(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null, 'role' => $data['role']]);
            $user->is_active = $user->is($request->user()) ? true : $request->boolean('is_active');

            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
            }

            $user->save();
            $this->syncLawyer($user, $data);
        });

        AuditLogger::log('user_updated', "Updated account of {$user->name}", $user);

        return redirect()->route('admin.users.index')->with('status', "{$user->name} saved.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'roll_number' => ['nullable', 'string', 'max:30'],
            'ibp_chapter' => ['nullable', 'string', 'max:100'],
            'years_of_practice' => ['nullable', 'integer', 'min:0', 'max:70'],
            'notarial_commission_no' => ['nullable', 'string', 'max:50'],
            'commission_expires_on' => ['nullable', 'required_with:notarial_commission_no', 'date'],
            'services' => ['nullable', 'array'],
            'services.*' => ['exists:services,id'],
        ], ['phone.regex' => 'Use a mobile number like 09171234567.']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncLawyer(User $user, array $data): void
    {
        if ($user->role !== UserRole::Lawyer) {
            $user->lawyer?->update(['accepts_bookings' => false]);

            return;
        }

        $lawyer = $user->lawyer()->updateOrCreate([], [
            'roll_number' => $data['roll_number'] ?? null,
            'ibp_chapter' => $data['ibp_chapter'] ?? null,
            'years_of_practice' => $data['years_of_practice'] ?? null,
            'notarial_commission_no' => $data['notarial_commission_no'] ?? null,
            'commission_expires_on' => $data['commission_expires_on'] ?? null,
            'accepts_bookings' => true,
        ]);
        $lawyer->services()->sync($data['services'] ?? []);
    }
}
