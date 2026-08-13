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
        $normalized = [];

        foreach (['name', 'profile_status', 'profile_status_other', 'address'] as $field) {
            if ($this->exists($field)) {
                $normalized[$field] = $this->filled($field)
                    ? trim((string) $this->input($field))
                    : $this->input($field);
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
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
            'profile_status' => ['sometimes', 'nullable', 'string', 'in:student,lecturer,education_staff,employee,other'],
            'profile_status_other' => ['nullable', 'string', 'max:100', 'required_if:profile_status,other'],
            'address' => ['sometimes', 'nullable', 'string', 'max:160'],
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
