<?php

namespace App\Http\Requests\Admin\Accounts;

use App\Models\Guardian;
use App\Services\Accounts\AccountRules;
use Illuminate\Foundation\Http\FormRequest;

class GuardianAccountRequest extends FormRequest
{
    /**
     * Normalize the is_active checkbox so updates always receive a boolean;
     * account creation never sends the field and stays active.
     */
    protected function prepareForValidation(): void
    {
        if ($this->route('guardian') !== null) {
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
        $guardian = $this->route('guardian');

        return AccountRules::rules(
            'parent',
            null,
            $guardian instanceof Guardian ? $guardian->user_id : null,
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return AccountRules::messages('parent');
    }
}
