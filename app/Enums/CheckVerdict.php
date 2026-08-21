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
            self::Clear => 'bg-emerald-50 text-emerald-900 ring-1 ring-inset ring-emerald-200',
            self::Review => 'bg-amber-50 text-amber-950 ring-1 ring-inset ring-amber-200',
            self::Block => 'bg-rose-50 text-rose-900 ring-1 ring-inset ring-rose-200',
        };
    }
}
