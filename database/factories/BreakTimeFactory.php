<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BreakTime>
 */
class BreakTimeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_record_id' => \App\Models\AttendanceRecord::factory(),
            'break_start' => $this->faker->dateTimeBetween('11:45', '12:15')->format('H:i:00'),
            'break_end' => $this->faker->dateTimeBetween('12:45', '13:15')->format('H:i:00'),
        ];
    }
}
