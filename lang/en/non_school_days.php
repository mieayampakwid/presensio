<?php

return [
    'index' => [
        'title' => 'Non-School Days',
        'description' => 'Holidays and school closures — the sweep and scanner respect these.',
        'add' => 'Add date',
        'empty' => 'No non-school days recorded.',
        'columns' => [
            'date' => 'Date',
            'name' => 'Name',
            'source' => 'Source',
        ],
        'delete' => 'Delete',
        'remove_title' => 'Delete non-school day',
        'remove_description' => 'Remove :date — ":name"? Note: a synced date deleted while the national feed still lists it will re-import at the next weekly sync.',
        'remove_confirm' => 'Delete',
    ],
    'source' => [
        'sync' => 'Sync',
        'manual' => 'Manual',
    ],
    'form' => [
        'create_title' => 'Add non-school day',
        'create_description' => 'Declare a school closure. Only one entry per date is allowed.',
        'date' => 'Date',
        'name' => 'Name',
        'name_placeholder' => 'e.g. Teacher training day',
        'submit_create' => 'Add date',
    ],
];
