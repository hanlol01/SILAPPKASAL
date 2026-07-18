<?php

return [
    'actor' => [
        'reporter' => 'Pelapor',
        'system' => 'Sistem',
    ],
    'roles' => [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'satgas_ppks' => 'Satgas PPKS',
        'staff' => 'Petugas',
    ],
    'validation' => [
        'date_range_max' => 'Rentang tanggal audit maksimal 90 hari.',
    ],
    'export' => [
        'headers' => [
            'public_id' => 'ID Publik',
            'created_at' => 'Waktu',
            'action' => 'Tindakan',
            'category' => 'Kategori',
            'severity' => 'Tingkat',
            'result' => 'Hasil',
            'actor_label' => 'Pelaku',
            'actor_role' => 'Peran',
            'subject_kind' => 'Jenis Objek',
            'subject_reference' => 'Referensi Objek',
            'elevated' => 'Akses Darurat',
            'request_id' => 'ID Permintaan',
        ],
    ],
];
