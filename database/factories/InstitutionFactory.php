<?php

namespace Database\Factories;

use App\Enums\InstitutionStatus;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Institution>
 */
class InstitutionFactory extends Factory
{
    protected $model = Institution::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'subdomain' => fake()->unique()->slug(2),
            'status' => InstitutionStatus::Active,
            'dea_code' => fake()->unique()->bothify('OD#####??'),
            'rif' => 'J-'.fake()->unique()->numerify('########').'-'.fake()->randomDigit(),
            'address' => fake()->address(),
            'phone' => fake()->numerify('0212-#######'),
            'email' => fake()->unique()->safeEmail(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => InstitutionStatus::Inactive]);
    }
}
