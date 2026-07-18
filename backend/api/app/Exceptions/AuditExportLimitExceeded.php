<?php

namespace App\Exceptions;

use RuntimeException;

final class AuditExportLimitExceeded extends RuntimeException
{
    public function __construct(public readonly int $rowCount)
    {
        parent::__construct('Audit export row limit exceeded.');
    }
}
