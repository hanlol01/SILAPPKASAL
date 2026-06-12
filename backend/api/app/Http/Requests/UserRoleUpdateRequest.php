<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRoleUpdateRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    public const ALLOWED_ROLES = ['admin', 'satgas_ppks', 'reporter'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role_code' => ['required', 'string', Rule::in(self::ALLOWED_ROLES), Rule::exists('roles', 'code')->where('is_active', true)],
        ];
    }
}
