<?php

return [
    'index' => [
        'title' => 'Users',
        'description' => 'Provisioned accounts for all roles.',
        'create' => 'Create user',
        'search_placeholder' => 'Search by username or email…',
        'empty' => 'No users found.',
        'columns' => [
            'username' => 'Username',
            'email' => 'Email',
            'roles' => 'Roles',
            'status' => 'Status',
        ],
    ],
    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],
    'create' => [
        'title' => 'Create user',
        'description' => 'Provision a new account and optionally link it to a teacher, guardian, or student profile.',
    ],
    'edit' => [
        'head' => 'Edit :username',
        'title' => 'Edit user',
        'description' => 'Account credentials and role. Deactivate instead of deleting.',
        'submit' => 'Save changes',
    ],
    'reset_password' => [
        'title' => 'Reset password',
        'label' => 'New password',
        'placeholder' => 'New password',
        'submit' => 'Reset password',
    ],
    'form' => [
        'username' => 'Username',
        'username_placeholder' => 'NIS / NIP / NIK',
        'email' => 'Email (optional)',
        'roles' => 'Roles',
        'linked_profile' => 'Linked :role profile',
        'not_linked' => 'Not linked',
        'active' => 'Active',
        'password' => 'Password',
        'password_placeholder' => 'Password',
        'create_submit' => 'Create user',
    ],
    'toast' => [
        'created' => 'User created.',
        'updated' => 'User updated.',
        'password_updated' => 'Password updated.',
    ],
];
