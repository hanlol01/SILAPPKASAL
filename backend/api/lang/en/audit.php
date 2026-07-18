<?php

return [
    'actor' => [
        'reporter' => 'Reporter',
        'system' => 'System',
    ],
    'roles' => [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'satgas_ppks' => 'PPKS Task Force',
        'staff' => 'Staff',
    ],
    'validation' => [
        'date_range_max' => 'The audit date range may not exceed 90 days.',
    ],
    'export' => [
        'headers' => [
            'public_id' => 'Public ID',
            'created_at' => 'Time',
            'action' => 'Action',
            'category' => 'Category',
            'severity' => 'Severity',
            'result' => 'Result',
            'actor_label' => 'Actor',
            'actor_role' => 'Role',
            'subject_kind' => 'Subject Type',
            'subject_reference' => 'Subject Reference',
            'elevated' => 'Emergency Access',
            'request_id' => 'Request ID',
        ],
    ],
];
