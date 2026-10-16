<?php

namespace App\Http\Requests\Admin\Accounts;

use App\Models\Teacher;
use App\Services\Accounts\AccountRules;
use Illuminate\Foundation\Http\FormRequest;

class TeacherAccountRequest extends FormRequest
{
    /**
     * Normalize the is_active checkbox so updates always receive a boolean;
     * account creation never sends the field and stays active.
     */
    protected function prepareForValidation(): void
    {
        if ($this->route('teacher') !== null) {
            $this->merge([
                'is_active' => $this->boolean('is_active'),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $teacher = $this->route('teacher');

        return AccountRules::rules(
            'teacher',
            $teacher instanceof Teacher ? $teacher->id : null,
            $teacher instanceof Teacher ? $teacher->user_id : null,
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return AccountRules::messages('teacher');
    }
}
