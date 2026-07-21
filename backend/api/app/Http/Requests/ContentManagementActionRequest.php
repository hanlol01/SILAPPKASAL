<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContentManagementActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    public function rules(): array
    {
        return [];
    }
}
