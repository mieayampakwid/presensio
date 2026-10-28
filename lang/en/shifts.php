<?php

return [
    'staff' => [
        'title' => 'Staff Hours (Fallback)',
        'note' => 'These hours apply to staff without a shift assignment. Staff with a shift follow their shift hours instead.',
        'start' => 'Staff start time',
        'end' => 'Staff end time',
        'sweep' => 'Auto-absent sweep time',
    ],
    'section' => [
        'title' => 'Work Shifts',
        'create' => 'Add shift',
        'empty' => 'No work shifts defined. Staff without a shift follow the staff hours (fallback).',
        'columns' => [
            'name' => 'Name',
            'times' => 'Hours',
            'sweep_offset' => 'Absent sweep',
        ],
        'crosses_midnight' => 'Crosses midnight',
        'sweep_offset' => 'Absent sweep :offset minutes after start',
        'actions' => 'Actions',
        'remove' => 'Delete',
        'remove_description' => 'Shift :name will be deleted. Shifts still in use cannot be deleted.',
        'remove_confirm' => 'Delete',
        'cancel' => 'Cancel',
        'max_note' => 'Up to 10 shifts.',
    ],
    'form' => [
        'create_title' => 'Add shift',
        'create_description' => 'Define a named working-hours template for staff.',
        'edit_title' => 'Edit shift',
        'edit_description' => 'Update the shift hours. Changes only affect records created afterwards.',
        'name' => 'Name',
        'name_placeholder' => 'e.g. Morning shift',
        'start_time' => 'Start time',
        'end_time' => 'End time',
        'sweep_offset_minutes' => 'Absent sweep (minutes after start)',
        'sweep_offset_hint' => '30-480 minutes after the start time; the absent sweep runs at this time.',
        'submit_create' => 'Save',
        'submit_update' => 'Save changes',
    ],
    'errors' => [
        'in_use' => 'This shift is still used as an employee default, a roster row, or an attendance snapshot and cannot be deleted.',
        'max_shifts' => 'Up to 10 work shifts.',
        'sweep_past_midnight' => 'Start time + sweep offset passes midnight; the sweep must run on the shift start date.',
    ],
    'toast' => [
        'created' => 'Work shift created.',
        'updated' => 'Work shift updated.',
        'deleted' => 'Work shift deleted.',
    ],
];
