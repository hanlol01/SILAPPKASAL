<?php

namespace App\Http\Requests;

use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffUserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'nip' => trim((string) $this->input('nip')),
            'phone_number' => $this->filled('phone_number') ? trim((string) $this->input('phone_number')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'nip' => ['required', 'string', 'max:20', Rule::unique('users', 'nip')],
            'phone_number' => ['nullable', 'string', new PhoneNumber],
            'university_id' => ['required', 'integer', Rule::exists('universities', 'id')->where('is_active', true)],
            'role_code' => ['required', 'string', Rule::in(['admin', 'satgas_ppks']), Rule::exists('roles', 'code')->where('is_active', true)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
