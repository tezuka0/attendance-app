<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    //勤怠登録画面を表示する
    public function index()
    {
        $user = Auth::user();

        $formattedDate = now()->locale('ja')->isoFormat('YYYY年M月D日(ddd)');

        $formattedTime = now()->format('H:i');

        return view('user.attendance-register', compact('user', 'formattedDate', 'formattedTime'));
    }

    //打刻する
    public function store(Request $request)
    {
        $user = Auth::user();
        $action = $request->input('action');
        $status = $user->attendance_status;
        $time = now()->format('H:i:s');

        if ($action === 'clock_in' && $status === '勤務外') {
            AttendanceRecord::create([
                'user_id' => $user->id,
                'date' => today(),
                'clock_in' => $time,
            ]);
        }elseif ($action === 'break_in' && $status === '出勤中') {
            //休憩入り
            $user->todayAttendance()->breakTimes()->create([
                'break_start' => $time,
            ]);
        }elseif ($action ==='break_out' && $status === '休憩中') {
            //休憩戻り
            $user->todayAttendance()->breakTimes()
            ->whereNull('break_end')
            ->first()
            ->update(['break_end' => $time]);
        }elseif ($action === 'clock_out' && $status ==='出勤中') {
            //退勤
            $user->todayAttendance()->update([
                'clock_out' => $time,
            ]);
        }

        //打刻後
        return redirect('/attendance');
    }
}
