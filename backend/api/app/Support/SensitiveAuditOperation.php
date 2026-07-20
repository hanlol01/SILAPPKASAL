<?php

namespace App\Support;

final class SensitiveAuditOperation
{
    /** @var array<string, string> */
    private const ROUTES = [
        'audit.show' => 'audit.detail',
        'audit.oversight' => 'audit.oversight',
        'audit.oversight.items' => 'audit.oversight',
        'audit.export' => 'audit.export',
        'break_glass.approve' => 'break_glass.approve',
        'break_glass.deny' => 'break_glass.deny',
        'break_glass.revoke' => 'break_glass.revoke',
        'break_glass.reveal' => 'break_glass.reveal',
        'evidence.upload' => 'evidence.upload',
        'evidence.download' => 'evidence.download',
        'evidence.preview' => 'evidence.preview',
        'reporter_evidence.download' => 'reporter_evidence.download',
        'reporter_evidence.preview' => 'reporter_evidence.preview',
        'recommendation.review' => 'recommendation.review',
    ];

    public static function fromRouteName(?string $routeName): ?string
    {
        return $routeName ? (self::ROUTES[$routeName] ?? null) : null;
    }
}
