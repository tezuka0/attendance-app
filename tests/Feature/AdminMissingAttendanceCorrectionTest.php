<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// テストケース一覧外の追加テスト：打刻のない日の勤怠修正（管理者）
class AdminMissingAttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    // 打刻のない日の勤怠詳細を開くと、修正フォームの送信先にスタッフの user_id が付いている
    public function test_form_action_has_user_id_on_day_without_attendance(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->get('/admin/attendance/2026-11-03?user_id='.$user->id);
        $response->assertStatus(200);
        $response->assertSee($user->name);
        $response->assertSee('/attendance/2026-11-03?user_id='.$user->id, false);
    }

    // 打刻のない日でも、管理者が勤怠を直接修正すると勤怠と休憩が作られる
    public function test_admin_can_correct_day_without_attendance(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->post('/attendance/2026-11-03?user_id='.$user->id, $this->validFormData());
        $response->assertRedirect('/admin/attendance/list?date=2026-11-03');

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-11-03',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $attendanceRecord = AttendanceRecord::where('user_id', $user->id)->first();
        $this->assertDatabaseHas('break_times', [
            'attendance_record_id' => $attendanceRecord->id,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);
    }

    // 正しい修正内容
    private function validFormData(): array
    {
        return [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment' => '打刻忘れのため',
        ];
    }
}
