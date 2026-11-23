<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectRequestRequest;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\AttendanceCorrectRequestController as AdminAttendanceCorrectRequestController;
use App\Models\AttendanceCorrectRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Carbon\Carbon;

class AttendanceCorrectRequestController extends Controller
{
    /**
     * 申請一覧の「詳細」リンクから、対応する勤怠詳細画面へリダイレクトする
     *
     * 自分の申請だけを探す。他人の申請や存在しない id のときは 404 になる。
     *
     * @param string $id 修正申請の id
     * @return RedirectResponse 勤怠詳細画面へのリダイレクト（勤怠がない日の申請は日付で移動する）
     */
    public function redirectToDetail(string $id): RedirectResponse
    {
        // 自分の申請から探す（他人の申請や存在しない id なら 404）
        $application = Auth::user()->attendanceCorrectRequests()->findOrFail($id);

        // 勤怠の id があればそれ、打刻のない日の申請なら日付
        $target = $application->attendance_record_id ?? $application->date->format('Y-m-d');

        // 勤怠詳細へ移動する
        return redirect("/attendance/detail/{$target}");
    }

    /**
     * 勤怠の修正申請を保存する
     *
     * 管理者のときは、申請を作らずに勤怠を直接修正する処理に任せる。
     * 同じ日に承認待ちの申請がすでにあるときは、新しく作らない。
     *
     * @param AttendanceCorrectRequestRequest $request 修正後の出勤・退勤・休憩・備考（バリデーション済み）
     * @param string $id 勤怠の id、または打刻のない日の日付（Y-m-d）
     * @return RedirectResponse 申請一覧画面へのリダイレクト（管理者のときは勤怠一覧へ）
     */
    public function store(AttendanceCorrectRequestRequest $request, string $id): RedirectResponse
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

        $application->proposalBreaks()->createMany(
            collect($request->input('new_break_in', []))
                ->filter()
                ->map(fn ($breakIn, $index) => [
                    'break_start' => $breakIn,
                    'break_end' => $request->input("new_break_out.{$index}"),
                ])
        );

        return redirect('/stamp_correction_request/list');
    }

    /**
     * ログインユーザーの修正申請一覧を表示する
     *
     * 管理者のときは、全ユーザーの申請一覧を表示する処理に任せる。
     *
     * @return View 承認待ち・承認済みの申請を渡した申請一覧画面
     */
    public function index(): View
    {
        $user = Auth::user();

        if ($user->admin_status) {
            return app(AdminAttendanceCorrectRequestController::class)->index();
        }

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
