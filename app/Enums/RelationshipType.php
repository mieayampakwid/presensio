<?php

namespace App\Enums;

enum RelationshipType: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';
}
