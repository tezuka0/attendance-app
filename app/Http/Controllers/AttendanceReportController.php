<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AttendanceReportController extends Controller
{
    // レポートで集計する月数（当月を含む）
    const REPORT_MONTHS = 6;

    // 始業時刻。これを超えた出勤は遅刻
    const WORK_START_TIME = '09:00:00';

    // 終業時刻。これより前の退勤は早退
    const WORK_END_TIME = '18:00:00';

    // 長時間労働の基準（分）。これを超えた日は長時間労働
    const LONG_WORK_MINUTES = 600;

    // マイ勤怠レポートを表示する
    public function index()
    {
        // 集計の期間：5ヶ月前の月初 〜 当月末
        $startOn = now()->subMonthsNoOverflow(self::REPORT_MONTHS - 1)->startOfMonth();
        $endOn = now()->endOfMonth();

        // 期間内の勤怠を休憩と一緒に取得し、退勤していない日は除く
        $records = Auth::user()->attendanceRecords()
            ->with('breakTimes')
            ->whereBetween('date', [$startOn, $endOn])
            ->get()
            ->filter(fn ($record) => $record->clock_out !== null);

        // 基本サマリー
        $summary = $this->buildSummary($records);

        // 月次推移
        $monthlyTrend = $this->buildMonthlyTrend($records, $startOn);

        // 今月の異常検知
        $anomalies = $this->buildAnomalies($records);

        return view('reports.index', compact('summary', 'monthlyTrend', 'anomalies'));
    }

    // 基本サマリー（総労働時間・総残業時間・1日あたりの平均労働時間）を作る
    private function buildSummary(Collection $records): array
    {
        $totalWorkMinutes = $records->sum(fn ($record) => $record->totalWorkMinutes());
        $workedDays = $records->count();

        return [
            'total_work_minutes' => $totalWorkMinutes,
            'total_overtime_minutes' => $records->sum(fn ($record) => $record->overtimeMinutes()),
            'avg_work_minutes' => $workedDays === 0 ? 0 : intdiv($totalWorkMinutes, $workedDays),
        ];
    }

    // 月次推移（6ヶ月分の労働時間・残業時間）を作る。データがない月は 0 にする
    private function buildMonthlyTrend(Collection $records, Carbon $startOn): array
    {
        $recordsByMonth = $records->groupBy(fn ($record) => $record->date->format('Y-m'));

        return collect(range(0, self::REPORT_MONTHS - 1))
            ->map(function ($offset) use ($startOn, $recordsByMonth) {
                $month = $startOn->copy()->addMonthsNoOverflow($offset);
                $monthRecords = $recordsByMonth->get($month->format('Y-m'), collect());

                return [
                    'month' => $month->format('Y/m'),
                    'work_minutes' => $monthRecords->sum(fn ($record) => $record->totalWorkMinutes()),
                    'overtime_minutes' => $monthRecords->sum(fn ($record) => $record->overtimeMinutes()),
                ];
            })
            ->all();
    }

    // 今月の異常検知（遅刻・早退・長時間労働の回数）を作る
    private function buildAnomalies(Collection $records): array
    {
        $currentMonthRecords = $records->filter(fn ($record) => $record->date->isSameMonth(now()));

        return [
            'late_count' => $currentMonthRecords
                ->filter(fn ($record) => $record->clock_in > self::WORK_START_TIME)
                ->count(),
            'early_leave_count' => $currentMonthRecords
                ->filter(fn ($record) => $record->clock_out < self::WORK_END_TIME)
                ->count(),
            'long_work_count' => $currentMonthRecords
                ->filter(fn ($record) => $record->totalWorkMinutes() > self::LONG_WORK_MINUTES)
                ->count(),
        ];
    }
}
