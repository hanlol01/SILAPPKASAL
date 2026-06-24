<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FacultyIndexRequest extends FormRequest
{
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
            'university_id' => [
                'required',
                'integer',
                Rule::exists('universities', 'id')->where('is_active', true),
            ],
        ];
    }
}
