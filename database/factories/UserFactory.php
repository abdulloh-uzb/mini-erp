<?php

namespace Database\Factories;

use App\Enums\StoreRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * By default a user is the owner of a new store.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+99890'.fake()->unique()->numerify('#######'),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'team_id' => Team::factory(),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->team_id !== null) {
                $user->assignStoreRole(StoreRole::Owner);
            }
        });
    }

    /**
     * Indicate that the user is an employee of the given store.
     */
    public function employeeOf(Team $team): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
        ])->afterCreating(fn (User $user) => $user->assignStoreRole(StoreRole::Employee));
    }

    /**
     * Indicate that the user is a platform super-admin with no store.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => null,
        ])->afterCreating(fn (User $user) => $user->assignStoreRole(StoreRole::SuperAdmin));
    }

    /**
     * Indicate that the user has been deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
