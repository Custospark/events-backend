<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => 'active',
        'currency' => 'UGX',
        'timezone' => 'Africa/Kampala',
        'commission_percent' => 5,
    ];

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'domain',
        'logo_path',
        'status',
        'commission_percent',
        'currency',
        'timezone',
        'settings',
        'onboarded_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_percent' => 'decimal:2',
            'settings' => 'array',
            'onboarded_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
