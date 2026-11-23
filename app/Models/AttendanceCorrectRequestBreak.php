<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrectRequestBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_correct_request_id',
        'break_start',
        'break_end',
    ];

    /** 申請休憩の修正申請 */
    public function attendanceCorrectRequest(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrectRequest::class);
    }

    /** 休憩開始の時刻を返す（$break->break_in で使う） */
    public function getBreakInAttribute(): string
    {
        return $this->break_start;
    }

    /** 休憩終了の時刻を返す（$break->break_out で使う）。未入力なら null */
    public function getBreakOutAttribute(): ?string
    {
        return $this->break_end;
    }
}
