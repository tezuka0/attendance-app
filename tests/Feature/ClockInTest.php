<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClockInTest extends TestCase
{
    use RefreshDatabase;

    //出勤ボタンが正しく機能する
    public function test_clock_in_button_works(): void
    {
        $this->travelTo(now()->setTime(9, 0));

        $user = User::factory()->create();

        $beforeResponse = $this->actingAs($user)->get('/attendance');
        $beforeResponse->assertStatus(200);
        $beforeResponse->assertSee('value="clock_in"', false);

        $this->post('/attendance', ['action' => 'clock_in']);

        $afterResponse = $this->get('/attendance');
        $afterResponse->assertStatus(200);
        $afterResponse->assertSee('出勤中');
    }

    //出勤は一日一回のみできる
    public function test_user_can_clock_in_only_once_a_day(): void
    {
        $this->travelTo(now()->setTime(19, 0));

        $user = User::factory()->create();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today(),
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertDontSee('value="clock_in"', false);
    }

    //出勤時刻が勤怠一覧画面で確認できる
    public function test_clock_in_time_is_shown_on_attendance_list(): void
    {
        $this->travelTo(now()->setTime(9, 0));

        $user = User::factory()->create();

        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);
        $response = $this->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee('09:00');
    }
}
