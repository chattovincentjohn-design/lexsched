<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_a_link_without_revealing_whether_the_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->post(route('password.email'), ['email' => $user->email]);
        $unknown = $this->post(route('password.email'), ['email' => 'nobody@example.com']);

        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertSame(session('status'), $known->getSession()->get('status'));
        $unknown->assertSessionHas('status', $known->getSession()->get('status'));
    }

    public function test_reset_link_sets_a_new_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))->assertOk();
            $this->post(route('password.update'), [
                'token' => $notification->token, 'email' => $user->email,
                'password' => 'newpass123', 'password_confirmation' => 'newpass123',
            ])->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }

    public function test_invalid_reset_token_is_refused(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.update'), [
            'token' => 'made-up', 'email' => $user->email, 'password' => 'newpass123', 'password_confirmation' => 'newpass123',
        ])->assertSessionHasErrors('email');
    }

    public function test_user_updates_their_profile(): void
    {
        $client = User::factory()->role(UserRole::Client)->create();

        $this->actingAs($client)->get(route('profile.edit'))->assertOk();
        $this->actingAs($client)->put(route('profile.update'), ['name' => 'Maria C. Reyes', 'email' => 'mc@example.com', 'phone' => '09181112222'])
            ->assertSessionHasNoErrors();

        $this->assertSame('09181112222', $client->fresh()->phone);
    }

    public function test_clients_must_keep_a_valid_mobile_number(): void
    {
        $client = User::factory()->role(UserRole::Client)->create();

        $this->actingAs($client)->put(route('profile.update'), ['name' => 'X', 'email' => $client->email, 'phone' => ''])
            ->assertSessionHasErrors('phone');
    }

    public function test_changing_password_requires_the_current_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.password'), ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasErrorsIn('password', 'current_password');

        $this->actingAs($user)->put(route('profile.password'), ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }
}
