<?php

namespace App\Http\Requests;

class UpdateContentDraftRequest extends StoreContentItemRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        foreach ($rules as $field => &$fieldRules) {
            $fieldRules = array_values(array_filter(
                $fieldRules,
                static fn ($rule): bool => $rule !== 'required'
            ));
            array_unshift($fieldRules, 'sometimes');
        }
        unset($fieldRules);

        unset($rules['content_type'], $rules['scope'], $rules['university_id'], $rules['slug']);
        $rules['lock_version'] = ['sometimes', 'integer', 'min:1'];

        return $rules;
    }
}
