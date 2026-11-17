<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceCorrectRequestRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    //日次勤怠一覧
    public function index(Request $request)
    {
        $date = Carbon::parse($request->query('date', now()))->startOfDay();

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        $users = User::where('admin_status', false)->get();

        $attendanceRecords = AttendanceRecord::with('breakTimes')->whereDate('date', $date)->get();

        return view('admin.admin-attendance-list', compact('date', 'previousDay', 'nextDay', 'users', 'attendanceRecords'));
    }

    //管理者の勤怠詳細
    public function show(Request $request, $id)
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
            'id' => $record ? $record->id : $date->format('Y-m-d'),
            'year' => $date->format('Y') . '年',
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

    //管理者が勤怠を直接修正する
    public function update(AttendanceCorrectRequestRequest $request, $id)
    {
        $record = AttendanceRecord::findOrFail($id);

        DB::transaction(function () use ($record, $request) {
            $record->update([
                'clock_in' => $request->input('new_clock_in'),
                'clock_out' => $request->input('new_clock_out'),
                'comment' => $request->input('comment'),
            ]);

            $record->breakTimes()->delete();

            foreach ($request->input('new_break_in', []) as $index => $breakIn) {
                if (empty($breakIn)) {
                    continue;
                }

                $record->breakTimes()->create([
                    'break_start' => $breakIn,
                    'break_end' => $request->input("new_break_out.{$index}"),
                ]);
            }
        });

        return redirect('/admin/attendance/list?date=' . $record->date->format('Y-m-d'));
    }
}
