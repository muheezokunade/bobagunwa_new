<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;

/**
 * An authenticatable account. Members and officers share this model; an
 * officer may also be linked to a member record. Permissions come only from
 * assigned roles and are resolved server-side on every request.
 */
class User extends Authenticatable
{
    protected $guarded = ['id', 'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** @var Collection<int, string>|null */
    private ?Collection $permissionCache = null;

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot(['granted_by', 'granted_at', 'reason']);
    }

    /** @return Collection<int, string> */
    public function permissionKeys(): Collection
    {
        return $this->permissionCache ??= Permission::query()
            ->join('permission_role', 'permission_role.permission_id', '=', 'permissions.id')
            ->join('role_user', 'role_user.role_id', '=', 'permission_role.role_id')
            ->where('role_user.user_id', $this->id)
            ->distinct()
            ->pluck('permissions.key');
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isActive() && $this->permissionKeys()->contains($permission);
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        return collect($permissions)->contains(fn ($p) => $this->hasPermission($p));
    }

    public function isStaff(): bool
    {
        return $this->isActive() && $this->permissionKeys()->isNotEmpty();
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->member_id === null || $this->member?->status === 'active';
    }

    public function forgetPermissionCache(): void
    {
        $this->permissionCache = null;
    }
}
