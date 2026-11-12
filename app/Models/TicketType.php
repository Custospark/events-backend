<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketType extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => 'active',
        'quantity_sold' => 0,
        'min_per_order' => 1,
        'max_per_order' => 10,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'tenant_id',
        'event_id',
        'name',
        'description',
        'price',
        'currency',
        'quantity_total',
        'quantity_sold',
        'min_per_order',
        'max_per_order',
        'sales_start_at',
        'sales_end_at',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity_total' => 'integer',
            'quantity_sold' => 'integer',
            'min_per_order' => 'integer',
            'max_per_order' => 'integer',
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function remaining(): int
    {
        return max(0, $this->quantity_total - $this->quantity_sold);
    }

    public function isOnSale(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->sales_start_at !== null && $this->sales_start_at->isFuture()) {
            return false;
        }

        return $this->sales_end_at === null || $this->sales_end_at->isFuture();
    }
}
