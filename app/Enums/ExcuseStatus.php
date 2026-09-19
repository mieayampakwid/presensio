<?php

namespace App\Enums;

enum ExcuseStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
