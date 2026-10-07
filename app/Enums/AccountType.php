<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case NetAssets = 'net_assets';
    case Revenue = 'revenue';
    case Expense = 'expense';

    /**
     * Normal balance direction for the account type.
     */
    public function normalBalance(): string
    {
        return match ($this) {
            self::Asset, self::Expense => 'debit',
            self::Liability, self::NetAssets, self::Revenue => 'credit',
        };
    }

    /**
     * Human-readable label for account type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Aset',
            self::Liability => 'Liabilitas',
            self::NetAssets => 'Aset Neto',
            self::Revenue => 'Pendapatan',
            self::Expense => 'Beban',
        };
    }
}
