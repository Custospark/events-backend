<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use BelongsToTenant, HasFactory;

    protected $attributes = [
        'status' => 'valid',
    ];

    protected $fillable = [
        'tenant_id',
        'order_id',
        'order_item_id',
        'event_id',
        'ticket_type_id',
        'code',
        'holder_name',
        'holder_email',
        'status',
        'qr_payload',
        'checked_in_at',
        'checked_in_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('status', 'valid');
    }

    public function isValid(): bool
    {
        return $this->status === 'valid' && $this->checked_in_at === null;
    }
}
