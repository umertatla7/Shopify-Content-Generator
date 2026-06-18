<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'current_account_id',
        'name',
        'email',
        'email_verified_at',
        'password',
        'global_role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function currentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'current_account_id');
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_users')
            ->withPivot(['role_id', 'status', 'permissions'])
            ->withTimestamps();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'account_users')
            ->withPivot(['account_id', 'status', 'permissions'])
            ->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->global_role === 'super_admin';
    }

    public function isPlatformAdmin(): bool
    {
        return in_array($this->global_role, ['super_admin', 'manager'], true);
    }

    public function belongsToAccount(Account|int|null $account): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $accountId = $account instanceof Account ? $account->id : $account;

        if (! $accountId) {
            return false;
        }

        return $this->accounts()
            ->where('accounts.id', $accountId)
            ->wherePivot('status', 'active')
            ->exists();
    }

    public function hasAccountPermission(string $permission, Account|int|null $account = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $accountId = $account instanceof Account ? $account->id : ($account ?? $this->current_account_id);

        if (! $accountId) {
            return false;
        }

        $membership = AccountUser::query()
            ->with('role.permissions')
            ->where('account_id', $accountId)
            ->where('user_id', $this->id)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            return false;
        }

        $directPermissions = $membership->permissions ?? [];

        if (in_array($permission, $directPermissions, true)) {
            return true;
        }

        return $membership->role?->permissions->contains('name', $permission) ?? false;
    }
}
