<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrectRequest;
use App\Models\AttendanceRecord;
use Illuminate\Support\Facades\DB;

class AttendanceCorrectRequestController extends Controller
{
    //申請一覧（全ユーザー）
    public function index()
    {
        $applications = AttendanceCorrectRequest::with('user', 'attendanceRecord')
            ->latest()
            ->get();

        return view('admin.admin-application-list', compact('applications'));
    }

    //修正申請承認画面
    public function show($attendanceCorrectRequestId)
    {
        $application = AttendanceCorrectRequest::with('user', 'proposalBreaks')
            ->findOrFail($attendanceCorrectRequestId);

        $user = $application->user;

        return view('admin.admin-application-detail', compact('user', 'application'));
    }

    //修正申請を承認する
    public function approve($attendanceCorrectRequestId)
    {
        $application = AttendanceCorrectRequest::with('proposalBreaks')
            ->findOrFail($attendanceCorrectRequestId);

        //承認済みなら何もしない
        if ($application->status === AttendanceCorrectRequest::STATUS_APPROVED) {
            return redirect('/stamp_correction_request/approve/' . $application->id);
        }

        DB::transaction(function () use ($application) {
            //勤怠を更新する（打刻のない日は新しく作る）
            $record = AttendanceRecord::updateOrCreate(
                ['user_id' => $application->user_id, 'date' => $application->date->toDateString()],
                [
                    'clock_in' => $application->clock_in,
                    'clock_out' => $application->clock_out,
                    'comment' => $application->comment,
                ]
            );

            //休憩を申請の内容で作り直す
            $record->breakTimes()->delete();

            foreach ($application->proposalBreaks as $break) {
                $record->breakTimes()->create([
                    'break_start' => $break->break_start,
                    'break_end' => $break->break_end,
                ]);
            }

            //申請を承認済みにし、勤怠と結びつける
            $application->update([
                'status' => AttendanceCorrectRequest::STATUS_APPROVED,
                'attendance_record_id' => $record->id,
            ]);
        });

        return redirect('/stamp_correction_request/approve/' . $application->id);
    }
}
