<?php

namespace App\Actions\Documents;

use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;

/**
 * Drafts waiting for the owner to execute (D3): purchases, sales returns and inventory counts.
 * Feeds the owner home widget and the sidebar badges.
 */
class PendingDrafts
{
    /**
     * The kinds the owner executes, in the order the home widget lists them.
     *
     * @var array<int, DocumentKind>
     */
    private const array KINDS = [DocumentKind::Purchase, DocumentKind::SalesReturn, DocumentKind::InventoryCount];

    /**
     * Count the user's store's drafts per kind, for the kinds the user may execute.
     *
     * @return array<string, int>
     */
    public function counts(User $user): array
    {
        $kinds = $this->executableKinds($user);

        if ($kinds === []) {
            return [];
        }

        $counts = Document::withoutGlobalScopes()
            ->where('team_id', $user->team_id)
            ->where('status', DocumentStatus::Draft)
            ->whereIn('kind', $kinds)
            ->groupBy('kind')
            ->selectRaw('kind, count(*) as total')
            ->pluck('total', 'kind');

        return collect($kinds)->mapWithKeys(fn (DocumentKind $kind) => [$kind->value => (int) ($counts[$kind->value] ?? 0)])->all();
    }

    /**
     * Get the kinds with waiting drafts, each with its count and its latest drafts.
     *
     * @return array<int, array{kind: string, label: string, count: int, href: string, drafts: array<int, array{id: int, number: int, author: string, updated_at: string|null, href: string}>}>
     */
    public function widget(User $user, int $perKind = 5): array
    {
        $groups = [];

        foreach ($this->counts($user) as $value => $count) {
            if ($count === 0) {
                continue;
            }

            $kind = DocumentKind::from($value);
            $drafts = Document::query()
                ->ofKind($kind)
                ->where('status', DocumentStatus::Draft)
                ->with('author')
                ->orderByDesc('updated_at')
                ->limit($perKind)
                ->get();

            $groups[] = [
                'kind' => $kind->value,
                'label' => $kind->label(),
                'count' => $count,
                'href' => route($kind->routePrefix().'.index', ['status' => DocumentStatus::Draft->value], false),
                'drafts' => $drafts->map(fn (Document $draft) => [
                    'id' => $draft->id,
                    'number' => $draft->number,
                    'author' => $draft->author->name,
                    'updated_at' => $draft->updated_at?->toIso8601String(),
                    'href' => route($kind->routePrefix().'.show', $draft, false),
                ])->all(),
            ];
        }

        return $groups;
    }

    /**
     * Get the pending kinds the user may execute.
     *
     * @return array<int, DocumentKind>
     */
    private function executableKinds(User $user): array
    {
        return array_values(array_filter(self::KINDS, fn (DocumentKind $kind) => $user->can($kind->executePermission()->value)));
    }
}
