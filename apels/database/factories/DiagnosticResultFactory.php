<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiagnosticResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'               => User::factory(),
            'speaking'              => $this->faker->numberBetween(0, 100),
            'grammar'               => $this->faker->numberBetween(0, 100),
            'vocabulary'            => $this->faker->numberBetween(0, 100),
            'overall'               => $this->faker->randomFloat(2, 0, 100),
            'attempt'               => 1,
            'audio_path'            => null,
            'is_speaking_processed' => true,
        ];
    }

    public function unprocessed(): static
    {
        return $this->state(['is_speaking_processed' => false]);
    }
}
