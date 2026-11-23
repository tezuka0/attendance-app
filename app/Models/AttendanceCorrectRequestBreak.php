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

    public function attendanceCorrectRequest(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrectRequest::class);
    }

    public function getBreakInAttribute(): string
    {
        return $this->break_start;
    }

    public function getBreakOutAttribute(): ?string
    {
        return $this->break_end;
    }
}
