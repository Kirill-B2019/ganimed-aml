<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTokenCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract' => ['required', 'string', 'min:8', 'max:256'],
            'chain_id' => ['required', 'string', Rule::in(array_keys(config('goplus.chains')))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'chain_id' => $this->input('chain_id') ?: 'tron',
        ]);
    }
}
