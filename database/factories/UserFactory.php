<?php

namespace Database\Factories;

use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Accounts are global (SPEC-004): ->for($institution) makes the account a
 * member of that institution, and ->inactive() suspends that membership, as
 * an institution deactivating someone does.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    protected ?InstitutionModel $memberOf = null;

    protected bool $suspended = false;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the institution has suspended the user's membership.
     */
    public function inactive(): static
    {
        $factory = $this->newInstance();
        $factory->suspended = true;

        return $factory;
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * @param  Factory|Model  $factory
     * @param  string|null  $relationship
     * @return static
     */
    public function for($factory, $relationship = null)
    {
        if ($factory instanceof InstitutionModel) {
            $member = $this->newInstance();
            $member->memberOf = $factory;

            return $member;
        }

        return parent::for($factory, $relationship);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, User>|User
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        $created = parent::create($attributes, $parent);

        if ($this->memberOf !== null) {
            // Ignored when it exists: create() with attributes calls itself
            // through a derived factory that carries the same membership.
            foreach (Collection::wrap($created) as $user) {
                DB::table('institution_memberships')->insertOrIgnore([
                    'institution_id' => $this->memberOf->id,
                    'user_id' => $user->id,
                    'status' => $this->suspended ? 'suspended' : 'active',
                    'suspension_reason' => $this->suspended ? 'institution' : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $created;
    }

    /**
     * Carry the membership settings over to every derived factory.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function newInstance(array $arguments = []): static
    {
        $factory = parent::newInstance($arguments);
        $factory->memberOf = $this->memberOf;
        $factory->suspended = $this->suspended;

        return $factory;
    }
}
