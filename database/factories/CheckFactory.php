<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace Database\Factories;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Enums\CheckVerdict;
use App\Models\Check;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Check>
 */
class CheckFactory extends Factory
{
    protected $model = Check::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => CheckType::Address,
            'subject' => '0x'.fake()->regexify('[a-f0-9]{40}'),
            'chain_id' => 'tron',
            'status' => CheckStatus::Completed,
            'verdict' => CheckVerdict::Clear,
            'risk_score' => 0,
            'locale' => 'en',
            'flags' => [],
            'raw_response' => ['sanctioned' => '0', 'money_laundering' => '0'],
        ];
    }
}
