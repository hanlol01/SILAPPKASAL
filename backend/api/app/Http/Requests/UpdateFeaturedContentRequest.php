<?php

namespace App\Http\Requests;

class UpdateFeaturedContentRequest extends StoreFeaturedContentRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        foreach ($rules as &$fieldRules) {
            $fieldRules = array_values(array_filter($fieldRules, static fn ($rule): bool => $rule !== 'required'));
            array_unshift($fieldRules, 'sometimes');
        }
        unset($fieldRules);

        $rules['concurrency_token'] = ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'];

        return $rules;
    }
}
