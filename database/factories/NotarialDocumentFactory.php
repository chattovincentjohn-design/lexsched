<?php

namespace Database\Factories;

use App\Models\NotarialDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotarialDocument>
 */
class NotarialDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Affidavit of Loss',
            'fee' => 300,
            'requirements' => ['1 valid government ID'],
            'is_active' => true,
        ];
    }
}
