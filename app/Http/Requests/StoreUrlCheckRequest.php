<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUrlCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'min:4', 'max:512'],
        ];
    }
}
