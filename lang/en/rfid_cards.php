<?php

return [
    'index' => [
        'title' => 'RFID Cards',
        'description' => 'Registered scan cards; assign them to students for attendance.',
        'create' => 'Create card',
        'search_placeholder' => 'Search by card or student…',
        'empty' => 'No cards found.',
        'columns' => [
            'card_number' => 'Card number',
            'assigned_to' => 'Assigned to',
            'status' => 'Status',
        ],
        'revoke' => 'Revoke',
        'delete' => 'Delete',
        'remove_title' => 'Delete card',
        'remove_description' => 'Are you sure you want to delete card ":number"?',
        'remove_confirm' => 'Delete',
    ],
    'status' => [
        'assigned' => 'Assigned',
        'spare' => 'Spare',
    ],
    'form' => [
        'create_title' => 'Create card',
        'create_description' => 'Register a new RFID card number and optionally assign it to a student.',
        'edit_title' => 'Edit card :number',
        'edit_description' => 'Change the assignment or return the card to the spare pool.',
        'card_number' => 'Card number',
        'card_number_placeholder' => 'Number printed on the card',
        'assigned_student' => 'Assigned student',
        'spare_option' => 'Spare (not assigned)',
        'submit_create' => 'Create card',
        'submit_update' => 'Save changes',
    ],
];
