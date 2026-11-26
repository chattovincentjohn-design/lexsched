<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_register_as_a_client(): void
    {
        $this->post(route('register'), [
            'name' => 'Maria Clara Reyes', 'email' => 'mc@example.com', 'phone' => '09171234567',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'consent' => '1',
        ])->assertRedirect(route('client.book'));

        $this->assertSame(UserRole::Client, User::sole()->role);
        $this->assertAuthenticated();
    }

    public function test_users_land_on_their_role_home_page(): void
    {
        foreach ([UserRole::Client, UserRole::Secretary, UserRole::Admin] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get(route('dashboard'))
                ->assertRedirect(route($role->homeRoute()));
        }
    }

    public function test_disabled_accounts_cannot_sign_in(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Too many sign-in attempts', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_home_page_is_public(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Book a legal consultation');
    }
}
