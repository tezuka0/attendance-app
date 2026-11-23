<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 勤怠詳細情報取得機能（一般ユーザー）
class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    // 勤怠詳細画面の「名前」がログインユーザーの氏名になっている
    public function test_name_is_logged_in_user_name(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $response = $this->actingAs($user)->get('/attendance/detail/'.$attendanceRecord->id);
        $response->assertStatus(200);
        $response->assertSee($user->name);
    }

    // 勤怠詳細画面の「日付」が選択した日付になっている
    public function test_date_is_selected_date(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $response = $this->actingAs($user)->get('/attendance/detail/'.$attendanceRecord->id);
        $response->assertStatus(200);
        $response->assertSee('2026年');
        $response->assertSee('11月2日');
    }

    // 「出勤・退勤」にて記されている時間がログインユーザーの打刻と一致している
    public function test_clock_in_and_clock_out_match_user_stamps(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/detail/'.$attendanceRecord->id);
        $response->assertStatus(200);
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    // 「休憩」にて記されている時間がログインユーザーの打刻と一致している
    public function test_break_time_matches_user_stamps(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        BreakTime::factory()->create([
            'attendance_record_id' => $attendanceRecord->id,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/detail/'.$attendanceRecord->id);
        $response->assertStatus(200);
        $response->assertSee('12:00');
        $response->assertSee('13:00');
    }
}
