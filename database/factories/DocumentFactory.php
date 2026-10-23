<?php

namespace Database\Factories;

use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * By default a draft opening balance written by the owner of a new store.
 *
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'team_id' => fn (array $attributes) => User::query()->whereKey($attributes['author_id'])->firstOrFail()->team_id,
            'kind' => DocumentKind::OpeningBalance,
            'status' => DocumentStatus::Draft,
        ];
    }

    /**
     * Indicate that the document was written by the given user, in their store.
     */
    public function by(User $author): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $author->team_id,
            'author_id' => $author->id,
        ]);
    }

    /**
     * Indicate the kind of document.
     */
    public function kind(DocumentKind $kind): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => $kind,
        ]);
    }

    /**
     * Indicate that the document has been executed by its author.
     */
    public function executed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Executed,
            'executor_id' => $attributes['author_id'],
            'executed_at' => now(),
        ]);
    }
}
