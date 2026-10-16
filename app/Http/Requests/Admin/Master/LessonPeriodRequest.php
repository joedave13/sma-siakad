<?php

namespace App\Http\Requests\Admin\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonPeriodRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'number' => [
                'required',
                'integer',
                'min:1',
                'max:50',
                Rule::unique('lesson_periods', 'number')->ignore($this->route('lessonPeriod')),
            ],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ];
    }
}
