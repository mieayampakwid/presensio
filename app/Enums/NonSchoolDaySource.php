<?php

namespace App\Enums;

enum NonSchoolDaySource: string
{
    case Sync = 'sync';
    case Manual = 'manual';
}
