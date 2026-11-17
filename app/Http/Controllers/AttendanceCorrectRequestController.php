<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectRequestRequest;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Models\AttendanceCorrectRequest;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AttendanceCorrectRequestController extends Controller
{
    // 申請一覧の「詳細」から、対応する勤怠詳細へ移動する
    public function redirectToDetail($id)
    {
        // 自分の申請から探す（他人の申請や存在しない id なら 404）
        $application = Auth::user()->attendanceCorrectRequests()->findOrFail($id);

        // 勤怠の id があればそれ、打刻のない日の申請なら日付
        $target = $application->attendance_record_id ?? $application->date->format('Y-m-d');

        // 勤怠詳細へ移動する
        return redirect("/attendance/detail/{$target}");
    }

    // 修正申請を保存する
    public function store(AttendanceCorrectRequestRequest $request, $id)
    {
        $user = Auth::user();

        if ($user->admin_status) {
            return app(AdminAttendanceController::class)->update($request, $id);
        }

        if (ctype_digit($id)) {
            $record = $user->attendanceRecords()->findOrFail($id);
            $date = $record->date;
        } else {
            $date = Carbon::parse($id);
            $record = $user->attendanceRecords()->whereDate('date', $date)->first();
        }

        $exists = $user->attendanceCorrectRequests()
            ->whereDate('date', $date)
            ->where('status', AttendanceCorrectRequest::STATUS_PENDING)
            ->exists();
        if ($exists) {
            return redirect('/stamp_correction_request/list');
        }

        $application = $user->attendanceCorrectRequests()->create([
            'attendance_record_id' => $record ? $record->id : null,
            'date' => $date,
            'clock_in' => $request->input('new_clock_in'),
            'clock_out' => $request->input('new_clock_out'),
            'comment' => $request->input('comment'),
            'status' => AttendanceCorrectRequest::STATUS_PENDING,
        ]);

        foreach ($request->input('new_break_in', []) as $index => $breakIn) {
            if (empty($breakIn)) {
                continue;
            }

            $breakOut = $request->input("new_break_out.{$index}");
            $application->attendanceCorrectRequestBreaks()->create([
                'break_start' => $breakIn,
                'break_end' => $breakOut,
            ]);
        }

        return redirect('/stamp_correction_request/list');
    }

    // 申請一覧を表示する
    public function index()
    {
        $user = Auth::user();

        $formattedApplications = $user->attendanceCorrectRequests()
            ->latest()
            ->get()
            ->map(fn ($application) => [
                'id' => $application->id,
                'approval_status' => $application->status === AttendanceCorrectRequest::STATUS_PENDING ? '承認待ち' : '承認済み',
                'date' => $application->date->format('Y/m/d'),
                'comment' => $application->comment,
                'application_date' => $application->created_at->format('Y/m/d'),
            ])
            ->all();

        return view('user.user-application-list', compact('user', 'formattedApplications'));
    }
}
