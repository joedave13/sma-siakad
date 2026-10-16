<?php

namespace App\Http\Requests\Admin\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('subjects', 'code')->ignore($this->route('subject')),
            ],
            'name' => ['required', 'string', 'max:100'],
            'kkm' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
