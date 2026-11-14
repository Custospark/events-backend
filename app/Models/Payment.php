<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $attributes = [
        'provider' => 'pesapal',
        'status' => 'pending',
    ];

    protected $fillable = [
        'tenant_id',
        'order_id',
        'provider',
        'provider_reference',
        'amount',
        'currency',
        'status',
        'organiser_name',
        'event_title',
        'transaction_label',
        'fee_bearer_code',
        'reconciliation_code',
        'reconciliation',
        'payload',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payload' => 'array',
            'reconciliation' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'succeeded';
    }
}
