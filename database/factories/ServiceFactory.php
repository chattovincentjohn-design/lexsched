<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Family Law', 'Labor Law', 'Civil & Property', 'Criminal Law', 'Corporate']);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'name' => $name,
            'description' => fake()->sentence(),
            'fee' => 1500,
            'duration_minutes' => 60,
            'is_notarial' => false,
            'is_active' => true,
        ];
    }

    public function notarial(): static
    {
        return $this->state(fn (array $attributes) => ['name' => 'Notarial Services', 'fee' => 0, 'is_notarial' => true]);
    }
}
