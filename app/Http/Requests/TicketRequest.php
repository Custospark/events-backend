<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TicketRequest extends FormRequest
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
            'holder_name' => ['sometimes', 'string', 'max:255'],
            'holder_email' => ['sometimes', 'email', 'max:255'],
            'status' => ['sometimes', 'in:valid,used,cancelled,refunded'],
        ];
    }
}
