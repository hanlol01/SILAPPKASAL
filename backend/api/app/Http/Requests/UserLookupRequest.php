<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserLookupRequest extends FormRequest
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
            'role' => ['required', 'string', Rule::in(self::ALLOWED_ROLES)],
            'search' => ['sometimes', 'string', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
