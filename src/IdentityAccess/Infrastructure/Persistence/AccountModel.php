<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Persistence;

use AulaX\IdentityAccess\Application\Auth\HasActiveMembershipHandler;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

/**
 * Persistence (and Laravel authentication) of the global account
 * (SPEC-004, ADR 0004): one per person, shared by all the institutions they
 * belong to through memberships. Spatie stores it as the "user" morph alias.
 */
#[UseFactory(UserFactory::class)]
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class AccountModel extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $table = 'users';

    /**
     * Ids come from the sequence before inserting (see booted()), so the
     * insert needs no RETURNING clause.
     */
    public $incrementing = false;

    /**
     * Under Row Level Security an INSERT ... RETURNING must also pass the
     * SELECT policy, and a brand-new account is not yet a member of the
     * active institution (ADR 0006): take the id first, insert without
     * RETURNING.
     */
    protected static function booted(): void
    {
        static::creating(function (self $user) {
            $user->id ??= (int) DB::selectOne("select nextval(pg_get_serial_sequence('users', 'id')) as id")->id;
        });
    }

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

    /**
     * Whether the account is active and its membership in the active
     * institution is too (IDG-R03.8).
     */
    public function isActive(): bool
    {
        return app(HasActiveMembershipHandler::class)((int) $this->id);
    }
}
