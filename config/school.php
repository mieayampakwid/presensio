<?php

return [
    // false = a teacher may homeroom exactly one class (spec 02 §4);
    // true  = duplicates allowed, the validation becomes advisory.
    'allow_multiple_homerooms' => env('SCHOOL_ALLOW_MULTIPLE_HOMEROOMS', false),
];
