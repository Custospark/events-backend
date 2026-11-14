<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkPayoutPaidRequest extends FormRequest
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
            'channel' => ['nullable', Rule::in(['bank_counter', 'mobile_money', 'bank_transfer', 'cash'])],
            'provider_reference' => ['required', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:10240'],
        ];
    }
}
