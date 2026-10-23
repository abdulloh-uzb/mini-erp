<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * For catalogue rows that are archived instead of deleted (`archived_at`): archived rows
 * disappear from pickers for new documents but stay in history and reports.
 *
 * @mixin Model
 */
trait Archivable
{
    /**
     * Determine if the row has been archived.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Hide the row from new documents, keeping it in history.
     */
    public function archive(): void
    {
        $this->update(['archived_at' => now()]);
    }

    /**
     * Make an archived row available to new documents again.
     */
    public function unarchive(): void
    {
        $this->update(['archived_at' => null]);
    }

    /**
     * Limit the query to rows that are not archived, the only ones new documents may use.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('archived_at'));
    }

    /**
     * Limit the query to archived rows.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function archived(Builder $query): void
    {
        $query->whereNotNull($this->qualifyColumn('archived_at'));
    }
}
