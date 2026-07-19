<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneNumber implements ValidationRule
{
    public const MAX_LENGTH = 30;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (
            ! is_string($value)
            || mb_strlen($value) > self::MAX_LENGTH
            || preg_match('/^\+?[0-9]+$/D', $value) !== 1
        ) {
            $fail('validation.phone_number')->translate();
        }
    }
}
