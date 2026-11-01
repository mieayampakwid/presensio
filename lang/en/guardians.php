<?php

return [
    'index' => [
        'title' => 'Guardians',
        'description' => 'Absence notifications go to these contacts; the phone number is the guardian identity.',
        'create' => 'Create guardian',
        'search_placeholder' => 'Search by name or phone…',
        'empty' => 'No guardians found.',
        'columns' => [
            'occupation' => 'Occupation',
        ],
    ],
    'create' => [
        'title' => 'Create guardian',
        'description' => 'Add a new guardian contact for notifications and student linkage.',
        'submit' => 'Create guardian',
    ],
    'edit' => [
        'title' => 'Edit :name',
        'description' => 'Update contact details.',
    ],
    'delete' => [
        'title' => 'Delete guardian',
        'description' => 'Guardian :name will be deleted. Their links to children are removed with them.',
    ],
    'form' => [
        'name_placeholder' => 'Full name',
        'phone_number' => 'Phone number',
        'phone_number_placeholder' => 'e.g. +6281234567890',
        'occupation' => 'Occupation (optional)',
        'address' => 'Address (optional)',
    ],
];
