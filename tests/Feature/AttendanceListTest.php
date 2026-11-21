<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

//勤怠一覧情報取得機能（一般ユーザー）
class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    //自分が行った勤怠情報が全て表示されている
    public function test_attendance_records_are_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-03',
            'clock_in' => '08:45:00',
            'clock_out' => '17:45:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('08:45');
        $response->assertSee('17:45');
    }

    //勤怠一覧画面に遷移した際に現在の月が表示される
    public function test_current_month_is_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee('2026/11');
    }

    //「前月」を押下した際に表示月の前月の情報が表示される
    public function test_previous_month_is_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list?date=2026-10');
        $response->assertStatus(200);
        $response->assertSee('2026/10');
        $response->assertSee('10/01');
        $response->assertSee('09:00');
    }

    //「翌月」を押下した際に表示月の翌月の情報が表示される
    public function test_next_month_is_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-12-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list?date=2026-12');
        $response->assertStatus(200);
        $response->assertSee('2026/12');
        $response->assertSee('12/01');
        $response->assertSee('09:00');
    }

    //「詳細」を押下すると、その日の勤怠詳細画面に遷移する
    public function test_detail_link_goes_to_attendance_detail(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $listResponse = $this->actingAs($user)->get('/attendance/list');
        $listResponse->assertSee('/attendance/' . $attendanceRecord->id);

        $redirectResponse = $this->get('/attendance/' . $attendanceRecord->id);
        $redirectResponse->assertRedirect('/attendance/detail/' . $attendanceRecord->id);

        $detailResponse = $this->get('/attendance/detail/' . $attendanceRecord->id);
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('11月2日');
    }
}
