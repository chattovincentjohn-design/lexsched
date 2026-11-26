<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Lawyer;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_name' => fake()->name(),
            'client_phone' => '09'.fake()->numerify('#########'),
            'client_email' => fake()->safeEmail(),
            'lawyer_id' => Lawyer::factory(),
            'service_id' => Service::factory(),
            'scheduled_date' => today()->addWeekday(),
            'start_time' => '10:00',
            'status' => AppointmentStatus::Pending,
            'mode' => 'in_office',
            'source' => 'online',
            'concern' => fake()->sentence(),
            'fee' => 1500,
            'payment_method' => 'office',
            'copies' => 1,
        ];
    }

    public function status(AppointmentStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
