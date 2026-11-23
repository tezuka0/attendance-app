<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

//マイ勤怠レポート機能
class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 集計期間は 2026/06 〜 2026/11 になる
        $this->travelTo(Carbon::create(2026, 11, 15, 12, 0));
    }

    //ゲストはレポートページにアクセスできない
    public function test_guest_cannot_access_report(): void
    {
        $response = $this->get('/attendance/report');

        $response->assertRedirect('/login');
    }

    //認証ユーザーの統計情報が正しく計算される
    public function test_report_statistics_are_calculated_correctly(): void
    {
        $user = User::factory()->create();

        // 当月（休憩はすべて 12:00〜13:00 の1時間）
        $this->createAttendance($user, '2026-11-02', '09:00:00', '20:00:00'); // 労働600分・残業120分
        $this->createAttendance($user, '2026-11-03', '09:30:00', '18:00:00'); // 労働450分・遅刻
        $this->createAttendance($user, '2026-11-04', '08:00:00', '21:00:00'); // 労働720分・残業240分・長時間労働
        $this->createAttendance($user, '2026-11-05', '09:00:00', '17:00:00'); // 労働420分・早退

        // 過去の月
        $this->createAttendance($user, '2026-09-01', '09:00:00', '18:00:00'); // 労働480分

        // 集計に含めないデータ
        $this->createAttendance($user, '2026-05-29', '09:00:00', '18:00:00'); // 6ヶ月より前
        $this->createAttendance(User::factory()->create(), '2026-11-02', '09:00:00', '18:00:00'); // 他のユーザー

        $response = $this->actingAs($user)->get('/attendance/report');

        $response->assertStatus(200);

        // 総労働 600+450+720+420+480 = 2670分、総残業 120+240 = 360分、平均 2670÷5 = 534分
        $response->assertViewHas('summary', [
            'total_work_minutes' => 2670,
            'total_overtime_minutes' => 360,
            'avg_work_minutes' => 534,
        ]);

        $response->assertViewHas('monthlyTrend', [
            ['month' => '2026/06', 'work_minutes' => 0, 'overtime_minutes' => 0],
            ['month' => '2026/07', 'work_minutes' => 0, 'overtime_minutes' => 0],
            ['month' => '2026/08', 'work_minutes' => 0, 'overtime_minutes' => 0],
            ['month' => '2026/09', 'work_minutes' => 480, 'overtime_minutes' => 0],
            ['month' => '2026/10', 'work_minutes' => 0, 'overtime_minutes' => 0],
            ['month' => '2026/11', 'work_minutes' => 2190, 'overtime_minutes' => 360],
        ]);

        $response->assertViewHas('anomalies', [
            'late_count' => 1,
            'early_leave_count' => 1,
            'long_work_count' => 1,
        ]);
    }

    //勤怠記録がないユーザーで安全に処理される
    public function test_report_is_safe_without_attendance_records(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/attendance/report');

        $response->assertStatus(200);

        $response->assertViewHas('summary', [
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'avg_work_minutes' => 0,
        ]);

        $response->assertViewHas('monthlyTrend', function ($monthlyTrend) {
            return collect($monthlyTrend)->every(
                fn ($row) => $row['work_minutes'] === 0 && $row['overtime_minutes'] === 0
            );
        });

        $response->assertViewHas('anomalies', [
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
        ]);
    }

    //休憩 12:00〜13:00 付きの勤怠を1件作る
    private function createAttendance(User $user, string $date, string $clockIn, string $clockOut): void
    {
        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
        ]);

        BreakTime::factory()->create([
            'attendance_record_id' => $attendanceRecord->id,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);
    }
}
