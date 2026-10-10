<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\BelongsToInstitution;
use App\Enums\UserStatus;
use App\Notifications\ResetPasswordLink;
use App\Notifications\UserInvitation;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['institution_id', 'name', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToInstitution, HasFactory, HasRoles, Notifiable;

    /**
     * Each role's landing page after login, in priority order: a user
     * holding several roles lands on the first one that matches.
     */
    public const HOME_ROUTES = [
        'Administrador' => 'admin.home',
        'Director' => 'director.home',
        'Coordinador' => 'coordinator.home',
        'Docente' => 'teacher.home',
        'Representante' => 'guardian.home',
        'Estudiante' => 'student.home',
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
            'status' => UserStatus::class,
        ];
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Email a link for the user to choose their password. Replaces any
     * earlier invitation or reset link still pending.
     */
    public function sendInvitation(): void
    {
        $this->notify(new UserInvitation(Password::broker('invitations')->createToken($this)));
    }

    /**
     * Email a "forgot your password?" link, valid for
     * auth.passwords.users.expire minutes (Password::sendResetLink calls this).
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordLink($token));
    }

    public function hasAcceptedInvitation(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * The route name of this user's home screen, or null when they have no
     * role in the current institution yet.
     */
    public function homeRoute(): ?string
    {
        foreach (self::HOME_ROUTES as $role => $route) {
            if ($this->hasRole($role)) {
                return $route;
            }
        }

        return null;
    }
}
