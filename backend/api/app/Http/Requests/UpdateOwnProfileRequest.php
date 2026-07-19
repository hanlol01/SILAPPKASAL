<?php

namespace App\Http\Requests;

use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('name')) {
            $this->merge([
                'name' => $this->filled('name') ? trim((string) $this->input('name')) : $this->input('name'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'nullable', 'string', new PhoneNumber],
            'email' => ['prohibited'],
            'nim' => ['prohibited'],
            'nip' => ['prohibited'],
            'role' => ['prohibited'],
            'role_id' => ['prohibited'],
            'permissions' => ['prohibited'],
            'is_active' => ['prohibited'],
            'approval_history' => ['prohibited'],
            'approved_user_id' => ['prohibited'],
            'reviewed_by' => ['prohibited'],
            'reviewed_at' => ['prohibited'],
            'rejection_reason' => ['prohibited'],
        ];
    }
}
