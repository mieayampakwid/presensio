<?php

return [
    'index' => [
        'title' => 'Teachers',
        'description' => 'Master data for homeroom assignment and login linking.',
        'create' => 'Create teacher',
        'search_placeholder' => 'Search by name, NIP/NUPTK, or phone…',
        'empty' => 'No teachers found.',
        'columns' => [
            'teacher_number' => 'Teacher number',
        ],
    ],
    'create' => [
        'title' => 'Create teacher',
        'description' => 'Master data for homeroom assignment. A login account can be linked later.',
        'submit' => 'Create teacher',
    ],
    'edit' => [
        'head' => 'Edit :name',
        'title' => 'Edit teacher',
        'description' => 'A teacher homerooming a class or linked to a login account cannot be deleted.',
    ],
    'delete' => [
        'title' => 'Delete teacher',
        'description' => 'Teacher :name will be deleted. Teachers homerooming a class or linked to a login account cannot be deleted.',
    ],
];
