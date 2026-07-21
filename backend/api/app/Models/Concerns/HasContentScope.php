<?php

namespace App\Models\Concerns;

use App\Support\ContentScopeKey;
use Illuminate\Database\Eloquent\Model;

trait HasContentScope
{
    protected static function bootHasContentScope(): void
    {
        static::saving(function (Model $model): void {
            $model->setAttribute('scope_key', ContentScopeKey::make(
                $model->getAttribute('scope'),
                $model->getAttribute('university_id') === null
                    ? null
                    : (int) $model->getAttribute('university_id'),
            ));
        });
    }
}
