<?php

namespace App\Support;

final class ContentCategoryName
{
    public static function display(string $name): string
    {
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($name, \Normalizer::FORM_C);

            if (is_string($normalized)) {
                $name = $normalized;
            }
        }

        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    public static function normalize(string $name): string
    {
        return mb_strtolower(self::display($name));
    }
}
