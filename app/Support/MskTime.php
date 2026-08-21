<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

class MskTime
{
    public const ZONE = 'Europe/Moscow';

    public static function format(DateTimeInterface|string|null $value, string $pattern = 'd.m.Y H:i'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $dt = $value instanceof DateTimeInterface
            ? Carbon::parse($value)
            : Carbon::parse((string) $value);

        return $dt->timezone(self::ZONE)->format($pattern);
    }

    public static function stamp(DateTimeInterface|string|null $value, string $pattern = 'd.m.Y H:i'): string
    {
        $formatted = self::format($value, $pattern);

        return $formatted === null ? '—' : $formatted.' '.__('aml.timezone_msk');
    }
}
