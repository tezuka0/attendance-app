<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectRequest;
use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

//公開API 読み取り系
class PublicApiReadTest extends TestCase
{
    use RefreshDatabase;

    //GET /api/v1/attendance-records で勤怠一覧が JSON で取得できる
    public function test_attendance_records_can_be_listed_as_json(): void
    {
        $this->seed();

        $response = $this->getJson('/api/v1/attendance-records');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $response->assertJsonPath('meta.total', AttendanceRecord::count());
    }

    //GET /api/v1/attendance-records/{attendanceRecord} で勤怠詳細が JSON で取得できる
    public function test_attendance_record_detail_can_be_shown_as_json(): void
    {
        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        BreakTime::factory()->create([
            'attendance_record_id' => $attendanceRecord->id,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        AttendanceCorrectRequest::create([
            'user_id' => $user->id,
            'attendance_record_id' => $attendanceRecord->id,
            'date' => '2026-11-02',
            'clock_in' => '09:30:00',
            'clock_out' => '18:00:00',
            'comment' => '電車遅延のため',
            'status' => AttendanceCorrectRequest::STATUS_PENDING,
        ]);

        $response = $this->getJson("/api/v1/attendance-records/{$attendanceRecord->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $attendanceRecord->id);
        $response->assertJsonPath('data.user.name', $user->name);
        $response->assertJsonPath('data.breaks.0.break_in', '12:00:00');
        $response->assertJsonPath('data.breaks.0.break_out', '13:00:00');
        $response->assertJsonPath('data.applications.0.comment', '電車遅延のため');
    }

    //存在しない ID では 404 とエラー JSON が返る
    public function test_not_found_error_is_returned_for_unknown_id(): void
    {
        $response = $this->getJson('/api/v1/attendance-records/99999');

        $response->assertStatus(404);
        $response->assertExactJson(['error' => '勤怠情報が見つかりませんでした。']);
    }
}
