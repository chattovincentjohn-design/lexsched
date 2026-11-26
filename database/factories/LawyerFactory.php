<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Lawyer;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lawyer>
 */
class LawyerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->role(UserRole::Lawyer),
            'roll_number' => (string) fake()->numberBetween(50000, 80000),
            'ibp_chapter' => 'Zamboanga del Norte',
            'years_of_practice' => fake()->numberBetween(3, 25),
            'accepts_bookings' => true,
        ];
    }

    public function notary(): static
    {
        return $this->state(fn (array $attributes) => [
            'notarial_commission_no' => '2026-'.fake()->numerify('###'),
            'commission_expires_on' => now()->endOfYear()->addYear(),
        ]);
    }

    /**
     * @param  Service|list<Service>  $services
     */
    public function offering(Service|array $services): static
    {
        return $this->afterCreating(fn (Lawyer $lawyer) => $lawyer->services()->attach(collect(is_array($services) ? $services : [$services])->pluck('id')));
    }
}
