<?php

return [
    'title' => 'Subjects',
    'description' => 'School subject catalog for curriculum, teacher assignments, and report cards.',
    'create' => 'Create Subject',
    'search' => 'Search',
    'search_placeholder' => 'Search code or name...',
    'table' => [
        'code' => 'Code',
        'name' => 'Name',
        'group' => 'Group',
        'order' => 'Order',
        'status' => 'Status',
        'actions' => 'Actions',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'empty' => 'No subjects found.',
    ],
    'create_title' => 'Create Subject',
    'create_description' => 'Add a new subject to the school curriculum catalog.',
    'edit_title' => 'Edit Subject — :name',
    'edit_description' => 'Update subject data.',
    'fields' => [
        'code' => 'Code',
        'code_placeholder' => 'e.g. MAT',
        'name' => 'Name',
        'name_placeholder' => 'e.g. Mathematics',
        'group' => 'Group',
        'sort_order' => 'Sort Order',
        'description' => 'Description (optional)',
        'description_placeholder' => 'Brief subject description...',
        'is_active' => 'Active (Subject is active)',
    ],
    'delete_dialog' => [
        'title' => 'Delete Subject',
        'message' => 'Are you sure you want to delete :name (:code)? This action cannot be undone.',
    ],
];
