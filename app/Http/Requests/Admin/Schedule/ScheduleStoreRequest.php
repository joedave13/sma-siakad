<?php

namespace App\Http\Requests\Admin\Schedule;

use App\Enums\DayOfWeek;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleStoreRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'teaching_assignment_id' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'day_of_week' => ['required', 'string', Rule::enum(DayOfWeek::class)],
            'lesson_period_id' => ['required', 'integer', 'exists:lesson_periods,id'],
        ];
    }
}
