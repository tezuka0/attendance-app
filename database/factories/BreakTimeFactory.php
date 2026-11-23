<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BreakTime>
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
            'attendance_record_id' => AttendanceRecord::factory(),
            'break_start' => $this->faker->dateTimeBetween('11:45', '12:15')->format('H:i:00'),
            'break_end' => $this->faker->dateTimeBetween('12:45', '13:15')->format('H:i:00'),
        ];
    }
}
