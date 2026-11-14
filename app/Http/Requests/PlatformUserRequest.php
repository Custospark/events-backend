<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformUserRequest extends FormRequest
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
        $user = $this->route('user');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [$creating ? 'required' : 'sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:8', 'max:255'],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->whereNull('tenant_id')],
            'status' => ['sometimes', 'in:active,suspended'],
        ];
    }
}
