<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;

class StaffController extends Controller
{
    //スタッフ一覧
    public function index(): View
    {
        $users = User::where('admin_status', false)->get();

        return view('admin.staff-list', compact('users'));
    }

    //スタッフ別の月次勤怠
    public function show(Request $request, string $id): View
    {
        $user = User::where('admin_status', false)->findOrFail($id);

        $date = Carbon::parse($request->query('date', now()))->startOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $formattedAttendanceRecords = $user->monthlyAttendanceRecords($date);

        // 打刻のない日は、管理者の勤怠詳細で対象ユーザーが分かるよう user_id を付ける
        $formattedAttendanceRecords = array_map(function ($attendanceRecord) use ($user) {
            if (ctype_digit((string) $attendanceRecord['id'])) {
                return $attendanceRecord;
            }

            $attendanceRecord['id'] .= '?user_id=' . $user->id;

            return $attendanceRecord;
        }, $formattedAttendanceRecords);

        return view('admin.staff-attendance-list', compact('date', 'previousMonth', 'nextMonth', 'formattedAttendanceRecords', 'user'));
    }
}
