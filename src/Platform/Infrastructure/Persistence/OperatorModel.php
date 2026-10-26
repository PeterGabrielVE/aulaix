<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure\Persistence;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * A platform operator row (SPEC-014), also the user of the «platform» auth
 * guard. Its TOTP secret is encrypted with the application key (PLT-NF02).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property ?string $password
 * @property string $role
 * @property string $status
 * @property ?string $totp_secret
 * @property ?int $totp_last_step
 * @property int $session_version
 */
final class OperatorModel extends Model implements Authenticatable
{
    protected $table = 'platform_operators';

    protected $guarded = ['id'];

    protected $hidden = ['password', 'totp_secret', 'totp_last_step'];

    protected function casts(): array
    {
        return [
            'totp_secret' => 'encrypted',
            'totp_last_step' => 'integer',
            'session_version' => 'integer',
        ];
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password;
    }

    /**
     * The panel keeps no «remember me» (PLT-R01.8).
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
