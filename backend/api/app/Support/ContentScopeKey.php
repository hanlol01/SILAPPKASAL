<?php

namespace App\Support;

use App\Enums\ContentScope;
use InvalidArgumentException;

final class ContentScopeKey
{
    public static function make(ContentScope|string $scope, ?int $universityId): string
    {
        $scope = $scope instanceof ContentScope ? $scope : ContentScope::tryFrom($scope);

        if ($scope === ContentScope::Global && $universityId === null) {
            return ContentScope::Global->value;
        }

        if ($scope === ContentScope::Campus && $universityId !== null && $universityId > 0) {
            return 'campus:'.$universityId;
        }

        throw new InvalidArgumentException('Content scope and university are inconsistent.');
    }
}
