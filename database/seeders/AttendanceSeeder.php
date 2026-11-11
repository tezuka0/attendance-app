<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //user1 レポート検証用の固定データ
        $user1 = User::where('email', 'user1@example.com')->first();
        $this->seedUser1($user1);

        //user2 ・user3 : 実運用に近いランダムデータ
        $others = User::whereIn('email', ['user2@example.com', 'user3@example.com'])->get();
        foreach ($others as $user) {
            $this->seedRandom($user);
        }
    }

    //user1 :過去５ヶ月（各15日） + 当月17日
    private function seedUser1(User $user): void
    {
        //過去５ヶ月 : ５ヶ月前 → １ヶ月前の順に、各月15日を通常勤務で作る
        for ($i = 5; $i >= 1; $i--) {
            $month = now()->subMonthsNoOverflow($i)->startOfMonth();

            foreach ($this->weekdaysOf($month, 15) as $date) {
                $this->createFixed($user, $date, '09:00:00', '18:00:00');
            }
        }

        //当月 : 17日分のパターン（出勤、退勤）
        $patterns = array_merge(
            array_fill(0, 10, ['09:00:00', '18:00:00']), // 通常
            array_fill(0, 3, ['09:00:00', '20:00:00']),  // 残業
            array_fill(0, 2, ['09:30:00', '18:00:00']),  // 遅刻
            [['09:00:00', '17:00:00']],                  // 早退
            [['08:00:00', '21:00:00']],                  // 長時間労働
        );

        $dates = $this->weekdaysOf(now()->startOfMonth(), count($patterns));

        foreach ($dates as $index => $date) {
            [$clockIn, $clockOut] = $patterns[$index];
            $this->createFixed($user, $date, $clockIn, $clockOut);
        }
    }

    //時刻を指定して勤怠一件＋休憩 12:00-13:00 を作成する
    private function createFixed(User $user, Carbon $date, string $clockIn, string $clockOut): void
    {
        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $date->toDateString(),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
        ]);

        BreakTime::create([
            'attendance_record_id' => $record->id,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);
    }

    // 5ヶ月前の月初〜昨日の平日。1割は休み
    private function seedRandom(User $user): void
    {
        $period = CarbonPeriod::create(
            now()->subMonthsNoOverflow(5)->startOfMonth(),
            today()->subDay()
        );

        foreach ($period as $date) {
            if (!$date->isWeekday() || fake()->boolean(10)) {
                continue;
            }

            $record = AttendanceRecord::factory()->create([
                'user_id' => $user->id,
                'date' => $date->toDateString(),
            ]);

            BreakTime::factory()->create([
                'attendance_record_id' => $record->id,
            ]);
        }
    }

    // その月の平日を、月初から $count 日分返す
    private function weekdaysOf(Carbon $month, int $count): array
    {
        $dates = [];
        $date = $month->copy()->startOfMonth();

        while (count($dates) < $count) {
            if ($date->isWeekday()) {
                $dates[] = $date->copy();
            }
            $date->addDay();
        }

        return $dates;
    }
}
