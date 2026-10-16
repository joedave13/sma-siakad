<?php

namespace App\Http\Requests\Admin\Accounts;

use App\Models\Student;
use App\Services\Accounts\AccountRules;
use Illuminate\Foundation\Http\FormRequest;

class StudentAccountRequest extends FormRequest
{
    /**
     * Normalize the is_active checkbox so updates always receive a boolean;
     * account creation never sends the field and stays active.
     */
    protected function prepareForValidation(): void
    {
        if ($this->route('student') !== null) {
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
        $student = $this->route('student');

        return AccountRules::rules(
            'student',
            $student instanceof Student ? $student->id : null,
            $student instanceof Student ? $student->user_id : null,
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return AccountRules::messages('student');
    }
}
