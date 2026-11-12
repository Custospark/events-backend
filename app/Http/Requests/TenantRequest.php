<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantRequest extends FormRequest
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
        $creating = $this->isMethod('post');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'alpha_dash', 'max:80', Rule::unique('tenants', 'slug')->ignore($this->route('tenant'))],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'domain' => ['nullable', 'string', 'max:255', Rule::unique('tenants', 'domain')->ignore($this->route('tenant'))],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,suspended'],
            'commission_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'timezone' => ['sometimes', 'string', 'max:80'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
