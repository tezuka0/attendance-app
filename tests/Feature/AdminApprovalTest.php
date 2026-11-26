<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 勤怠情報修正機能（管理者）
class AdminApprovalTest extends TestCase
{
    use RefreshDatabase;

    // 承認待ちの修正申請が全て表示されている
    public function test_all_pending_requests_are_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $firstRecord = AttendanceRecord::factory()->create([
            'user_id' => $firstUser->id,
            'date' => '2026-11-02',
        ]);

        $secondRecord = AttendanceRecord::factory()->create([
            'user_id' => $secondUser->id,
            'date' => '2026-11-02',
        ]);

        $formData = $this->validFormData();
        $this->actingAs($firstUser)->post('/attendance/detail/'.$firstRecord->id, $formData);

        $formData['comment'] = '打刻忘れのため';
        $this->actingAs($secondUser)->post('/attendance/detail/'.$secondRecord->id, $formData);

        $response = $this->actingAs($admin)->get('/stamp_correction_request/list');
        $response->assertStatus(200);
        $response->assertSeeInOrder(['電車遅延のため', 'id="content2"'], false);
        $response->assertSeeInOrder(['打刻忘れのため', 'id="content2"'], false);
    }

    // 承認済みの修正申請が全て表示されている
    public function test_all_approved_requests_are_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $firstRecord = AttendanceRecord::factory()->create([
            'user_id' => $firstUser->id,
            'date' => '2026-11-02',
        ]);

        $secondRecord = AttendanceRecord::factory()->create([
            'user_id' => $secondUser->id,
            'date' => '2026-11-02',
        ]);

        $formData = $this->validFormData();
        $this->actingAs($firstUser)->post('/attendance/detail/'.$firstRecord->id, $formData);

        $formData['comment'] = '打刻忘れのため';
        $this->actingAs($secondUser)->post('/attendance/detail/'.$secondRecord->id, $formData);

        foreach (AttendanceCorrectRequest::all() as $application) {
            $this->actingAs($admin)->post('/stamp_correction_request/approve/'.$application->id);
        }

        $response = $this->get('/stamp_correction_request/list');
        $response->assertStatus(200);
        $response->assertSeeInOrder(['id="content2"', '電車遅延のため'], false);
        $response->assertSeeInOrder(['id="content2"', '打刻忘れのため'], false);
    }

    // 修正申請の詳細内容が正しく表示されている
    public function test_request_detail_is_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $this->actingAs($user)->post('/attendance/detail/'.$attendanceRecord->id, $this->validFormData());

        $application = AttendanceCorrectRequest::first();

        $response = $this->actingAs($admin)->get('/stamp_correction_request/approve/'.$application->id);
        $response->assertStatus(200);
        $response->assertSee($user->name);
        $response->assertSee('11月2日');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('電車遅延のため');
    }

    // 修正申請の承認処理が正しく行われる
    public function test_request_is_approved_and_attendance_is_updated(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
            'clock_in' => '08:00:00',
            'clock_out' => '17:00:00',
        ]);

        $this->actingAs($user)->post('/attendance/detail/'.$attendanceRecord->id, $this->validFormData());

        $application = AttendanceCorrectRequest::first();

        $this->actingAs($admin)->post('/stamp_correction_request/approve/'.$application->id);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendanceRecord->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->assertDatabaseHas('attendance_correct_requests', [
            'id' => $application->id,
            'status' => AttendanceCorrectRequest::STATUS_APPROVED,
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
