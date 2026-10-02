<?php

namespace App\Enums;

enum Locale: string
{
    case Id = 'id';
    case En = 'en';

    /**
     * Native display name; never translated so a user can always find their language.
     */
    public function label(): string
    {
        return match ($this) {
            self::Id => 'Bahasa Indonesia',
            self::En => 'English',
        };
    }
}
