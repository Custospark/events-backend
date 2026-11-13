<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TenantPayoutAccountRequest extends FormRequest
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
            'payout_account' => ['present', 'nullable', 'array'],
            'payout_account.preferred_method' => ['nullable', 'in:bank,mobile_money'],
            'payout_account.bank' => ['nullable', 'array'],
            'payout_account.bank.bank_name' => ['nullable', 'string', 'max:255'],
            'payout_account.bank.account_name' => ['nullable', 'string', 'max:255'],
            'payout_account.bank.account_number' => ['nullable', 'string', 'max:60'],
            'payout_account.bank.branch' => ['nullable', 'string', 'max:255'],
            'payout_account.bank.swift_code' => ['nullable', 'string', 'max:32'],
            'payout_account.mobile_money' => ['nullable', 'array'],
            'payout_account.mobile_money.provider' => ['nullable', 'string', 'max:120'],
            'payout_account.mobile_money.account_name' => ['nullable', 'string', 'max:255'],
            'payout_account.mobile_money.phone_number' => ['nullable', 'string', 'max:40'],
        ];
    }
}
