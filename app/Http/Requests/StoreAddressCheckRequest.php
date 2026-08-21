<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAddressCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address' => ['required', 'string', 'min:8', 'max:128'],
            'chain_id' => ['nullable', 'string', Rule::in(array_keys(config('goplus.chains')))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'chain_id' => $this->input('chain_id') ?: 'tron',
            'address' => $this->input('address') ?: $this->firstBatchAddress(),
        ]);
    }

    private function firstBatchAddress(): ?string
    {
        $raw = (string) $this->input('addresses', '');
        foreach (preg_split('/\R+/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                return $line;
            }
        }

        return null;
    }
}
