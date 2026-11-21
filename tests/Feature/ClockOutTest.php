<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClockOutTest extends TestCase
{
    use RefreshDatabase;

    //退勤ボタンが正しく機能する
    public function test_clock_out_button_works(): void
    {
        $this->travelTo(now()->setTime(18, 0));

        $user = $this->createWorkingUser();

        $beforeResponse = $this->actingAs($user)->get('/attendance');
        $beforeResponse->assertStatus(200);
        $beforeResponse->assertSee('value="clock_out"', false);

        $this->post('/attendance', ['action' => 'clock_out']);

        $afterResponse = $this->get('/attendance');
        $afterResponse->assertStatus(200);
        $afterResponse->assertSee('退勤済');
    }

    //退勤時刻が勤怠一覧画面で確認できる
    public function test_clock_out_is_shown_on_attendance_list(): void
    {
        $this->travelTo(now()->setTime(9, 0));

        $user = User::factory()->create();

        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);

        $this->travelTo(now()->setTime(18, 0));
        $this->post('/attendance', ['action' => 'clock_out']);

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee('18:00');
    }

    //ステータスが勤務中のユーザーを作成
    private function createWorkingUser(): User
    {
        $user = User::factory()->create();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today(),
            'clock_out' => null,
        ]);

        return $user;
    }
}
