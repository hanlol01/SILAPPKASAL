<?php

return [
    'login_failure_retention_days' => (int) env('AUDIT_LOGIN_FAILURE_RETENTION_DAYS', 7),
    'login_failure_fingerprint' => [
        'active_version' => env('AUDIT_LOGIN_FINGERPRINT_VERSION', 'v1'),
        'keys' => [
            'v1' => env('AUDIT_LOGIN_FINGERPRINT_KEY_V1'),
        ],
    ],
    'security_denial_deduplication_minutes' => 5,
    'oversight' => [
        'timezone' => 'Asia/Jakarta',
        'threshold_business_days' => [
            'report_verification' => 2,
            'case_assignment' => 1,
            'satgas_case' => 5,
            'recommendation_review' => 3,
            'decision_handoff' => 5,
            'emergency_access' => 1,
            'critical_security' => 1,
        ],
    ],
];
