<?php

namespace App\Enums;

enum ScanOutcome: string
{
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';
    case AbsentUpgraded = 'absent_upgraded';
    case IgnoredDebounce = 'ignored_debounce';
    case IgnoredComplete = 'ignored_complete';
    case IgnoredExcused = 'ignored_excused';
    case ErrorExpiredToken = 'error_expired_token';
    case ErrorUnknownCredential = 'error_unknown_credential';
}
