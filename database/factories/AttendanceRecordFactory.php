<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
            'user_id' => \App\Models\User::factory(),
            'date' => $this->faker->date(),
            'clock_in' => $this->faker->dateTimeBetween('8:30', '9:30')->format('H:i:00'),
            'clock_out' => $this->faker->dateTimeBetween('17:30', '18:30')->format('H:i:00'),
            'comment' => null,
        ];
    }
}
