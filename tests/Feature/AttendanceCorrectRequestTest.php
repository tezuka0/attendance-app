<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 勤怠詳細情報修正機能（一般ユーザー）
class AttendanceCorrectRequestTest extends TestCase
{
    use RefreshDatabase;

    // 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される
    public function test_clock_in_after_clock_out_shows_error(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $formData = $this->validFormData();
        $formData['new_clock_in'] = '19:00';

        $response = $this->actingAs($user)->post('/attendance/detail/'.$attendanceRecord->id, $formData);
        $response->assertSessionHasErrors([
            'new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);
    }

    // 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される
    public function test_break_in_after_clock_out_shows_error(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $formData = $this->validFormData();
        $formData['new_break_in'] = ['19:00'];

        $response = $this->actingAs($user)->post('/attendance/detail/'.$attendanceRecord->id, $formData);
        $response->assertSessionHasErrors([
            'new_break_in.0' => '休憩時間が不適切な値です',
        ]);
    }

    // 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される
    public function test_break_out_after_clock_out_shows_error(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $formData = $this->validFormData();
        $formData['new_break_out'] = ['19:00'];

        $response = $this->actingAs($user)->post('/attendance/detail/'.$attendanceRecord->id, $formData);
        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);
    }

    // 備考欄が未入力の場合のエラーメッセージが表示される
    public function test_empty_comment_shows_error(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $formData = $this->validFormData();
        $formData['comment'] = '';

        $response = $this->actingAs($user)->post('/attendance/detail/'.$attendanceRecord->id, $formData);
        $response->assertSessionHasErrors([
            'comment' => '備考を記入してください',
        ]);
    }

    // 修正申請処理が実行される
    public function test_correction_request_is_shown_to_admin(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $this->actingAs($user)->post('/attendance/detail/'.$attendanceRecord->id, $this->validFormData());

        $application = AttendanceCorrectRequest::first();

        $listResponse = $this->actingAs($admin)->get('/stamp_correction_request/list');
        $listResponse->assertStatus(200);
        $listResponse->assertSee($user->name);
        $listResponse->assertSee('電車遅延のため');

        $detailResponse = $this->get('/stamp_correction_request/approve/'.$application->id);
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee($user->name);
        $detailResponse->assertSee('電車遅延のため');
    }

    // 「承認待ち」にログインユーザーが行った申請が全て表示されていること
    public function test_all_pending_requests_are_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();

        $firstRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $secondRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-03',
        ]);

        $formData = $this->validFormData();
        $this->actingAs($user)->post('/attendance/detail/'.$firstRecord->id, $formData);

        $formData['comment'] = '打刻忘れのため';
        $this->post('/attendance/detail/'.$secondRecord->id, $formData);

        $response = $this->get('/stamp_correction_request/list');
        $response->assertStatus(200);
        $response->assertSeeInOrder(['電車遅延のため', 'id="content2"'], false);
        $response->assertSeeInOrder(['打刻忘れのため', 'id="content2"'], false);
    }

    // 「承認済み」に管理者が承認した修正申請が全て表示されている
    public function test_all_approved_requests_are_shown(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $firstRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $secondRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-03',
        ]);

        $formData = $this->validFormData();
        $this->actingAs($user)->post('/attendance/detail/'.$firstRecord->id, $formData);

        $formData['comment'] = '打刻忘れのため';
        $this->post('/attendance/detail/'.$secondRecord->id, $formData);

        foreach (AttendanceCorrectRequest::all() as $application) {
            $this->actingAs($admin)->post('/stamp_correction_request/approve/'.$application->id);
        }

        $response = $this->actingAs($user)->get('/stamp_correction_request/list');
        $response->assertStatus(200);
        $response->assertSeeInOrder(['id="content2"', '電車遅延のため'], false);
        $response->assertSeeInOrder(['id="content2"', '打刻忘れのため'], false);
    }

    // 各申請の「詳細」を押下すると勤怠詳細画面に遷移する
    public function test_detail_link_goes_to_attendance_detail(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 15, 9, 0));

        $user = User::factory()->create();

        $attendanceRecord = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
        ]);

        $this->actingAs($user)->post('/attendance/detail/'.$attendanceRecord->id, $this->validFormData());

        $application = AttendanceCorrectRequest::first();

        $listResponse = $this->get('/stamp_correction_request/list');
        $listResponse->assertSee('/application/'.$application->id);

        $redirectResponse = $this->get('/application/'.$application->id);
        $redirectResponse->assertRedirect('/attendance/detail/'.$attendanceRecord->id);

        $detailResponse = $this->get('/attendance/detail/'.$attendanceRecord->id);
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('11月2日');
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
