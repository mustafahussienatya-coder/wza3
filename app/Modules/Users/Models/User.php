<?php

namespace App\Modules\Users\Models;

use App\Enums\UserRole;
use App\Modules\Distributors\Models\Distributor;
use App\Traits\HasActivityLog;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'phone', 'username', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasActivityLog, HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
    ];

    protected $appends = [
        'distributor_id',
        'distributor_status',
    ];

    public function getDistributorIdAttribute(): ?int
    {
        return $this->distributor?->id;
    }

    public function getDistributorStatusAttribute(): ?string
    {
        return $this->distributor?->status?->value;
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function distributor()
    {
        return $this->hasOne(Distributor::class);
    }

    public function role(): ?UserRole
    {
        return UserRole::fromValue($this->role);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role() === UserRole::SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->role() === UserRole::ADMIN;
    }

    public function isDistributor(): bool
    {
        return $this->role() === UserRole::DISTRIBUTOR;
    }

    public function isCustomerService(): bool
    {
        return $this->role() === UserRole::CUSTOMER_SERVICE;
    }

    public function hasPermission(string $permission): bool
    {
        return $this->can($permission);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRole($query, UserRole $role)
    {
        return $query->where('role', $role->value);
    }
}
