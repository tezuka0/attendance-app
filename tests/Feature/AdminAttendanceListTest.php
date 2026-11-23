<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 勤怠一覧情報取得機能（管理者）
class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    // その日になされた全ユーザーの勤怠情報が正確に確認できる
    public function test_all_users_attendance_records_of_the_day_are_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        AttendanceRecord::factory()->create([
            'user_id' => $firstUser->id,
            'date' => '2026-11-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        AttendanceRecord::factory()->create([
            'user_id' => $secondUser->id,
            'date' => '2026-11-15',
            'clock_in' => '08:45:00',
            'clock_out' => '17:45:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/list');
        $response->assertStatus(200);
        $response->assertSeeInOrder([
            $firstUser->name,
            '09:00',
            '18:00',
            $secondUser->name,
            '08:45',
            '17:45',
        ]);
    }

    // 遷移した際に現在の日付が表示される
    public function test_current_date_is_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/attendance/list');
        $response->assertStatus(200);
        $response->assertSee('2026/11/15');
    }

    // 「前日」を押下した時に前の日の勤怠情報が表示される
    public function test_previous_day_is_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/list?date=2026-11-14');
        $response->assertStatus(200);
        $response->assertSee('2026/11/14');
        $response->assertSee($user->name);
        $response->assertSee('09:00');
    }

    // 「翌日」を押下した時に次の日の勤怠情報が表示される
    public function test_next_day_is_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-16',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/list?date=2026-11-16');
        $response->assertStatus(200);
        $response->assertSee('2026/11/16');
        $response->assertSee($user->name);
        $response->assertSee('09:00');
    }
}
