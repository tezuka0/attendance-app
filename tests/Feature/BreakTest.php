<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreakTest extends TestCase
{
    use RefreshDatabase;

    //休憩ボタンが正しく機能する
    public function test_break_in_button_works(): void
    {
        $this->travelTo(now()->setTime(12, 0));

        $user = $this->createWorkingUser();

        $beforeResponse = $this->actingAs($user)->get('/attendance');
        $beforeResponse->assertStatus(200);
        $beforeResponse->assertSee('value="break_in"', false);

        $this->post('/attendance', ['action' => 'break_in']);

        $afterResponse = $this->get('/attendance');
        $afterResponse->assertStatus(200);
        $afterResponse->assertSee('休憩中');
    }

    //休憩は1日に何回もできる
    public function test_user_can_take_breaks_many_times_a_day(): void
    {
        $this->travelTo(now()->setTime(12, 0));

        $user = $this->createWorkingUser();

        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);
        $this->post('/attendance', ['action' => 'break_out']);

        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('value="break_in"', false);
    }

    //休憩戻ボタンが正しく機能する
    public function test_break_out_button_works(): void
    {
        $this->travelTo(now()->setTime(12, 0));

        $user = $this->createWorkingUser();

        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);

        $beforeResponse = $this->get('/attendance');
        $beforeResponse->assertStatus(200);
        $beforeResponse->assertSee('value="break_out"', false);

        $this->post('/attendance', ['action' => 'break_out']);

        $afterResponse = $this->get('/attendance');
        $afterResponse->assertStatus(200);
        $afterResponse->assertSee('出勤中');
    }

    //休憩戻は1日に何回もできる
    public function test_user_can_return_from_breaks_many_times_a_day(): void
    {
        $this->travelTo(now()->setTime(12, 0));

        $user = $this->createWorkingUser();

        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);
        $this->post('/attendance', ['action' => 'break_out']);
        $this->post('/attendance', ['action' => 'break_in']);

        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('value="break_out"', false);
    }

    //休憩時刻が勤怠一覧で確認できる
    public function test_break_time_is_shown_on_attendance_list(): void
    {
        $this->travelTo(now()->setTime(12, 0));

        $user = $this->createWorkingUser();

        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);

        $this->travelTo(now()->setTime(13, 0));
        $this->post('/attendance', ['action' => 'break_out']);

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee('1:00');
    }

    //ステータスが出勤中のユーザーを作成
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
