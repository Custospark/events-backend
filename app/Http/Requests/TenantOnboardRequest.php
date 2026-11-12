<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TenantOnboardRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:80', 'unique:tenants,slug'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'domain' => ['nullable', 'string', 'max:255', 'unique:tenants,domain'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:80'],
            'settings' => ['nullable', 'array'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_phone' => ['nullable', 'string', 'max:40'],
            'admin_password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
