<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Enums;

enum CheckVerdict: string
{
    case Clear = 'clear';
    case Review = 'review';
    case Block = 'block';

    public function label(): string
    {
        return __('aml.verdicts.'.$this->value);
    }

    public function css(): string
    {
        return match ($this) {
            self::Clear => 'bg-emerald-100 text-emerald-800',
            self::Review => 'bg-amber-100 text-amber-800',
            self::Block => 'bg-rose-100 text-rose-800',
        };
    }
}
