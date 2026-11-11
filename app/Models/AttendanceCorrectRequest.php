<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function attendanceCorrectRequestBreaks()
    {
        return $this->hasMany(AttendanceCorrectRequestBreak::class);
    }
}
