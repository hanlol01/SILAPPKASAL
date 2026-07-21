<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteFeaturedContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    public function rules(): array
    {
        return ['concurrency_token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/']];
    }
}
