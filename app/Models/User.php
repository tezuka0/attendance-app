<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $fillable = [
        'name',
        'email',
        'password',
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'admin_status' => 'boolean',
    ];

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function attendanceCorrectRequests(): HasMany
    {
        return $this->hasMany(AttendanceCorrectRequest::class);
    }

    //今日の勤怠レコードを返す
    public function todayAttendance(): ?AttendanceRecord
    {
        return $this->attendanceRecords()
            ->whereDate('date', today())
            ->first();
    }

    public function getAttendanceStatusAttribute(): string
    {
        $attendance = $this->todayAttendance();

        //今日の勤怠レコードがない
        if (!$attendance) {
            return '勤務外';
        }

        //退勤時間が入っている
        if ($attendance->clock_out) {
            return '退勤済';
        }

        //終了時刻がからの休憩がある
        if ($attendance->breakTimes()->whereNull('break_end')->exists()){
            return '休憩中';
        }

        //上記以外
        return '出勤中';
    }

    //指定した月の勤怠一覧（1日ずつの配列）
    public function monthlyAttendanceRecords(Carbon $month): array
    {
        $records = $this->attendanceRecords()
            ->with('breakTimes')
            ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->get()
            ->keyBy(fn($record) => $record->date->format('Y-m-d'));

        $formattedAttendanceRecords = [];
        for ($day = $month->copy(); $day->lte($month->copy()->endOfMonth()); $day->addDay()) {
            $record = $records[$day->format('Y-m-d')] ?? null;

            $formattedAttendanceRecords[] = [
                'id' => $record ? $record->id : $day->format('Y-m-d'),
                'date' => $day->locale('ja')->isoFormat('MM/DD(ddd)'),
                'clock_in' => $record ? Carbon::parse($record->clock_in)->format('H:i') : '',
                'clock_out' => $record && $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
                'total_break_time' => $record ? $record->total_break_time : null,
                'total_time' => $record ? $record->total_time : null,
            ];
        }

        return $formattedAttendanceRecords;
    }
}
