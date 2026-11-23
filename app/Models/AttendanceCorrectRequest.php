<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** 申請したユーザー */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 申請の対象の勤怠
     *
     * 打刻のない日の申請でも、申請の日付だけを入れた空の勤怠を返す。
     */
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class)
            ->withDefault(function ($attendanceRecord, $application) {
                $attendanceRecord->date = $application->date;
            });
    }

    /** 申請した休憩 */
    public function proposalBreaks(): HasMany
    {
        return $this->hasMany(AttendanceCorrectRequestBreak::class);
    }

    /** 承認状態を返す（承認待ち／承認済み） */
    public function getApprovalStatusAttribute(): string
    {
        return $this->status === self::STATUS_PENDING ? '承認待ち' : '承認済み';
    }

    /** 申請日時を返す（作成日時） */
    public function getApplicationDateAttribute(): Carbon
    {
        return $this->created_at;
    }

    /** 申請後の日付を返す */
    public function getNewDateAttribute(): Carbon
    {
        return $this->date;
    }

    /** 申請後の出勤時刻を H:i 形式で返す */
    public function getNewClockInAttribute(): string
    {
        return Carbon::parse($this->clock_in)->format('H:i');
    }

    /** 申請後の退勤時刻を H:i 形式で返す */
    public function getNewClockOutAttribute(): string
    {
        return Carbon::parse($this->clock_out)->format('H:i');
    }
}
