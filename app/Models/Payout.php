<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    use BelongsToTenant, HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $fillable = [
        'requested_by',
        'initiation',
        'request_priority',
        'requested_note',
        'requested_at',
        'processing_started_at',
        'completed_at',
        'decline_reason',
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
            'requested_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PayoutAttachment::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
