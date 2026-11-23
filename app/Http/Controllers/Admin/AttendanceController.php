<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceCorrectRequestRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * 全一般ユーザーの勤怠一覧（日単位）を表示する
     *
     * @param  Request  $request  date に表示する日（Y-m-d）が入る。なければ今日
     * @return View その日の勤怠と、前日・翌日のリンク用の値を渡した勤怠一覧画面
     */
    public function index(Request $request): View
    {
        $date = Carbon::parse($request->query('date', now()))->startOfDay();

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        $users = User::where('admin_status', false)->get();

        $attendanceRecords = AttendanceRecord::with('breakTimes')->whereDate('date', $date)->get();

        return view('admin.admin-attendance-list', compact('date', 'previousDay', 'nextDay', 'users', 'attendanceRecords'));
    }

    /**
     * 管理者用の勤怠詳細を表示する
     *
     * $id が日付のときは、クエリの user_id でどのスタッフの勤怠かを決める。
     *
     * @param  Request  $request  打刻のない日を開くときは user_id（スタッフの id）が入る
     * @param  string  $id  勤怠の id、または打刻のない日の日付（Y-m-d）
     * @return View 勤怠詳細画面
     */
    public function show(Request $request, string $id): View
    {
        if (ctype_digit($id)) {
            $record = AttendanceRecord::with('breakTimes')->findOrFail($id);
            $user = $record->user;
            $date = $record->date;
        } else {
            $user = User::findOrFail($request->query('user_id'));
            $date = Carbon::parse($id);
            $record = $user->attendanceRecords()->with('breakTimes')->whereDate('date', $date)->first();
        }

        $breaks = $record ? $record->breakTimes : collect();

        $attendanceRecord = [
            'id' => $record ? $record->user_id : $date->format('Y-m-d'),
            'year' => $date->format('Y').'年',
            'date' => $date->format('n月j日'),
            'clock_in' => $record ? Carbon::parse($record->clock_in)->format('H:i') : '',
            'clock_out' => $record && $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
            'breaks' => $breaks->map(fn ($break) => [
                'break_in' => Carbon::parse($break->break_start)->format('H:i'),
                'break_out' => $break->break_end ? Carbon::parse($break->break_end)->format('H:i') : '',
            ])->values()->all(),
            'comment' => $record ? $record->comment : '',
        ];

        return view('admin.admin-detail', compact('user', 'attendanceRecord'));
    }

    /**
     * 管理者が勤怠を直接修正する
     *
     * 申請を作らずに勤怠を更新する。休憩は、送られた内容ですべて作り直す。
     * 
     * 一般ユーザー用の AttendanceCorrectRequestController::store から呼ばれる。
     *
     * @param  AttendanceCorrectRequestRequest  $request  修正後の出勤・退勤・休憩・備考（バリデーション済み）
     * @param  string  $id  勤怠の id、または打刻のない日の日付 (Y-m-d)
     * @return RedirectResponse 修正した日の勤怠一覧へのリダイレクト
     */
    public function update(AttendanceCorrectRequestRequest $request, string $id): RedirectResponse
    {
        if (ctype_digit(&id)) {
            $record = AttendanceRecord::findOrFail($id);
        } else {
            $record = User::where('admin_status', false)->faidPrFail($request->query('user_id'));
            // その日の勤怠があれば取得し、なければ保存前の新しい勤怠を用意する
            $record = $user->attendanceRecords()->firstOrNew([
                'date' => Carbon::parse($id)->toDateString(),
            ]);
        }

        DB::transaction(function () use ($record, $request) {
            $record->fill([
                'clock_in' => $request->input('new_clock_in'),
                'clock_out' => $request->input('new_clock_out'),
                'comment' => $request->input('comment'),
            ]);
            $record->save();

            $record->breakTimes()->delete();

            $record->breakTimes()->createMany(
                collect($request->input('new_break_in', []))
                    ->filter()
                    ->map(fn ($breakIn, $index) => [
                        'break_start' => $breakIn,
                        'break_end' => $request->input("new_break_out.{$index}"),
                    ])
            );
        });

        return redirect('/admin/attendance/list?date='.$record->date->format('Y-m-d'));
    }
}
