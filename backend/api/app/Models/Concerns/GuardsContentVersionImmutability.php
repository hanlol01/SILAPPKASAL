<?php

namespace App\Models\Concerns;

use App\Models\ContentVersion;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait GuardsContentVersionImmutability
{
    protected static function bootGuardsContentVersionImmutability(): void
    {
        $guard = static function (Model $model): void {
            $version = ContentVersion::query()->find($model->getAttribute('content_version_id'));
            if ($version?->lifecycle_status?->immutable()) {
                throw new LogicException('Published and archived content version payloads are immutable.');
            }
        };

        static::saving($guard);
        static::deleting($guard);
    }
}
