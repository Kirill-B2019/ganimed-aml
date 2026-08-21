<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Ops;

use App\Models\ScreeningCase;
use App\Models\User;

class DefaultScreeningCases
{
    /**
     * @return list<array{slug: string, name: string, note: string}>
     */
    public static function catalog(): array
    {
        return [
            [
                'slug' => 'client',
                'name' => __('aml.case_default_client'),
                'note' => __('aml.case_default_client_note'),
            ],
            [
                'slug' => 'counterparty',
                'name' => __('aml.case_default_counterparty'),
                'note' => __('aml.case_default_counterparty_note'),
            ],
            [
                'slug' => 'treasury',
                'name' => __('aml.case_default_treasury'),
                'note' => __('aml.case_default_treasury_note'),
            ],
            [
                'slug' => 'incident',
                'name' => __('aml.case_default_incident'),
                'note' => __('aml.case_default_incident_note'),
            ],
            [
                'slug' => 'monitoring',
                'name' => __('aml.case_default_monitoring'),
                'note' => __('aml.case_default_monitoring_note'),
            ],
        ];
    }

    public function ensureFor(User $user): void
    {
        foreach (self::catalog() as $row) {
            ScreeningCase::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'slug' => $row['slug'],
                ],
                [
                    'name' => $row['name'],
                    'note' => $row['note'],
                ],
            );
        }
    }
}
