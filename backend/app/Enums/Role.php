<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case User = 'user';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
