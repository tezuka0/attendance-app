<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AttendanceCorrectRequest extends Model
{
    use HasFactory;

    const STATUS_PENDING = 0; // 承認待ち
    const STATUS_APPROVED = 1; // 承認済み

    protected $fillable = [
        'user_id',
        'attendance_record_id',
        'date',
        'clock_in',
        'clock_out',
        'comment',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class)
            ->withDefault(function ($attendanceRecord, $application) {
                $attendanceRecord->date = $application->date;
            });
    }

    public function proposalBreaks()
    {
        return $this->hasMany(AttendanceCorrectRequestBreak::class);
    }

    //承認状態(承認待ち/承認済み)
    public function getApprovalStatusAttribute(): string
    {
        return $this->status === self::STATUS_PENDING ? '承認待ち' : '承認済み';
    }

    //申請日時
    public function getApplicationDateAttribute()
    {
        return $this->created_at;
    }

    //申請後の日
    public function getNewDateAttribute()
    {
        return $this->date;
    }

    //申請後の出勤時間
    public function getNewClockInAttribute()
    {
        return Carbon::parse($this->clock_in)->format('H:i');
    }

    //申請後の退勤時間
    public function getNewClockOutAttribute()
    {
        return Carbon::parse($this->clock_out)->format('H:i');
    }
}
