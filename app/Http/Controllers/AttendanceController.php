<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrectRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * 勤怠登録画面を表示する
     *
     * @return View 今日の日付・現在時刻・ログインユーザーを渡した勤怠登録画面
     */
    public function index(): View
    {
        $user = Auth::user();

        $formattedDate = now()->locale('ja')->isoFormat('YYYY年M月D日(ddd)');

        $formattedTime = now()->format('H:i');

        return view('user.attendance-register', compact('user', 'formattedDate', 'formattedTime'));
    }

    /**
     * 打刻する（出勤・休憩入・休憩戻・退勤）
     *
     * 押されたボタンと今のステータスの組み合わせが正しいときだけ記録する。
     *
     * @param  Request  $request  action に clock_in / break_in / break_out / clock_out のいずれかが入る
     * @return RedirectResponse 勤怠登録画面へのリダイレクト
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $action = $request->input('action');
        $status = $user->attendance_status;
        $time = now()->format('H:i:s');

        if ($action === 'clock_in' && $status === User::STATUS_OFF_DUTY) {
            AttendanceRecord::create([
                'user_id' => $user->id,
                'date' => today(),
                'clock_in' => $time,
            ]);
        } elseif ($action === 'break_in' && $status === User::STATUS_WORKING) {
            // 休憩入り
            $user->todayAttendance()->breakTimes()->create([
                'break_start' => $time,
            ]);
        } elseif ($action === 'break_out' && $status === User::STATUS_ON_BREAK) {
            // 休憩戻り
            $user->todayAttendance()->breakTimes()
                ->whereNull('break_end')
                ->first()
                ->update(['break_end' => $time]);
        } elseif ($action === 'clock_out' && $status === User::STATUS_WORKING) {
            // 退勤
            $user->todayAttendance()->update([
                'clock_out' => $time,
            ]);
        }

        // 打刻後
        return redirect('/attendance');
    }

    /**
     * ログインユーザーの勤怠一覧（月単位）を表示する
     *
     * @param  Request  $request  date に表示する月（Y-m）が入る。なければ今月
     * @return View 1か月分の勤怠と、前月・翌月のリンク用の値を渡した勤怠一覧画面
     */
    public function list(Request $request): View
    {
        $user = Auth::user();

        $date = Carbon::parse($request->query('date', now()))->startOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $formattedAttendanceRecords = $user->monthlyAttendanceRecords($date);

        return view('user.user-attendance-list', compact('date', 'previousMonth', 'nextMonth', 'formattedAttendanceRecords'));
    }

    /**
     * 一覧の「詳細」リンクから、勤怠詳細画面へリダイレクトする
     *
     * 管理者は管理者用の勤怠詳細へ、一般ユーザーは一般ユーザー用の勤怠詳細へ移動する。
     *
     * @param  Request  $request  管理者のときは user_id（スタッフの id）が入ることがある
     * @param  string  $id  勤怠の id、または打刻のない日の日付（Y-m-d）
     * @return RedirectResponse 勤怠詳細画面へのリダイレクト
     */
    public function redirectToDetail(Request $request, string $id): RedirectResponse
    {
        $user = Auth::user();

        if ($user->admin_status) {
            $url = "/admin/attendance/{$id}";

            $userId = $request->query('user_id');
            if ($userId) {
                $url .= '?user_id='.$userId;
            }

            return redirect($url);
        }

        return redirect("/attendance/detail/{$id}");
    }

    /**
     * ログインユーザーの勤怠詳細を表示する
     *
     * 承認待ちの修正申請があるときは、申請の内容を表示する。
     *
     * @param  string  $id  勤怠の id、または打刻のない日の日付（Y-m-d）
     * @return View 勤怠詳細画面
     */
    public function show(string $id): View
    {
        $user = Auth::user();

        if (ctype_digit($id)) {
            $record = $user->attendanceRecords()->with('breakTimes')->findOrFail($id);
            $date = $record->date;
        } else {
            $date = Carbon::parse($id);
            $record = $user->attendanceRecords()->with('breakTimes')->whereDate('date', $date)->first();
        }

        $application = $user->attendanceCorrectRequests()
            ->with('proposalBreaks')
            ->whereDate('date', $date)
            ->where('status', AttendanceCorrectRequest::STATUS_PENDING)
            ->first();

        $source = $application ?? $record;
        $breaks = $application ? $application->proposalBreaks : ($record ? $record->breakTimes : collect());

        $data = [
            'id' => $record ? $record->id : $date->format('Y-m-d'),
            'year' => $date->format('Y').'年',
            'date' => $date->format('n月j日'),
            'clock_in' => $source ? Carbon::parse($source->clock_in)->format('H:i') : '',
            'clock_out' => $source && $source->clock_out ? Carbon::parse($source->clock_out)->format('H:i') : '',
            'breaks' => $breaks->map(fn ($break) => [
                'break_in' => Carbon::parse($break->break_start)->format('H:i'),
                'break_out' => $break->break_end ? Carbon::parse($break->break_end)->format('H:i') : '',
            ])->values()->all(),
            'comment' => $source ? $source->comment : '',
            'application' => $application,
        ];

        return view('user.user-detail', compact('user', 'data'));
    }
}
