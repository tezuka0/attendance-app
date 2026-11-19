<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_page_displays_current_datetime(): void
    {
        $this->travelTo(now()->setTime(9, 0));

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/attendance');

        $currentDate = now()->locale('ja')->isoFormat('YYYY年M月D日(ddd)');

        $currentTime = now()->format('H:i');

        $response->assertStatus(200);
        $response->assertSee($currentDate);
        $response->assertSee($currentTime);
    }
}
