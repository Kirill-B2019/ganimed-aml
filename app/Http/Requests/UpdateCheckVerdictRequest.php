<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Http\Requests;

use App\Enums\CheckVerdict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCheckVerdictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'verdict' => ['required', Rule::in([
                CheckVerdict::Manual->value,
                CheckVerdict::Review->value,
                CheckVerdict::Block->value,
            ])],
            'note' => ['nullable', 'string', 'max:500'],
            'tokens' => ['nullable', 'array'],
            'tokens.*' => ['string', Rule::in(['lookalike', 'noise', 'ignore'])],
        ];
    }
}
