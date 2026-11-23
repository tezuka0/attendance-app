<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** 勤怠のユーザー */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 勤怠の休憩 */
    public function breakTimes(): HasMany
    {
        return $this->hasMany(BreakTime::class);
    }

    /** 勤怠への修正申請 */
    public function attendanceCorrectRequests(): HasMany
    {
        return $this->hasMany(AttendanceCorrectRequest::class);
    }

    /**
     * 休憩時間の合計を分で返す
     *
     * 終わっていない休憩（break_end が空）は数えない。
     *
     * @return int 休憩時間の合計（分）
     */
    public function totalBreakMinutes(): int
    {
        return $this->breakTimes
            ->filter(fn($break) => !is_null($break->break_end))
            ->sum(fn($break) => Carbon::parse($break->break_start)->diffInMinutes(Carbon::parse($break->break_end)));
    }

    /**
     * 休憩時間の合計を表示用の文字列で返す（$record->total_break_time で使う）
     *
     * @return string H:MM 形式（例：1:00）
     */
    public function getTotalBreakTimeAttribute(): string
    {
        return $this->formatMinutes($this->totalBreakMinutes());
    }

    /**
     * 勤務時間の合計を表示用の文字列で返す（$record->total_time で使う）
     *
     * @return string|null H:MM 形式（例：8:00）。退勤していなければ null
     */
    public function getTotalTimeAttribute(): ?string
    {
        return $this->formatMinutes($this->totalWorkMinutes());
    }

    /**
     * 分を H:MM 形式の文字列に変える
     *
     * @param int|null $minutes 分
     * @return string|null H:MM 形式の文字列。$minutes が null なら null
     */
    private function formatMinutes(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * 勤務時間の合計を分で返す（退勤 − 出勤 − 休憩）
     *
     * @return int|null 勤務時間の合計（分）。退勤していなければ null
     */
    public function totalWorkMinutes(): ?int
    {
        if ($this->clock_out === null) {
            return null;
        }

        return Carbon::parse($this->clock_in)->diffInMinutes($this->clock_out) - ($this->totalBreakMinutes());
    }
}
