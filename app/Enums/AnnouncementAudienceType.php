<?php

namespace App\Enums;

enum AnnouncementAudienceType: string
{
    case All = 'all';
    case Role = 'role';
    case GradeLevel = 'grade_level';
    case Classroom = 'class';
}
