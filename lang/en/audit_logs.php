<?php

return [
    'title' => 'Audit Logs',
    'description' => 'Append-only record of academic, administrative, and financial mutations.',
    'breadcrumbs' => [
        'admin' => 'Admin',
        'audit_logs' => 'Audit Logs',
    ],
    'filters' => [
        'entity_type' => 'Entity Type',
        'entity_type_placeholder' => 'e.g. User',
        'entity_id' => 'Entity ID',
        'entity_id_placeholder' => 'e.g. 1',
        'user_id' => 'User ID',
        'user_id_placeholder' => 'e.g. 1',
        'from_date' => 'From Date',
        'to_date' => 'To Date',
        'reset' => 'Reset',
        'filter' => 'Filter',
    ],
    'table' => [
        'timestamp' => 'Timestamp',
        'actor' => 'Actor',
        'action' => 'Action',
        'target' => 'Target',
        'changes' => 'Changes',
        'reason_ip' => 'Reason / IP',
        'system' => 'System',
        'empty' => 'No audit log entries found.',
    ],
    'pagination' => [
        'summary' => 'Page :current of :last (:total entries)',
    ],
];
