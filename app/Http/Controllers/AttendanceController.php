<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceCorrectRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

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

    //勤怠一覧を表示する
    public function list(Request $request)
    {
        $user = Auth::user();

        $date = Carbon::parse($request->query('date',now() ))->startOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $records = $user->attendanceRecords()
            ->with('breakTimes')
            ->whereBetween('date', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()])
            ->get()
            ->keyBy(fn($record) => $record->date->format('Y-m-d'));
        $formattedAttendanceRecords = [];
        for ($day = $date->copy(); $day->lte($date->copy()->endOfMonth()); $day->addDay()) {
            $record = $records[$day->format('Y-m-d')] ?? null;

            $formattedAttendanceRecords[] = [
                'id' => $record ? $record->id : $day->format('Y-m-d'),
                'date' => $day->locale('ja')->isoFormat('MM/DD(ddd)'),
                'clock_in' => $record ? Carbon::parse($record->clock_in)->format('H:i') : '',
                'clock_out' => $record && $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
                'total_break_time' => $record ? $this->formatMinutes($record->totalBreakMinutes()) : null,
                'total_time' => $record ? $this->formatMinutes($record->totalWorkMinutes()) : null,
            ];
        }

        return view('user.user-attendance-list', compact('date', 'previousMonth', 'nextMonth', 'formattedAttendanceRecords'));
    }

    private function formatMinutes(?int $minutes): ?string
    {
        return $minutes === null ? null : sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    //勤怠詳細を表示する
    public function show($id)
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
            ->with('attendanceCorrectRequestBreaks')
            ->whereDate('date', $date)
            ->where('status', AttendanceCorrectRequest::STATUS_PENDING)
            ->first();

        $source = $application ?? $record;
        $breaks = $application ? $application->attendanceCorrectRequestBreaks : ($record ? $record->breakTimes : collect());

        $data = [
            'id' => $record ? $record->id : $date->format('Y-m-d'),
            'year' => $date->format('Y') . '年',
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

