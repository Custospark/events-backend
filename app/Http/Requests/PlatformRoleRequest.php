<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformRoleRequest extends FormRequest
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
            'slug' => [$creating ? 'required' : 'sometimes', 'string', 'alpha_dash', 'max:80', Rule::unique('roles', 'slug')->whereNull('tenant_id')],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => [$creating ? 'required' : 'sometimes', 'array', 'min:1'],
            'permissions.*' => ['string'],
        ];
    }
}
