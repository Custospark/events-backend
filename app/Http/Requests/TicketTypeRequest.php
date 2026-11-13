<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TicketTypeRequest extends FormRequest
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
            'event_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:events,id'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'quantity_total' => [$creating ? 'required' : 'sometimes', 'integer', 'min:1'],
            'admits' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'min_per_order' => ['sometimes', 'integer', 'min:1'],
            'max_per_order' => ['sometimes', 'integer', 'min:1'],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after:sales_start_at'],
            'status' => ['sometimes', 'in:active,inactive,sold_out'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
