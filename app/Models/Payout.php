<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    use BelongsToTenant, HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $fillable = [
        'tenant_id',
        'event_id',
        'reference',
        'gross_amount',
        'commission_amount',
        'net_amount',
        'currency',
        'status',
        'provider_reference',
        'paid_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
