<?php

namespace App\Http\Resources;

use App\Models\ContentVersion;

class PublishedContentGovernanceResource extends ContentGovernanceResource
{
    protected function versionForProjection(): ?ContentVersion
    {
        return $this->publishedVersion;
    }
}
