<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Enums;

enum CheckStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return __('aml.statuses.'.$this->value);
    }
}
