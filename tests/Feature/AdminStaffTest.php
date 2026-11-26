<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ユーザー情報取得機能（管理者）
class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    // 管理者ユーザーが全一般ユーザーの「氏名」「メールアドレス」を確認できる
    public function test_all_users_names_and_emails_are_shown(): void
    {
        $admin = User::factory()->admin()->create();
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $response = $this->actingAs($admin)->get('/admin/staff/list');
        $response->assertStatus(200);
        $response->assertSeeInOrder([
            $firstUser->name,
            $firstUser->email,
            $secondUser->name,
            $secondUser->email,
        ]);
    }

    // ユーザーの勤怠情報が正しく表示される
    public function test_user_attendance_records_are_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/staff/'.$user->id);
        $response->assertStatus(200);
        $response->assertSee($user->name);
        $response->assertSee('2026/11');
        $response->assertSeeInOrder(['11/02', '09:00', '18:00']);
    }

    // 「前月」を押下した時に表示月の前月の情報が表示される
    public function test_previous_month_is_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/staff/'.$user->id.'?date=2026-10');
        $response->assertStatus(200);
        $response->assertSee('2026/10');
        $response->assertSee('10/01');
        $response->assertSee('09:00');
    }

    // 「翌月」を押下した時に表示月の翌月の情報が表示される
    public function test_next_month_is_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-12-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/staff/'.$user->id.'?date=2026-12');
        $response->assertStatus(200);
        $response->assertSee('2026/12');
        $response->assertSee('12/01');
        $response->assertSee('09:00');
    }

    // 「詳細」を押下すると、その日の勤怠詳細画面に遷移する
    public function test_detail_link_goes_to_attendance_detail(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $listResponse = $this->actingAs($admin)->get('/admin/attendance/staff/'.$user->id);
        $listResponse->assertSee('/attendance/'.$attendanceRecord->id);

        $redirectResponse = $this->get('/attendance/'.$attendanceRecord->id);
        $redirectResponse->assertRedirect('/admin/attendance/'.$attendanceRecord->id);

        $detailResponse = $this->get('/admin/attendance/'.$attendanceRecord->id);
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('11月2日');
    }
}
