<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

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

    /** ユーザーの勤怠 */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /** ユーザーの修正申請 */
    public function attendanceCorrectRequests(): HasMany
    {
        return $this->hasMany(AttendanceCorrectRequest::class);
    }

    /**
     * 今日の勤怠を返す
     *
     * @return AttendanceRecord|null 今日の勤怠。まだ出勤していなければ null
     */
    public function todayAttendance(): ?AttendanceRecord
    {
        return $this->attendanceRecords()
            ->whereDate('date', today())
            ->first();
    }

    /**
     * 今日の勤怠ステータスを返す（$user->attendance_status で使う）
     *
     * 次の順で判定する。
     * 今日の勤怠がない → 勤務外 / 退勤している → 退勤済 /
     * 終わっていない休憩がある → 休憩中 / それ以外 → 出勤中
     *
     * @return string 勤務外・出勤中・休憩中・退勤済のいずれか
     */
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

        //終了時刻が空の休憩がある
        if ($attendance->breakTimes()->whereNull('break_end')->exists()) {
            return '休憩中';
        }

        //上記以外
        return '出勤中';
    }

    /**
     * 指定した月の勤怠を、1日ずつの表示用データにして返す
     *
     * 打刻のない日も、空の行として含める（その日の id には日付 Y-m-d が入る）。
     *
     * @param Carbon $month 表示する月（その月の1日）
     * @return array<int, array{id: int|string, date: string, clock_in: string, clock_out: string, total_break_time: ?string, total_time: ?string}>
     */
    public function monthlyAttendanceRecords(Carbon $month): array
    {
        $records = $this->attendanceRecords()
            ->with('breakTimes')
            ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->get()
            ->keyBy(fn($record) => $record->date->format('Y-m-d'));

        $days = CarbonPeriod::create($month->copy()->startOfMonth(), $month->copy()->endOfMonth());

        return collect($days)
            ->map(function (Carbon $day) use ($records) {
                $record = $records[$day->format('Y-m-d')] ?? null;

                return [
                    'id' => $record ? $record->id : $day->format('Y-m-d'),
                    'date' => $day->locale('ja')->isoFormat('MM/DD(ddd)'),
                    'clock_in' => $record ? Carbon::parse($record->clock_in)->format('H:i') : '',
                    'clock_out' => $record && $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
                    'total_break_time' => $record ? $record->total_break_time : null,
                    'total_time' => $record ? $record->total_time : null,
                ];
            })
            ->all();
    }
}
