<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrectRequest;
use App\Models\AttendanceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendanceCorrectRequestController extends Controller
{
    /**
     * 全ユーザーの修正申請一覧を表示する
     *
     * 一般ユーザー用の AttendanceCorrectRequestController::index から呼ばれる。
     *
     * @return View 全ユーザーの申請（新しい順）を渡した申請一覧画面
     */
    public function index(): View
    {
        $applications = AttendanceCorrectRequest::with('user', 'AttendanceRecord')
            ->latest()
            ->get();

        return view('admin.admin-application-list', compact('applications'));
    }

    /**
     * 修正申請の承認画面を表示する
     *
     * @param  string  $attendanceCorrectRequestId  修正申請の id
     * @return View 申請の内容と申請したユーザーを渡した承認画面
     */
    public function show(string $attendanceCorrectRequestId): View
    {
        $application = AttendanceCorrectRequest::with('user', 'proposalBreaks')
            ->findOrFail($attendanceCorrectRequestId);

        $user = $application->user;

        return view('admin.admin-application-detail', compact('user', 'application'));
    }

    /**
     * 修正申請を承認し、勤怠に反映する
     *
     * 勤怠を申請の内容で更新し（打刻のない日は新しく作る）、休憩は申請の内容で作り直す。
     * 最後に申請を承認済みにする。すでに承認済みのときは何もしない。
     *
     * @param  string  $attendanceCorrectRequestId  修正申請の id
     * @return RedirectResponse 承認画面へのリダイレクト
     */
    public function approve(string $attendanceCorrectRequestId): RedirectResponse
    {
        $application = AttendanceCorrectRequest::with('proposalBreaks')
            ->findOrFail($attendanceCorrectRequestId);

        // 承認済みなら何もしない
        if ($application->status === AttendanceCorrectRequest::STATUS_APPROVED) {
            return redirect('/stamp_correction_request/approve/'.$application->id);
        }

        DB::transaction(function () use ($application) {
            // 勤怠を更新する（打刻のない日は新しく作る）
            $record = AttendanceRecord::updateOrCreate(
                ['user_id' => $application->user_id, 'date' => $application->date->toDateString()],
                [
                    'clock_in' => $application->clock_in,
                    'clock_out' => $application->clock_out,
                    'comment' => $application->comment,
                ]
            );

            // 休憩を申請の内容で作り直す
            $record->breakTimes()->delete();

            $record->breakTimes()->createMany(
                $application->proposalBreaks->map(fn ($break) => [
                    'break_start' => $break->break_start,
                    'break_end' => $break->break_end,
                ])
            );

            // 申請を承認済みにし、勤怠と結びつける
            $application->update([
                'status' => AttendanceCorrectRequest::STATUS_APPROVED,
                'attendance_record_id' => $record->id,
            ]);
        });

        return redirect('/stamp_correction_request/approve/'.$application->id);
    }
}
