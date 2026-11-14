<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiatePayoutRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
