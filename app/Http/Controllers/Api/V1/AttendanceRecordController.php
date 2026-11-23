<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Carbon\Carbon;

class AttendanceRecordController extends Controller
{
    private const DEFAULT_PER_PAGE = 20;

    /**
     * 勤怠一覧を取得する（検索・絞り込み・ページネーション）
     */
    public function index(IndexAttendanceRecordRequest $request)
    {
        $perPage = $request->validated('per_page', self::DEFAULT_PER_PAGE);

        $attendanceRecords = AttendanceRecord::with(['user', 'breakTimes'])
            ->when($request->validated('user_id'), fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($request->validated('date'), fn ($query, $date) => $query->whereDate('date', $date))
            ->when($request->validated('month'), function ($query, $month) {
                $startOfMonth = Carbon::parse($month)->startOfMonth();

                $query->whereBetween('date', [
                    $startOfMonth->toDateString(),
                    $startOfMonth->copy()->endOfMonth()->toDateString(),
                ]);
            })
            ->latest('date')
            ->paginate($perPage)
            ->withQueryString();

        return AttendanceRecordResource::collection($attendanceRecords);
    }

    /**
     * 勤怠を登録する
     */
    public function store(StoreAttendanceRecordRequest $request)
    {
        $attendanceRecord = $request->user()->attendanceRecords()->create($request->validated());

        $attendanceRecord->load(['user', 'breakTimes']);

        return (new AttendanceRecordResource($attendanceRecord))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 勤怠詳細を取得する
     */
    public function show(AttendanceRecord $attendanceRecord)
    {
        $attendanceRecord->load(['user', 'breakTimes', 'attendanceCorrectRequests']);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * 勤怠を更新する
     */
    public function update(UpdateAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord)
    {
        $this->authorize('update', $attendanceRecord);

        $attendanceRecord->update($request->validated());

        $attendanceRecord->load(['user', 'breakTimes']);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * 勤怠を削除する
     */
    public function destroy(AttendanceRecord $attendanceRecord)
    {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->noContent();
    }
}
