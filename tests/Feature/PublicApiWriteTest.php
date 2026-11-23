<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

//公開API 書き込み系
class PublicApiWriteTest extends TestCase
{
    use RefreshDatabase;

    //POST /api/v1/attendance-records で勤怠が作成される
    public function test_attendance_record_can_be_created(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-02',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'API から登録',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-11-02',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'API から登録',
        ]);
    }

    //バリデーションエラー時に 422 と日本語エラーメッセージが返る
    public function test_validation_error_returns_japanese_messages(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/attendance-records', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'date' => '勤怠日は必須です。',
            'clock_in' => '出勤時刻は必須です。',
        ]);
        $this->assertDatabaseCount('attendance_records', 0);
    }

    //PUT /api/v1/attendance-records/{attendanceRecord} で勤怠が更新される
    public function test_attendance_record_can_be_updated(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->putJson("/api/v1/attendance-records/{$attendanceRecord->id}", [
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendanceRecord->id,
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        // 存在しない ID は 404
        $this->putJson('/api/v1/attendance-records/99999', [
            'clock_in' => '10:00:00',
        ])->assertStatus(404);
    }

    //DELETE /api/v1/attendance-records/{attendanceRecord} で勤怠が削除される
    public function test_attendance_record_can_be_deleted(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->deleteJson("/api/v1/attendance-records/{$attendanceRecord->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('attendance_records', [
            'id' => $attendanceRecord->id,
        ]);

        // 存在しない ID は 404
        $this->deleteJson('/api/v1/attendance-records/99999')->assertStatus(404);
    }
}
