<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 勤怠詳細情報取得・修正機能（管理者）
class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    // 勤怠詳細画面に表示されるデータが選択したものになっている
    public function test_selected_attendance_record_is_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/'.$attendanceRecord->id);
        $response->assertStatus(200);
        $response->assertSee($user->name);
        $response->assertSee('2026年');
        $response->assertSee('11月2日');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    // 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される
    public function test_clock_in_after_clock_out_shows_error(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $formData = $this->validFormData();
        $formData['new_clock_in'] = '19:00';

        $response = $this->actingAs($admin)->post('/attendance/'.$attendanceRecord->id, $formData);
        $response->assertSessionHasErrors([
            'new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);
    }

    // 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される
    public function test_break_in_after_clock_out_shows_error(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $formData = $this->validFormData();
        $formData['new_break_in'] = ['19:00'];

        $response = $this->actingAs($admin)->post('/attendance/'.$attendanceRecord->id, $formData);
        $response->assertSessionHasErrors([
            'new_break_in.0' => '休憩時間が不適切な値です',
        ]);
    }

    // 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される
    public function test_break_out_after_clock_out_shows_error(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $formData = $this->validFormData();
        $formData['new_break_out'] = ['19:00'];

        $response = $this->actingAs($admin)->post('/attendance/'.$attendanceRecord->id, $formData);
        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);
    }

    // 備考欄が未入力の場合のエラーメッセージが表示される
    public function test_empty_comment_shows_error(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $formData = $this->validFormData();
        $formData['comment'] = '';

        $response = $this->actingAs($admin)->post('/attendance/'.$attendanceRecord->id, $formData);
        $response->assertSessionHasErrors([
            'comment' => '備考を記入してください',
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
            'comment' => '電車遅延のため',
        ];
    }
}
