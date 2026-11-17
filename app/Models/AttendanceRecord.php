<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function breakTimes()
    {
        return $this->hasMany(BreakTime::class);
    }

    public function attendanceCorrectRequests()
    {
        return $this->hasMany(AttendanceCorrectRequest::class);
    }

    //休憩時間の合計（分）
    public function totalBreakMinutes(): int
    {
        return $this->breakTimes
            ->filter(fn($break) => !is_null($break->break_end))

            ->sum(fn($break) => Carbon::parse($break->break_start)->diffInMinutes(Carbon::parse($break->break_end)));

    }

    public function getTotalBreakTimeAttribute(): string
    {
        return $this->formatMinutes($this->totalBreakMinutes());
    }

    public function getTotalTimeAttribute(): ?string
    {
        return $this->formatMinutes($this->totalWorkMinutes());
    }

    private function formatMinutes(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    //勤務時間の合計（分）
    public function totalWorkMinutes(): ?int
    {
        if ($this->clock_out === null) {
            return null;
        }

        return Carbon::parse($this->clock_in)->diffInMinutes($this->clock_out) - ($this->totalBreakMinutes());
    }
}
