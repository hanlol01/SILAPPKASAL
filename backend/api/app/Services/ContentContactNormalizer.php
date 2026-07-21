<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ContentContactNormalizer
{
    public function normalize(?string $value, string $field): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^\+?[0-9\s().-]+$/', $value) !== 1) {
            throw ValidationException::withMessages([$field => ['Only digits, common display separators, and an optional leading + are allowed.']]);
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            throw ValidationException::withMessages([$field => ['The contact number must contain 8 to 15 digits.']]);
        }

        return str_starts_with($value, '+') ? '+'.$digits : $digits;
    }
}
