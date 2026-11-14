<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Laravel\Sanctum\HasApiTokens;

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

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function attendanceCorrectRequests()
    {
        return $this->hasMany(AttendanceCorrectRequest::class);
    }

    //今日の勤怠レコードを返す
    public function todayAttendance()
    {
        return $this->attendanceRecords()
            ->whereDate('date', today())
            ->first();
    }

    public function getAttendanceStatusAttribute()
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

}
