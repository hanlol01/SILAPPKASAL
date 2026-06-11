<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : $this->input('name'),
            'phone_number' => $this->filled('phone_number') ? trim((string) $this->input('phone_number')) : $this->input('phone_number'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:30'],
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
