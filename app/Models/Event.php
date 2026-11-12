<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => 'draft',
        'currency' => 'UGX',
        'timezone' => 'Africa/Kampala',
        'service_fee_percent' => 0,
    ];

    protected $fillable = [
        'tenant_id',
        'created_by',
        'title',
        'slug',
        'description',
        'venue',
        'address',
        'city',
        'country',
        'starts_at',
        'ends_at',
        'timezone',
        'status',
        'banner_path',
        'capacity',
        'currency',
        'service_fee_percent',
        'published_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'capacity' => 'integer',
            'service_fee_percent' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function canSell(): bool
    {
        return $this->isPublished() && $this->starts_at->isFuture();
    }
}
