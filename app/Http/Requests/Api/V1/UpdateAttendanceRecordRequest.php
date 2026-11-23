<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class UpdateAttendanceRecordRequest extends StoreAttendanceRecordRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $attendanceRecord = $this->route('attendanceRecord');

        // clock_in が送られていなければ、登録済みの出勤時刻と比べる
        $clockIn = $this->input('clock_in', $attendanceRecord->clock_in);

        return [
            'date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendance_records')
                    ->where('user_id', $attendanceRecord->user_id)
                    ->ignore($attendanceRecord),
            ],
            'clock_in' => ['sometimes', 'required', 'date_format:H:i:s'],
            'clock_out' => ['nullable', 'date_format:H:i:s', 'after:' . $clockIn],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }
}
