<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Enums;

enum CheckType: string
{
    case Address = 'address';
    case Token = 'token';
    case Phishing = 'phishing';
    case Dapp = 'dapp';
    case Scan = 'scan';

    public function label(): string
    {
        return __('aml.types.'.$this->value);
    }
}
