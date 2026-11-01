<?php

return [
    'form' => [
        'username' => 'Username',
        'username_placeholder' => 'NIS / NIP / NIK',
        'password' => 'Password',
        'password_placeholder' => 'Password',
        'confirm_password' => 'Confirm password',
    ],
    'passkey' => [
        'verify' => 'Sign in with a passkey',
        'verifying' => 'Authenticating...',
        'or_continue' => 'Or continue with email',
    ],
    'login' => [
        'title' => 'Log in',
        'layout_title' => 'Log in to your account',
        'layout_description' => 'Enter your username and password below to log in',
        'forgot_password' => 'Forgot your password?',
        'remember_me' => 'Remember me',
        'submit' => 'Log in',
    ],
    'forgot' => [
        'title' => 'Forgot password',
        'layout_title' => 'Forgot password',
        'layout_description' => 'Enter your username and we will send a reset link to your email on file',
        'submit' => 'Send password reset link',
        'return_to' => 'Or, return to',
        'log_in' => 'log in',
    ],
    'reset' => [
        'title' => 'Reset password',
        'layout_title' => 'Reset password',
        'layout_description' => 'Please enter your new password below',
        'submit' => 'Reset password',
    ],
    'confirm' => [
        'title' => 'Confirm password',
        'layout_title' => 'Confirm password',
        'layout_description' => 'This is a secure area of the application. Please confirm your password before continuing.',
        'passkey_label' => 'Confirm with passkey',
        'passkey_loading' => 'Confirming...',
        'passkey_separator' => 'Or confirm with password',
        'submit' => 'Confirm password',
    ],
    'two_factor' => [
        'title' => 'Two-factor authentication',
        'code_title' => 'Authentication code',
        'code_description' => 'Enter the authentication code provided by your authenticator application.',
        'recovery_title' => 'Recovery code',
        'recovery_description' => 'Please confirm access to your account by entering one of your emergency recovery codes.',
        'recovery_placeholder' => 'Enter recovery code',
        'toggle_code' => 'login using an authentication code',
        'toggle_recovery' => 'login using a recovery code',
        'continue' => 'Continue',
    ],
];
