<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Sanctum 認証
class SanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    // 未認証時に書き込み系 API で 401 が返る
    public function test_unauthenticated_write_requests_return_401(): void
    {
        $attendanceRecord = AttendanceRecord::factory()->create();

        $responses = [
            $this->postJson('/api/v1/attendance-records', [
                'date' => '2026-11-02',
                'clock_in' => '09:00:00',
            ]),
            $this->putJson("/api/v1/attendance-records/{$attendanceRecord->id}", [
                'clock_in' => '10:00:00',
            ]),
            $this->deleteJson("/api/v1/attendance-records/{$attendanceRecord->id}"),
        ];

        foreach ($responses as $response) {
            $response->assertStatus(401);
            $response->assertExactJson(['message' => 'Unauthenticated.']);
        }
    }

    // 認証済みユーザーは自分の勤怠を更新・削除できる
    public function test_user_can_update_and_delete_own_attendance_record(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->putJson("/api/v1/attendance-records/{$attendanceRecord->id}", [
            'clock_in' => '10:00:00',
        ])->assertStatus(200);

        $this->deleteJson("/api/v1/attendance-records/{$attendanceRecord->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('attendance_records', [
            'id' => $attendanceRecord->id,
        ]);
    }

    // 他ユーザーの勤怠を更新・削除しようとすると 403 が返る
    public function test_user_cannot_update_or_delete_other_users_attendance_record(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $otherUser->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $updateResponse = $this->putJson("/api/v1/attendance-records/{$attendanceRecord->id}", [
            'clock_in' => '10:00:00',
        ]);
        $updateResponse->assertStatus(403);
        $updateResponse->assertExactJson(['error' => 'この操作を実行する権限がありません。']);

        $deleteResponse = $this->deleteJson("/api/v1/attendance-records/{$attendanceRecord->id}");
        $deleteResponse->assertStatus(403);
        $deleteResponse->assertExactJson(['error' => 'この操作を実行する権限がありません。']);

        // 他ユーザーの勤怠は変わっていない
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendanceRecord->id,
            'clock_in' => '09:00:00',
        ]);
    }
}
