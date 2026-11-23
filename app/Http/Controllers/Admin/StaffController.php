<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffController extends Controller
{
    const UTF8_BOM = "\xEF\xBB\xBF";

    const CSV_HEADERS = ['日付', '出勤', '退勤', '休憩', '合計'];

    /**
     * 全一般ユーザーのスタッフ一覧を表示する
     *
     * 管理者は一覧に出さない。
     *
     * @return View 一般ユーザーを渡したスタッフ一覧画面
     */
    public function index(): View
    {
        $users = User::where('admin_status', false)->get();

        return view('admin.staff-list', compact('users'));
    }

    /**
     * スタッフ1人の勤怠一覧（月単位）を表示する
     *
     * 打刻のない日の詳細リンクには、どのスタッフの勤怠かがわかるよう user_id を付ける。
     *
     * @param Request $request date に表示する月（Y-m）が入る。なければ今月
     * @param string $id スタッフ（一般ユーザー）の id
     * @return View 1か月分の勤怠と、前月・翌月のリンク用の値を渡したスタッフ別勤怠一覧画面
     */
    public function show(Request $request, string $id): View
    {
        $user = User::where('admin_status', false)->findOrFail($id);

        $date = Carbon::parse($request->query('date', now()))->startOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $formattedAttendanceRecords = $user->monthlyAttendanceRecords($date);

        // 打刻のない日は、管理者の勤怠詳細で対象ユーザーが分かるよう user_id を付ける
        $formattedAttendanceRecords = collect($formattedAttendanceRecords)
            ->map(function ($attendanceRecord) use ($user) {
                if (ctype_digit((string) $attendanceRecord['id'])) {
                    return $attendanceRecord;
                }

                $attendanceRecord['id'] .= '?user_id=' . $user->id;

                return $attendanceRecord;
            })
            ->all();

        return view('admin.staff-attendance-list', compact('date', 'previousMonth', 'nextMonth', 'formattedAttendanceRecords', 'user'));
    }

    /**
     * スタッフ1人の1か月分の勤怠を CSV でダウンロードさせる
     *
     * Excel で開いても文字化けしないよう、先頭に BOM を付ける。
     *
     * @param Request $request user_id にスタッフの id、year_month に出力する月（Y-m）が入る
     * @return StreamedResponse 勤怠一覧の CSV ファイル
     */
    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('admin_status', false)],
            'year_month' => ['required', 'date_format:Y-m'],
        ]);

        $user = User::findOrFail($request->input('user_id'));
        $month = Carbon::parse($request->input('year_month'))->startOfMonth();

        $attendanceRecords = $user->monthlyAttendanceRecords($month);

        $fileName = $user->name . '_' . $month->format('Y-m') . '_勤怠.csv';

        return response()->streamDownload(function () use ($attendanceRecords) {
            $stream = fopen('php://output', 'w');

            fwrite($stream, self::UTF8_BOM);
            fputcsv($stream, self::CSV_HEADERS, escape: '');

            foreach ($attendanceRecords as $attendanceRecord) {
                fputcsv($stream, [
                    $attendanceRecord['date'],
                    $attendanceRecord['clock_in'],
                    $attendanceRecord['clock_out'],
                    $attendanceRecord['total_break_time'],
                    $attendanceRecord['total_time'],
                ], escape: '');
            }

            fclose($stream);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
