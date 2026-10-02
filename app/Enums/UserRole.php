<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Principal = 'principal';
    case Teacher = 'teacher';
    case Counselor = 'counselor';
    case Finance = 'finance';
    case Staff = 'staff';
    case Parent = 'parent';
    case Student = 'student';
}
