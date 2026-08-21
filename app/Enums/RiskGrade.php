<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Enums;

enum RiskGrade: string
{
    case Low = 'low';
    case Moderate = 'moderate';
    case Elevated = 'elevated';
    case High = 'high';
    case Critical = 'critical';

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score <= 0 => self::Low,
            $score <= 34 => self::Moderate,
            $score <= 64 => self::Elevated,
            $score <= 89 => self::High,
            default => self::Critical,
        };
    }

    public function label(): string
    {
        return __('aml.risk_grades.'.$this->value);
    }

    public function range(): string
    {
        return __('aml.risk_grade_ranges.'.$this->value);
    }

    public function hint(): string
    {
        return __('aml.risk_grade_hints.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Low => 'success',
            self::Moderate => 'caution',
            self::Elevated => 'warning',
            self::High => 'severe',
            self::Critical => 'danger',
        };
    }

    public function css(): string
    {
        return match ($this) {
            self::Low => 'bg-emerald-50 text-emerald-900 ring-1 ring-inset ring-emerald-200',
            self::Moderate => 'bg-yellow-50 text-yellow-900 ring-1 ring-inset ring-yellow-200',
            self::Elevated => 'bg-amber-50 text-amber-950 ring-1 ring-inset ring-amber-200',
            self::High => 'bg-orange-50 text-orange-950 ring-1 ring-inset ring-orange-200',
            self::Critical => 'bg-rose-50 text-rose-900 ring-1 ring-inset ring-rose-200',
        };
    }

    public function swatch(): string
    {
        return match ($this) {
            self::Low => '#059669',
            self::Moderate => '#ca8a04',
            self::Elevated => '#d97706',
            self::High => '#ea580c',
            self::Critical => '#e11d48',
        };
    }

    public function fill(): string
    {
        return match ($this) {
            self::Low => '#ecfdf5',
            self::Moderate => '#fefce8',
            self::Elevated => '#fffbeb',
            self::High => '#fff7ed',
            self::Critical => '#fef2f2',
        };
    }

    /**
     * @return list<array{key: string, label: string, range: string, hint: string, tone: string, swatch: string, fill: string}>
     */
    public static function legend(): array
    {
        $rows = [];
        foreach (self::cases() as $grade) {
            $rows[] = [
                'key' => $grade->value,
                'label' => $grade->label(),
                'range' => $grade->range(),
                'hint' => $grade->hint(),
                'tone' => $grade->tone(),
                'swatch' => $grade->swatch(),
                'fill' => $grade->fill(),
            ];
        }

        return $rows;
    }
}
