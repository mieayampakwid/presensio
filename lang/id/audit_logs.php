<?php

return [
    'title' => 'Log Audit',
    'description' => 'Catatan mutasi akademik, administratif, dan finansial yang bersifat append-only.',
    'breadcrumbs' => [
        'admin' => 'Admin',
        'audit_logs' => 'Log Audit',
    ],
    'filters' => [
        'entity_type' => 'Tipe Entitas',
        'entity_type_placeholder' => 'mis. User',
        'entity_id' => 'ID Entitas',
        'entity_id_placeholder' => 'mis. 1',
        'user_id' => 'ID Pengguna',
        'user_id_placeholder' => 'mis. 1',
        'from_date' => 'Dari Tanggal',
        'to_date' => 'Sampai Tanggal',
        'reset' => 'Atur Ulang',
        'filter' => 'Saring',
    ],
    'table' => [
        'timestamp' => 'Waktu',
        'actor' => 'Pelaku',
        'action' => 'Aksi',
        'target' => 'Target',
        'changes' => 'Perubahan',
        'reason_ip' => 'Alasan / IP',
        'system' => 'Sistem',
        'empty' => 'Tidak ada entri log audit ditemukan.',
    ],
    'pagination' => [
        'summary' => 'Halaman :current dari :last (:total entri)',
    ],
];
